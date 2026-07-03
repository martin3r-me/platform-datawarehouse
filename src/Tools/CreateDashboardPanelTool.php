<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseDashboardPanel;
use Platform\Datawarehouse\Services\PanelConfigValidator;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Adds a reusable panel to a dashboard. type ∈ config(datawarehouse.dashboard_panels)
 * (kpi_value|kpi_chart|progress|summary); config references the KPI(s) + options:
 *   kpi_value: {kpi_id}
 *   kpi_chart: {kpi_id, granularity:'month'|'quarter', stack_children:bool}
 *   progress:  {items:[{kpi_id}]}
 *   summary:   {kpi_ids:[...]}
 */
class CreateDashboardPanelTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboard_panels.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/dashboards/{id}/panels - Fügt einem Dashboard ein wiederverwendbares Panel hinzu. ERFORDERLICH: dashboard_id, type (kpi_value|kpi_chart|progress|summary), config. Optional: title, position. config je type: kpi_value {kpi_id}; kpi_chart {kpi_id, granularity month|quarter, stack_children bool}; progress {items:[{kpi_id}]}; summary {kpi_ids:[...]}. KPIs müssen dem Team gehören.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'type'         => ['type' => 'string', 'enum' => ['kpi_value', 'kpi_chart', 'progress', 'summary'], 'description' => 'Panel-Typ (ERFORDERLICH).'],
                'title'        => ['type' => 'string', 'description' => 'Optionaler Titel.'],
                'config'       => ['type' => 'object', 'description' => 'Panel-Konfiguration je type (ERFORDERLICH).', 'additionalProperties' => true],
                'position'     => ['type' => 'integer', 'description' => 'Optionale Position. Default: ans Ende.'],
            ],
            'required' => ['dashboard_id', 'type', 'config'],
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

            $type = (string) ($arguments['type'] ?? '');
            $config = $arguments['config'] ?? [];
            if (!is_array($config)) {
                return ToolResult::error('VALIDATION_ERROR', 'config muss ein Objekt sein.');
            }

            if ($error = app(PanelConfigValidator::class)->validate($type, $config, $teamId)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            $position = array_key_exists('position', $arguments)
                ? (int) $arguments['position']
                : ((int) $dashboard->panels()->max('position') + 1);

            $panel = DatawarehouseDashboardPanel::create([
                'dashboard_id' => $dashboard->id,
                'type'         => $type,
                'title'        => $arguments['title'] ?? null,
                'config'       => $config,
                'position'     => $position,
            ]);

            return ToolResult::success([
                'panel_id'     => $panel->id,
                'dashboard_id' => $dashboard->id,
                'type'         => $panel->type,
                'config'       => $panel->config,
                'position'     => $panel->position,
                'team_id'      => $teamId,
                'message'      => 'Panel hinzugefügt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Panels: ' . $e->getMessage());
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
            'idempotent' => false,
        ];
    }
}
