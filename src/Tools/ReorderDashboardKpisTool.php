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

class ReorderDashboardKpisTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.dashboards.reorder';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/dashboards/{id}/reorder - Setzt die Positionen mehrerer KPIs im Dashboard in einem Aufruf. ERFORDERLICH: dashboard_id, items (Array von {kpi_id, position}). Nur Items für tatsächlich verknüpfte KPIs werden angewendet. Maximal 50 Items pro Aufruf.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'dashboard_id' => ['type' => 'integer', 'description' => 'ID des Dashboards (ERFORDERLICH).'],
                'items' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array {kpi_id, position}. Maximal 50 Items.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kpi_id'   => ['type' => 'integer', 'description' => 'ID des KPI.'],
                            'position' => ['type' => 'integer', 'description' => 'Neue Position.'],
                        ],
                        'required' => ['kpi_id', 'position'],
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
            $teamId = (int)$resolved['team_id'];

            $dashboardId = (int)($arguments['dashboard_id'] ?? 0);
            if ($dashboardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'dashboard_id ist erforderlich.');
            }

            $dashboard = DatawarehouseDashboard::query()->where('team_id', $teamId)->find($dashboardId);
            if (!$dashboard) {
                return ToolResult::error('NOT_FOUND', 'Dashboard nicht gefunden (oder kein Zugriff).');
            }

            $items = $arguments['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                return ToolResult::error('VALIDATION_ERROR', 'items ist erforderlich und muss ein nicht-leeres Array sein.');
            }
            if (count($items) > 50) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 50 Items pro Aufruf erlaubt.');
            }

            $attachedKpiIds = $dashboard->kpis()->pluck('datawarehouse_kpis.id')->all();
            $updated = [];
            $errors = [];

            DB::transaction(function () use ($dashboard, $items, $attachedKpiIds, &$updated, &$errors) {
                foreach ($items as $index => $item) {
                    $kpiId = (int)($item['kpi_id'] ?? 0);
                    if ($kpiId <= 0) {
                        $errors[] = ['index' => $index, 'error' => 'kpi_id ist erforderlich.'];
                        continue;
                    }
                    if (!in_array($kpiId, $attachedKpiIds, true)) {
                        $errors[] = ['index' => $index, 'kpi_id' => $kpiId, 'error' => 'KPI ist nicht mit diesem Dashboard verknüpft.'];
                        continue;
                    }
                    $position = (int)($item['position'] ?? 0);
                    $dashboard->kpis()->updateExistingPivot($kpiId, ['position' => $position]);
                    $updated[] = ['kpi_id' => $kpiId, 'position' => $position];
                }
            });

            return ToolResult::success([
                'dashboard_id'  => $dashboard->id,
                'updated_count' => count($updated),
                'error_count'   => count($errors),
                'updated'       => $updated,
                'errors'        => $errors,
                'team_id'       => $teamId,
                'message'       => count($updated) . ' Position(en) aktualisiert, ' . count($errors) . ' Fehler.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Neusortieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'dashboards', 'kpis', 'reorder'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
