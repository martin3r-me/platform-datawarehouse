<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Services\KpiQueryBuilder;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

class ExecuteKpiAllRangesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'dwh.kpis.executeAllRanges';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/kpis/{id}/execute-all-ranges - Berechnet alle 11 vordefinierten Date-Ranges (current_month, current_quarter, … year_to_date) inkl. Vergleichswerten in einem Call. ERFORDERLICH: kpi_id. Setzt den Cache NICHT — nur Rückgabe.';
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

            try {
                $builder = app(KpiQueryBuilder::class);
                $results = $builder->executeAllRanges($kpi);

                return ToolResult::success([
                    'id'      => $kpi->id,
                    'ranges'  => $results,
                    'team_id' => $kpi->team_id,
                ]);
            } catch (\InvalidArgumentException $e) {
                return ToolResult::error('VALIDATION_ERROR', 'KPI-Definition ungültig: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Ausführen des KPI: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'kpis', 'execute', 'all_ranges'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
