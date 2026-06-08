<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Deletes a stream relation. KPIs that referenced it via join.relation_id will
 * fail validation on next execute until their definition is updated — the tool
 * therefore reports the relation it removed so the caller can fix dependents.
 */
class DeleteStreamRelationTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_relations.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/stream-relations/{id} - Löscht eine Stream-Beziehung. ERFORDERLICH: relation_id. Achtung: KPIs, die diese Relation via join.relation_id nutzen, schlagen danach beim Berechnen fehl, bis ihre definition angepasst ist.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'relation_id' => ['type' => 'integer', 'description' => 'ID der Relation (ERFORDERLICH). Nutze "datawarehouse.stream_relations.GET" um IDs zu finden.'],
            ],
            'required' => ['relation_id'],
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

            $relationId = (int) ($arguments['relation_id'] ?? 0);
            $relation = DatawarehouseStreamRelation::where('team_id', $teamId)->find($relationId);
            if (!$relation) {
                return ToolResult::error('NOT_FOUND', "Relation {$relationId} nicht gefunden (oder kein Zugriff).");
            }

            $summary = [
                'id'               => $relation->id,
                'source_stream_id' => $relation->source_stream_id,
                'source_column'    => $relation->source_column,
                'target_stream_id' => $relation->target_stream_id,
                'target_column'    => $relation->target_column,
                'label'            => $relation->label,
            ];

            $relation->delete();

            return ToolResult::success([
                'deleted' => $summary,
                'team_id' => $teamId,
                'message' => 'Relation gelöscht. Prüfe KPIs, die join.relation_id=' . $relationId . ' nutzen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Relation: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'relations', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
