<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class GetKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.kpi.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/kpis/{id} - Holt einen KPI inkl. vollständiger definition (streams, aggregations, filters, calendar_filters). ERFORDERLICH: kpi_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'kpi_id' => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
            ],
            'required' => ['kpi_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'kpi_id', DatawarehouseKpi::class, 'NOT_FOUND', 'KPI nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseKpi $kpi */
            $kpi = $found['model'];

            if ((int)$kpi->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen KPI.');
            }

            return ToolResult::success([
                'id'                      => $kpi->id,
                'uuid'                    => $kpi->uuid,
                'name'                    => $kpi->name,
                'description'             => $kpi->description,
                'icon'                    => $kpi->icon,
                'variant'                 => $kpi->variant,
                'unit'                    => $kpi->unit,
                'format'                  => $kpi->format,
                'decimals'                => (int)$kpi->decimals,
                'position'                => (int)$kpi->position,
                'parent_kpi_id'           => $kpi->parent_kpi_id !== null ? (int)$kpi->parent_kpi_id : null,
                'is_group'                => (bool)$kpi->is_group,
                'children'                => $kpi->children()->get()->map(fn (DatawarehouseKpi $c) => [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'cached_value' => $c->cached_value !== null ? (float)$c->cached_value : null,
                    'unit'         => $c->unit,
                    'position'     => (int)$c->position,
                ])->values()->toArray(),
                'definition'              => $kpi->definition,
                'display_range'           => $kpi->display_range,
                'display_range_label'     => $kpi->displayRangeLabel(),
                'cached_value'            => $kpi->cached_value !== null ? (float)$kpi->cached_value : null,
                'cached_comparison_value' => $kpi->cached_comparison_value !== null ? (float)$kpi->cached_comparison_value : null,
                'cached_at'               => $kpi->cached_at?->toISOString(),
                'is_cache_valid'          => $kpi->isCacheValid(),
                'trend_direction'         => $kpi->trendDirection(),
                'trend_value'             => $kpi->trendValue(),
                'status'                  => $kpi->status,
                'last_error'              => $kpi->last_error,
                'team_id'                 => $kpi->team_id,
                'created_at'              => $kpi->created_at?->toISOString(),
                'updated_at'              => $kpi->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des KPI: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'kpis', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
