<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboardPanel;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Removes a panel from its dashboard.
 */
class DeleteDashboardPanelTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboard_panels.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/dashboard-panels/{id} - Entfernt ein Panel von seinem Dashboard. ERFORDERLICH: panel_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'  => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'panel_id' => ['type' => 'integer', 'description' => 'ID des Panels (ERFORDERLICH).'],
            ],
            'required' => ['panel_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'panel_id', DatawarehouseDashboardPanel::class, 'NOT_FOUND', 'Panel nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseDashboardPanel $panel */
            $panel = $found['model'];
            if ((int) ($panel->dashboard->team_id ?? 0) !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Panel.');
            }

            $dashboardId = $panel->dashboard_id;
            $panel->delete();

            return ToolResult::success([
                'panel_id'     => (int) ($arguments['panel_id']),
                'dashboard_id' => $dashboardId,
                'team_id'      => $teamId,
                'message'      => 'Panel entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen des Panels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'panels'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
