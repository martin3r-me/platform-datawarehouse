<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListDashboardsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.dashboards.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/dashboards - Listet Dashboards (Container für KPIs) inkl. Anzahl verknüpfter KPIs. Parameter: team_id (optional), filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $query = DatawarehouseDashboard::query()
                ->withCount('kpis')
                ->forTeam($teamId);

            $this->applyStandardFilters($query, $arguments, ['name', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, ['name', 'position', 'created_at', 'updated_at'], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (DatawarehouseDashboard $d) => [
                'id'          => $d->id,
                'uuid'        => $d->uuid,
                'name'        => $d->name,
                'description' => $d->description,
                'icon'        => $d->icon,
                'position'    => (int)$d->position,
                'kpis_count'  => $d->kpis_count,
                'team_id'     => $d->team_id,
                'created_at'  => $d->created_at?->toISOString(),
                'updated_at'  => $d->updated_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Dashboards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'dashboards', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
