<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class AttachKpiToDashboardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboards.attachKpi';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/dashboards/{id}/kpis - Verknüpft einen KPI mit einem Dashboard. ERFORDERLICH: dashboard_id, kpi_id. Optional: position (Default: ans Ende). Idempotent — eine bereits bestehende Verknüpfung wird in der Position aktualisiert.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'kpi_id'       => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
                'position'     => ['type' => 'integer', 'description' => 'Optional: Position. Default: ans Ende.'],
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

            $kpi = DatawarehouseKpi::query()->where('team_id', $teamId)->find($kpiId);
            if (!$kpi) {
                return ToolResult::error('NOT_FOUND', 'KPI nicht gefunden (oder kein Zugriff).');
            }

            $position = isset($arguments['position'])
                ? (int)$arguments['position']
                : ((int)$dashboard->kpis()->max('datawarehouse_dashboard_kpis.position') + 1);

            $dashboard->kpis()->syncWithoutDetaching([$kpi->id => ['position' => $position]]);
            // syncWithoutDetaching does not update pivot of existing rows; ensure position is in sync.
            $dashboard->kpis()->updateExistingPivot($kpi->id, ['position' => $position]);

            return ToolResult::success([
                'dashboard_id' => $dashboard->id,
                'kpi_id'       => $kpi->id,
                'position'     => $position,
                'team_id'      => $teamId,
                'message'      => 'KPI mit Dashboard verknüpft.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verknüpfen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'kpis', 'attach'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
