<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class CreateDashboardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.dashboards.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/dashboards - Erstellt ein neues, leeres Dashboard. ERFORDERLICH: name. Optional: description, icon, position. KPIs werden nachträglich über "datawarehouse.dashboards.attachKpi" verknüpft.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'name'        => ['type' => 'string', 'description' => 'Anzeigename (ERFORDERLICH).'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'icon'        => ['type' => 'string', 'description' => 'Optional: Heroicon-Name.'],
                'position'    => ['type' => 'integer', 'description' => 'Optional: Sortierposition.'],
            ],
            'required' => ['name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $dashboard = DatawarehouseDashboard::create([
                'team_id'     => $teamId,
                'user_id'     => $context->user->id,
                'name'        => $name,
                'description' => $arguments['description'] ?? null,
                'icon'        => $arguments['icon'] ?? 'squares-2x2',
                'position'    => isset($arguments['position']) ? (int)$arguments['position'] : 0,
            ]);

            return ToolResult::success([
                'id'          => $dashboard->id,
                'uuid'        => $dashboard->uuid,
                'name'        => $dashboard->name,
                'description' => $dashboard->description,
                'team_id'     => $dashboard->team_id,
                'message'     => 'Dashboard erstellt. Verknüpfe KPIs über "datawarehouse.dashboards.attachKpi".',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Dashboards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
