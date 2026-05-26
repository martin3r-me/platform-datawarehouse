<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class UpdateDashboardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboards.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/dashboards/{id} - Aktualisiert ein Dashboard. ERFORDERLICH: dashboard_id. KPI-Verknüpfungen werden über "datawarehouse.dashboards.attachKpi/detachKpi/reorder" gepflegt.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'name'         => ['type' => 'string'],
                'description'  => ['type' => 'string'],
                'icon'         => ['type' => 'string'],
                'position'     => ['type' => 'integer'],
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

            foreach (['name', 'description', 'icon'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $dashboard->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }
            if (array_key_exists('position', $arguments)) {
                $dashboard->position = (int)$arguments['position'];
            }

            $dashboard->save();

            return ToolResult::success([
                'id'      => $dashboard->id,
                'name'    => $dashboard->name,
                'team_id' => $dashboard->team_id,
                'message' => 'Dashboard aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Dashboards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
