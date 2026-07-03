<?php

namespace Platform\Datawarehouse\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Sets the position of multiple panels of a dashboard in one call.
 */
class ReorderDashboardPanelsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    private const MAX_ITEMS = 100;

    public function getName(): string
    {
        return 'datawarehouse.dashboard_panels.reorder';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/dashboards/{id}/panels/reorder - Setzt die Positionen mehrerer Panels in einem Aufruf. ERFORDERLICH: dashboard_id, items (Array aus { panel_id, position }).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'items'        => [
                    'type' => 'array',
                    'description' => 'Array aus { panel_id, position }.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'panel_id' => ['type' => 'integer'],
                            'position' => ['type' => 'integer'],
                        ],
                        'required' => ['panel_id', 'position'],
                    ],
                ],
            ],
            'required' => ['dashboard_id', 'items'],
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

            $items = $arguments['items'] ?? null;
            if (!is_array($items) || $items === []) {
                return ToolResult::error('VALIDATION_ERROR', 'items muss ein nicht-leeres Array sein.');
            }
            if (count($items) > self::MAX_ITEMS) {
                return ToolResult::error('VALIDATION_ERROR', 'Zu viele items (max ' . self::MAX_ITEMS . ').');
            }

            $ownIds = $dashboard->panels()->pluck('id')->all();
            $updated = 0;
            $errors = [];

            DB::transaction(function () use ($items, $dashboard, $ownIds, &$updated, &$errors) {
                foreach ($items as $i => $item) {
                    $panelId = (int) ($item['panel_id'] ?? 0);
                    if (!in_array($panelId, $ownIds, true)) {
                        $errors[] = ['index' => $i, 'panel_id' => $panelId, 'error' => 'Panel gehört nicht zu diesem Dashboard.'];
                        continue;
                    }
                    $dashboard->panels()->whereKey($panelId)->update(['position' => (int) ($item['position'] ?? 0)]);
                    $updated++;
                }
            });

            return ToolResult::success([
                'dashboard_id' => $dashboard->id,
                'updated_count' => $updated,
                'error_count'  => count($errors),
                'errors'       => $errors ?: null,
                'team_id'      => $teamId,
                'message'      => 'Panel-Reihenfolge aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sortieren der Panels: ' . $e->getMessage());
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
