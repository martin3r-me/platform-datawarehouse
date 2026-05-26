<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class DetachKpiFromDashboardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.dashboards.detachKpi';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/dashboards/{id}/kpis/{kpi_id} - Entfernt eine KPI-Verknüpfung von einem Dashboard. Der KPI selbst bleibt bestehen. ERFORDERLICH: dashboard_id, kpi_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'kpi_id'       => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
            ],
            'required' => ['dashboard_id', 'kpi_id'],
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

            $dashboardId = (int)($arguments['dashboard_id'] ?? 0);
            $kpiId = (int)($arguments['kpi_id'] ?? 0);
            if ($dashboardId <= 0 || $kpiId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'dashboard_id und kpi_id sind erforderlich.');
            }

            $dashboard = DatawarehouseDashboard::query()->where('team_id', $teamId)->find($dashboardId);
            if (!$dashboard) {
                return ToolResult::error('NOT_FOUND', 'Dashboard nicht gefunden (oder kein Zugriff).');
            }

            $detached = $dashboard->kpis()->detach($kpiId);

            return ToolResult::success([
                'dashboard_id' => $dashboard->id,
                'kpi_id'       => $kpiId,
                'detached'     => (int)$detached,
                'team_id'      => $teamId,
                'message'      => $detached > 0 ? 'KPI vom Dashboard entfernt.' : 'KPI war nicht mit diesem Dashboard verknüpft.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entkoppeln: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'kpis', 'detach'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
