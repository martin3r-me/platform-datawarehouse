<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class GetDashboardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.dashboard.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/dashboards/{id} - Holt ein einzelnes Dashboard inkl. zugewiesener KPIs (mit Pivot-Position und aktuellem cached_value). ERFORDERLICH: dashboard_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
            ],
            'required' => ['dashboard_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'dashboard_id', DatawarehouseDashboard::class, 'NOT_FOUND', 'Dashboard nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseDashboard $dashboard */
            $dashboard = $found['model'];

            if ((int)$dashboard->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Dashboard.');
            }

            $kpis = $dashboard->kpis()->get()->map(fn ($k) => [
                'id'             => $k->id,
                'uuid'           => $k->uuid,
                'name'           => $k->name,
                'unit'           => $k->unit,
                'format'         => $k->format,
                'decimals'       => (int)$k->decimals,
                'pivot_position' => (int)($k->pivot->position ?? 0),
                'cached_value'   => $k->cached_value !== null ? (float)$k->cached_value : null,
                'cached_at'      => $k->cached_at?->toISOString(),
                'status'         => $k->status,
                'display_range'  => $k->display_range,
            ])->values()->toArray();

            return ToolResult::success([
                'id'          => $dashboard->id,
                'uuid'        => $dashboard->uuid,
                'name'        => $dashboard->name,
                'description' => $dashboard->description,
                'icon'        => $dashboard->icon,
                'position'    => (int)$dashboard->position,
                'kpis'        => $kpis,
                'team_id'     => $dashboard->team_id,
                'created_at'  => $dashboard->created_at?->toISOString(),
                'updated_at'  => $dashboard->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Dashboards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'dashboards', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
