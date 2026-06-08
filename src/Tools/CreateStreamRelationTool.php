<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Creates a relation between two streams so a single KPI can join across them.
 * The KpiQueryBuilder already knows how to JOIN via these relations; this tool
 * is the missing management surface (the relation was previously only creatable
 * through the StreamDetail UI).
 *
 * source/target column names are validated (regex + must exist + active) because
 * they are interpolated into the JOIN clause by the query builder.
 */
class CreateStreamRelationTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const ALLOWED_RELATION_TYPES = ['belongs_to', 'has_many'];
    private const JOINABLE_SYSTEM_COLUMNS = ['id', '_external_id'];

    public function getName(): string
    {
        return 'datawarehouse.stream_relations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/stream-relations - Legt eine Beziehung zwischen zwei Datenströmen an, damit ein KPI über beide joinen kann. ERFORDERLICH: source_stream_id, source_column, target_stream_id, target_column. Optional: label, relation_type (belongs_to|has_many, default belongs_to). Die Spalten müssen in der jeweiligen Tabelle existieren und aktiv sein. Danach in der KPI-definition nutzbar via streams[{alias:s1, stream_id:<target>, join:{relation_id:<id>, type:LEFT|INNER}}].';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'source_stream_id' => ['type' => 'integer', 'description' => 'ID des Quell-Streams (hält den Fremdwert). ERFORDERLICH.'],
                'source_column'    => ['type' => 'string', 'description' => 'Spalte im Quell-Stream, die den Fremdwert hält. ERFORDERLICH.'],
                'target_stream_id' => ['type' => 'integer', 'description' => 'ID des Ziel-/Lookup-Streams. ERFORDERLICH.'],
                'target_column'    => ['type' => 'string', 'description' => 'Spalte im Ziel-Stream, auf die verwiesen wird (oft dessen natural_key). ERFORDERLICH.'],
                'label'            => ['type' => 'string', 'description' => 'Optional: sprechender Name, z. B. "Kunde", "Verantwortlicher".'],
                'relation_type'    => ['type' => 'string', 'enum' => self::ALLOWED_RELATION_TYPES, 'description' => 'Optional: default belongs_to.'],
            ],
            'required' => ['source_stream_id', 'source_column', 'target_stream_id', 'target_column'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $sourceId = (int) ($arguments['source_stream_id'] ?? 0);
            $targetId = (int) ($arguments['target_stream_id'] ?? 0);
            $sourceColumn = (string) ($arguments['source_column'] ?? '');
            $targetColumn = (string) ($arguments['target_column'] ?? '');

            if ($sourceId === $targetId) {
                return ToolResult::error('VALIDATION_ERROR', 'source_stream_id und target_stream_id müssen unterschiedlich sein.');
            }

            $source = DatawarehouseStream::forTeam($teamId)->find($sourceId);
            if (!$source) {
                return ToolResult::error('NOT_FOUND', "Quell-Stream {$sourceId} nicht gefunden (oder kein Zugriff).");
            }
            $target = DatawarehouseStream::forTeam($teamId)->find($targetId);
            if (!$target) {
                return ToolResult::error('NOT_FOUND', "Ziel-Stream {$targetId} nicht gefunden (oder kein Zugriff).");
            }

            if ($err = $this->guardColumn($source, $sourceColumn)) {
                return ToolResult::error('VALIDATION_ERROR', "source_column: $err");
            }
            if ($err = $this->guardColumn($target, $targetColumn)) {
                return ToolResult::error('VALIDATION_ERROR', "target_column: $err");
            }

            $relationType = $arguments['relation_type'] ?? 'belongs_to';
            if (!in_array($relationType, self::ALLOWED_RELATION_TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'relation_type muss belongs_to oder has_many sein.');
            }

            $exists = DatawarehouseStreamRelation::where('source_stream_id', $sourceId)
                ->where('source_column', $sourceColumn)
                ->where('target_stream_id', $targetId)
                ->exists();
            if ($exists) {
                return ToolResult::error('CONFLICT', "Für '{$sourceColumn}' existiert bereits eine Relation zu diesem Ziel-Stream.");
            }

            $relation = DatawarehouseStreamRelation::create([
                'team_id'          => $teamId,
                'source_stream_id' => $sourceId,
                'source_column'    => $sourceColumn,
                'target_stream_id' => $targetId,
                'target_column'    => $targetColumn,
                'label'            => isset($arguments['label']) && trim((string) $arguments['label']) !== '' ? trim((string) $arguments['label']) : null,
                'relation_type'    => $relationType,
            ]);

            return ToolResult::success([
                'id'               => $relation->id,
                'source_stream_id' => $relation->source_stream_id,
                'source_column'    => $relation->source_column,
                'target_stream_id' => $relation->target_stream_id,
                'target_column'    => $relation->target_column,
                'label'            => $relation->label,
                'relation_type'    => $relation->relation_type,
                'team_id'          => $teamId,
                'message'          => "Relation angelegt. In KPI-definition nutzbar via join.relation_id={$relation->id}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Relation: ' . $e->getMessage());
        }
    }

    /**
     * Validate that a column is syntactically safe and exists/active on the stream.
     */
    private function guardColumn(DatawarehouseStream $stream, string $column): ?string
    {
        if ($column === '') {
            return 'Spalte ist erforderlich.';
        }
        if (!preg_match(self::COLUMN_REGEX, $column)) {
            return "Spalte '{$column}' enthält ungültige Zeichen.";
        }
        if (in_array($column, self::JOINABLE_SYSTEM_COLUMNS, true)) {
            return null;
        }
        $exists = $stream->columns()
            ->where('column_name', $column)
            ->where('is_active', true)
            ->exists();
        if (!$exists) {
            return "Spalte '{$column}' existiert nicht (oder ist inaktiv) auf Stream '{$stream->name}'.";
        }
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'relations', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
