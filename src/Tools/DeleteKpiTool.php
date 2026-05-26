<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class DeleteKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.kpis.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /datawarehouse/kpis/{id} - Soft-deletet einen KPI inkl. Dashboard-Verknüpfungen. ERFORDERLICH: kpi_id. Snapshot-Historie bleibt erhalten.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'kpi_id'  => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
            ],
            'required' => ['kpi_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'kpi_id', DatawarehouseKpi::class, 'NOT_FOUND', 'KPI nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseKpi $kpi */
            $kpi = $found['model'];

            if ((int)$kpi->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen KPI.');
            }

            $kpi->dashboards()->detach();
            $kpi->delete();

            return ToolResult::success([
                'id'      => $kpi->id,
                'team_id' => $kpi->team_id,
                'message' => 'KPI gelöscht und von allen Dashboards entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des KPI: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'kpis', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
