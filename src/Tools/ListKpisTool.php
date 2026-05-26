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
