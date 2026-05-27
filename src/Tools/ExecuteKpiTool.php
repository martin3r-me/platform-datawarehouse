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

class ExecuteKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.kpis.execute';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/kpis/{id}/execute - Berechnet den aktuellen KPI-Wert. ERFORDERLICH: kpi_id. Optional: range (überschreibt display_range — eines aus current_month/current_quarter/current_year/current_week/last_30_days/last_90_days/last_12_months/previous_month/previous_quarter/previous_year/year_to_date). cache (default true): bei range=null wird der Cache (cached_value/cached_comparison_value/cached_at) aktualisiert. Bei explizitem range ist die Berechnung IMMER einmalig (kein Cache-Schreiben, weil cached_value semantisch zum display_range gehört) — für Comparisons über alle Ranges nutze "datawarehouse.kpis.executeAllRanges".';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'kpi_id'  => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
                'range' => [
                    'type' => 'string',
                    'enum' => ['current_month', 'current_quarter', 'current_year', 'current_week', 'last_30_days', 'last_90_days', 'last_12_months', 'previous_month', 'previous_quarter', 'previous_year', 'year_to_date'],
                    'description' => 'Optional: explicit date range. Default: display_range des KPI bzw. globaler Wert ohne Datumsfilter.',
                ],
                'cache' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ergebnis als cached_value persistieren. Default: true.',
                ],
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

            $range = $arguments['range'] ?? null;
            if ($range !== null && !array_key_exists($range, KpiQueryBuilder::DATE_RANGE_MAP)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger range. Erlaubt: ' . implode(', ', array_keys(KpiQueryBuilder::DATE_RANGE_MAP)) . '.');
            }
            $cache = array_key_exists('cache', $arguments) ? (bool)$arguments['cache'] : true;

            $builder = app(KpiQueryBuilder::class);

            try {
                // Explicit range: one-off computation, never touches the cache.
                // cached_value is semantically tied to display_range, so overwriting
                // it with a different range would lie to every other reader of the KPI.
                if ($range !== null) {
                    $value = $builder->executeForRange($kpi, $range);

                    return ToolResult::success([
                        'id'         => $kpi->id,
                        'range'      => $range,
                        'value'      => $value,
                        'comparison' => null,
                        'cached'     => false,
                        'team_id'    => $kpi->team_id,
                        'message'    => 'Einmal-Berechnung — nutze "datawarehouse.kpis.executeAllRanges" für Vergleichswerte.',
                    ]);
                }

                if ($cache) {
                    $value = $builder->executeAndCache($kpi, 'tool_execute');
                } else {
                    $value = $builder->execute($kpi);
                }

                return ToolResult::success([
                    'id'         => $kpi->id,
                    'range'      => $kpi->display_range,
                    'value'      => $value,
                    'comparison' => $kpi->cached_comparison_value !== null ? (float)$kpi->cached_comparison_value : null,
                    'cached'     => $cache,
                    'cached_at'  => $kpi->cached_at?->toISOString(),
                    'team_id'    => $kpi->team_id,
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
            'tags' => ['datawarehouse', 'kpis', 'execute'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
