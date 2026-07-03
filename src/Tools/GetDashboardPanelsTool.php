<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Lists the reusable panels of a dashboard (chart / progress / summary / value).
 */
class GetDashboardPanelsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboard_panels.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/dashboards/{id}/panels - Liefert die Panels eines Dashboards (wiederverwendbare Bausteine: kpi_value, kpi_chart, progress, summary) inkl. type, title, config, position. ERFORDERLICH: dashboard_id.';
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
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'dashboard_id', DatawarehouseDashboard::class, 'NOT_FOUND', 'Dashboard nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $dashboard = $found['model'];
            if ((int) $dashboard->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Dashboard.');
            }

            $panels = $dashboard->panels()->get()->map(fn ($p) => [
                'id'       => $p->id,
                'type'     => $p->type,
                'title'    => $p->title,
                'config'   => $p->config,
                'position' => $p->position,
            ])->all();

            return ToolResult::success([
                'dashboard_id' => $dashboard->id,
                'panels'       => $panels,
                'count'        => count($panels),
                'available_types' => array_keys((array) config('datawarehouse.dashboard_panels', [])),
                'team_id'      => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Panels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'dashboards', 'panels'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
