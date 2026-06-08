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
 * Lists stream relations for the team, optionally filtered to those touching a
 * given stream (as source or target). The relation ids returned here are what
 * a KPI definition references via join.relation_id.
 */
class ListStreamRelationsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.stream_relations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/stream-relations - Listet die Stream-Beziehungen des Teams. Optional: stream_id (nur Relationen, die diesen Stream als Quelle ODER Ziel betreffen). Die zurückgegebene id wird in KPI-definitionen als join.relation_id verwendet.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'stream_id' => ['type' => 'integer', 'description' => 'Optional: nur Relationen mit diesem Stream als Quelle oder Ziel.'],
            ],
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

            $query = DatawarehouseStreamRelation::where('team_id', $teamId)
                ->with(['sourceStream:id,name', 'targetStream:id,name']);

            if (!empty($arguments['stream_id'])) {
                $streamId = (int) $arguments['stream_id'];
                $query->where(function ($q) use ($streamId) {
                    $q->where('source_stream_id', $streamId)
                      ->orWhere('target_stream_id', $streamId);
                });
            }

            $relations = $query->orderBy('id')->get()->map(fn (DatawarehouseStreamRelation $r) => [
                'id'               => $r->id,
                'source_stream_id' => $r->source_stream_id,
                'source_stream'    => $r->sourceStream?->name,
                'source_column'    => $r->source_column,
                'target_stream_id' => $r->target_stream_id,
                'target_stream'    => $r->targetStream?->name,
                'target_column'    => $r->target_column,
                'label'            => $r->label,
                'relation_type'    => $r->relation_type,
            ])->values()->toArray();

            return ToolResult::success([
                'data'    => $relations,
                'total'   => count($relations),
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Relationen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'streams', 'relations', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
