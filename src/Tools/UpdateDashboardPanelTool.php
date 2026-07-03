<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboardPanel;
use Platform\Datawarehouse\Services\PanelConfigValidator;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Updates a dashboard panel (title / type / config). The resulting type+config
 * is re-validated via PanelConfigValidator.
 */
class UpdateDashboardPanelTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboard_panels.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/dashboard-panels/{id} - Aktualisiert ein Panel (title, type, config). ERFORDERLICH: panel_id. Nur mitgeschickte Felder ändern sich; type+config werden zusammen validiert.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'  => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'panel_id' => ['type' => 'integer', 'description' => 'ID des Panels (ERFORDERLICH).'],
                'title'    => ['type' => 'string', 'description' => 'Optional: neuer Titel (leer = entfernen).'],
                'type'     => ['type' => 'string', 'enum' => ['kpi_value', 'kpi_chart', 'progress', 'summary'], 'description' => 'Optional: neuer Typ.'],
                'config'   => ['type' => 'object', 'description' => 'Optional: neue Konfiguration (ersetzt die bisherige).', 'additionalProperties' => true],
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

            $type = $arguments['type'] ?? $panel->type;
            $config = array_key_exists('config', $arguments) ? $arguments['config'] : $panel->config;
            if (!is_array($config)) {
                return ToolResult::error('VALIDATION_ERROR', 'config muss ein Objekt sein.');
            }

            if ($error = app(PanelConfigValidator::class)->validate($type, $config, $teamId)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            $panel->type = $type;
            $panel->config = $config;
            if (array_key_exists('title', $arguments)) {
                $panel->title = $arguments['title'] === '' ? null : $arguments['title'];
            }
            $panel->save();

            return ToolResult::success([
                'panel_id' => $panel->id,
                'type'     => $panel->type,
                'title'    => $panel->title,
                'config'   => $panel->config,
                'team_id'  => $teamId,
                'message'  => 'Panel aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Panels: ' . $e->getMessage());
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
