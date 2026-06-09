<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ListKpisTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.kpis.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/kpis - Listet KPIs des Teams inkl. cached_value und Trend. Parameter: team_id (optional), status (optional, active/error), filters/search/sort/limit/offset.';
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
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (z.B. "active", "error").',
                    ],
                    'tree' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Wenn true, wird die Drill-down-Hierarchie verschachtelt zurückgegeben — nur Top-Level-KPIs (parent_kpi_id=null), jeweils mit children[]. Paging wird in diesem Modus ignoriert.',
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

            $query = DatawarehouseKpi::query()->forTeam($teamId);

            if (isset($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            // Tree mode: return the drill-down hierarchy nested, ignore paging.
            if (!empty($arguments['tree'])) {
                $all = (clone $query)->orderBy('position')->get();
                $byParent = $all->groupBy('parent_kpi_id');
                $roots = $all->filter(fn (DatawarehouseKpi $k) => $k->parent_kpi_id === null);

                return ToolResult::success([
                    'data'    => $roots->map(fn (DatawarehouseKpi $k) => $this->mapNode($k, $byParent))->values()->toArray(),
                    'tree'    => true,
                    'total'   => $all->count(),
                    'team_id' => $teamId,
                ]);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'status', 'variant', 'unit', 'format', 'display_range', 'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, [
                'name', 'position', 'status', 'cached_at', 'created_at', 'updated_at',
            ], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (DatawarehouseKpi $k) => [
                'id'                      => $k->id,
                'uuid'                    => $k->uuid,
                'name'                    => $k->name,
                'description'             => $k->description,
                'icon'                    => $k->icon,
                'variant'                 => $k->variant,
                'unit'                    => $k->unit,
                'format'                  => $k->format,
                'decimals'                => (int)$k->decimals,
                'position'                => (int)$k->position,
                'parent_kpi_id'           => $k->parent_kpi_id !== null ? (int)$k->parent_kpi_id : null,
                'is_group'                => (bool)$k->is_group,
                'display_range'           => $k->display_range,
                'display_range_label'     => $k->displayRangeLabel(),
                'cached_value'            => $k->cached_value !== null ? (float)$k->cached_value : null,
                'cached_comparison_value' => $k->cached_comparison_value !== null ? (float)$k->cached_comparison_value : null,
                'cached_at'               => $k->cached_at?->toISOString(),
                'trend_direction'         => $k->trendDirection(),
                'trend_value'             => $k->trendValue(),
                'status'                  => $k->status,
                'last_error'              => $k->last_error,
                'team_id'                 => $k->team_id,
                'created_at'              => $k->created_at?->toISOString(),
                'updated_at'              => $k->updated_at?->toISOString(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der KPIs: ' . $e->getMessage());
        }
    }

    /**
     * Map a KPI to a compact tree node with nested children.
     *
     * @param  \Illuminate\Support\Collection  $byParent  KPIs grouped by parent_kpi_id.
     */
    private function mapNode(DatawarehouseKpi $kpi, $byParent): array
    {
        $children = $byParent->get($kpi->id, collect());

        return [
            'id'              => $kpi->id,
            'name'            => $kpi->name,
            'icon'            => $kpi->icon,
            'variant'         => $kpi->variant,
            'unit'            => $kpi->unit,
            'is_group'        => (bool)$kpi->is_group,
            'cached_value'    => $kpi->cached_value !== null ? (float)$kpi->cached_value : null,
            'trend_direction' => $kpi->trendDirection(),
            'position'        => (int)$kpi->position,
            'children'        => $children->map(fn (DatawarehouseKpi $c) => $this->mapNode($c, $byParent))->values()->toArray(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'kpis', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
