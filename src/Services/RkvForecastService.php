<?php

namespace Platform\Datawarehouse\Services;

use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;

/**
 * Computes the RKV Rückvergütung (JRV) forecast, replicating RKV_Tracker_2026:
 *
 *   Jahresprognose(Gesellschaft) = Σ IST(Jan..ist_through_month) + Σ Forecast(rest)
 *   Forecast[m] = SSL-Hochrechnung (Stream 16/17) sonst Vorjahr[m] × Faktor
 *   JRV = Jahresprognose × Staffelsatz (voller Umsatz × Satz der erreichten Stufe)
 *   WKZ (nur eventura) = gestaffelter Festbetrag nach Jahresprognose
 *   Gesamt = JRV_ER + JRV_EV + WKZ
 *
 * IST and Forecast are read via KpiQueryBuilder::executeBreakdown on transient
 * (unsaved) KPI definitions — same pattern as PreviewKpiTool — so stream
 * exclusions ("bereinigt") and snapshot-latest handling apply automatically.
 */
class RkvForecastService
{
    private const MONATE = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    public function __construct(private KpiQueryBuilder $builder) {}

    /**
     * Full computed model for the dashboard/UI and for tool verification.
     */
    public function compute(int $teamId, ?int $userId = null): array
    {
        $cfg = DatawarehouseRkvConfig::forTeamOrDefault($teamId, $userId)->config;

        $er = $this->company($teamId, $cfg, 'er');
        $ev = $this->company($teamId, $cfg, 'ev');

        $jrvEr = $this->jrv($er['prognose'], $cfg['er']['staffel']);
        $jrvEv = $this->jrv($ev['prognose'], $cfg['ev']['staffel']);
        $wkz   = $this->wkz($ev['prognose'], $cfg['ev']['wkz'] ?? []);
        $gesamt = $jrvEr + $jrvEv + $wkz;

        // Event Rent progress → next staffel threshold (or top band value).
        $erNext = $this->nextThreshold($er['prognose'], $cfg['er']['staffel']);
        // eventura progress → fixed JRV-Schwelle.
        $evTarget = (float) ($cfg['ev']['jrv_schwelle'] ?? 300000);

        return [
            'months' => array_slice(self::MONATE, 1),
            'ist_through_month' => (int) ($cfg['ist_through_month'] ?? 6),
            'er' => [
                'label'    => $cfg['er']['label'] ?? 'Event Rent',
                'ist_sum'  => $er['istSum'],
                'prognose' => $er['prognose'],
                'jrv'      => $jrvEr,
                'satz'     => $this->satz($er['prognose'], $cfg['er']['staffel']),
                'series'   => $er['series'],
                'staffel'  => $this->staffelRows($er['prognose'], $cfg['er']['staffel']),
                'progress' => [
                    'value'  => $er['prognose'],
                    'target' => $erNext,
                    'pct'    => $erNext > 0 ? min(100, round($er['prognose'] / $erNext * 100, 1)) : 0,
                ],
            ],
            'ev' => [
                'label'    => $cfg['ev']['label'] ?? 'eventura',
                'ist_sum'  => $ev['istSum'],
                'prognose' => $ev['prognose'],
                'jrv'      => $jrvEv,
                'wkz'      => $wkz,
                'series'   => $ev['series'],
                'staffel'  => $this->staffelRows($ev['prognose'], $cfg['ev']['staffel']),
                'wkz_table' => $this->wkzRows($ev['prognose'], $cfg['ev']['wkz'] ?? []),
                'progress' => [
                    'value'  => $ev['prognose'],
                    'target' => $evTarget,
                    'pct'    => $evTarget > 0 ? min(100, round($ev['prognose'] / $evTarget * 100, 1)) : 0,
                ],
            ],
            'gesamt' => [
                'jrv_er' => $jrvEr,
                'jrv_ev' => $jrvEv,
                'wkz'    => $wkz,
                'total'  => $gesamt,
            ],
        ];
    }

    /**
     * Per-company monthly series + sums.
     *
     * @return array{ist: array<int,float>, forecast: array<int,float>, series: array<int,float>, istSum: float, prognose: float}
     */
    private function company(int $teamId, array $cfg, string $key): array
    {
        $c   = $cfg[$key];
        $col = $cfg['columns'];
        $cut = (int) ($cfg['ist_through_month'] ?? 6);
        $factor = (float) ($cfg['factor'] ?? 1.0);
        $vorjahr = $cfg['vorjahr'][$key] ?? [];

        $ist = $this->breakdownMap($teamId, (int) $c['ist_stream_id'], $col['ist_netto'], $col['ist_date'], [
            'stream_alias' => 's0',
            'column'       => $col['ist_kreditor'],
            'operator'     => '=',
            'value'        => $c['kreditor'],
        ]);

        $forecast = $this->breakdownMap($teamId, (int) $c['forecast_stream_id'], $col['forecast_value'], $col['forecast_date'], null);

        $series = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($m <= $cut) {
                $series[$m] = $ist[$m] ?? 0.0;
            } else {
                $series[$m] = $forecast[$m] ?? (($vorjahr[$m] ?? 0) * $factor);
            }
        }

        $istSum = 0.0;
        for ($m = 1; $m <= $cut; $m++) {
            $istSum += $series[$m];
        }

        return [
            'ist'      => $ist,
            'forecast' => $forecast,
            'series'   => $series,
            'istSum'   => $istSum,
            'prognose' => array_sum($series),
        ];
    }

    /**
     * Run a SUM-by-month breakdown for the target (current) year and return
     * [monthNumber => value]. Reuses KpiQueryBuilder so exclusions + snapshot
     * apply exactly as for saved KPIs.
     *
     * @return array<int,float>
     */
    private function breakdownMap(int $teamId, int $streamId, string $valueColumn, string $dateColumn, ?array $filter): array
    {
        $definition = [
            'streams'      => [['stream_id' => $streamId, 'alias' => 's0']],
            'aggregations' => [['function' => 'SUM', 'column' => $valueColumn, 'stream_alias' => 's0']],
            'calendar_filters' => [
                'date_column'       => $dateColumn,
                'date_stream_alias' => 's0',
            ],
            'snapshot_mode' => 'latest',
        ];
        if ($filter !== null) {
            $definition['filters'] = [$filter];
        }

        $kpi = new DatawarehouseKpi();
        $kpi->team_id = $teamId;
        $kpi->definition = $definition;

        $targetYear = (int) now()->year;
        $map = [];
        try {
            foreach ($this->builder->executeBreakdown($kpi, 'month') as $row) {
                // period is "YYYY-MM"
                [$year, $month] = array_pad(explode('-', (string) $row['period']), 2, null);
                if ((int) $year === $targetYear) {
                    $map[(int) $month] = (float) $row['value'];
                }
            }
        } catch (\Throwable) {
            // stream/column not usable → empty (treated as 0)
        }

        return $map;
    }

    /** Highest staffel band with prognose >= v → prognose * s. */
    private function jrv(float $u, array $staffel): float
    {
        $rate = 0.0;
        foreach ($staffel as $s) {
            if ($u >= (float) $s['v']) {
                $rate = (float) $s['s'];
            }
        }
        return $u * $rate;
    }

    /** Active staffel rate as a display string (e.g. "5.00"). */
    private function satz(float $u, array $staffel): string
    {
        $rate = 0.0;
        foreach ($staffel as $s) {
            if ($u >= (float) $s['v']) {
                $rate = (float) $s['s'];
            }
        }
        return number_format($rate * 100, 2, '.', '');
    }

    /** Highest WKZ step with prognose >= ab → wkz amount. */
    private function wkz(float $u, array $wkz): float
    {
        $amount = 0.0;
        foreach ($wkz as $w) {
            if ($u >= (float) $w['ab']) {
                $amount = (float) $w['wkz'];
            }
        }
        return $amount;
    }

    /** First band threshold above prognose (or the top band's v when maxed). */
    private function nextThreshold(float $u, array $staffel): float
    {
        foreach ($staffel as $s) {
            if ((float) $s['v'] > $u) {
                return (float) $s['v'];
            }
        }
        // Already in/above the top band → use its entry value as the target.
        $last = end($staffel);
        return (float) ($last['v'] ?? 0);
    }

    /**
     * Staffel table rows with active/done flags + expected JRV on the active row.
     */
    private function staffelRows(float $u, array $staffel): array
    {
        $rows = [];
        foreach ($staffel as $s) {
            $v = (float) $s['v'];
            $b = $s['b'] ?? null;
            $active = $u >= $v && ($b === null || $u <= (float) $b);
            $done   = $b !== null && $u > (float) $b;
            $rows[] = [
                'label'   => $s['l'],
                'satz'    => (float) $s['s'],
                'erw_jrv' => $active ? $u * (float) $s['s'] : null,
                'active'  => $active,
                'done'    => $done,
            ];
        }
        return $rows;
    }

    /**
     * WKZ table rows with active/done flags.
     */
    private function wkzRows(float $u, array $wkz): array
    {
        $rows = [];
        $count = count($wkz);
        foreach ($wkz as $i => $w) {
            $ab = (float) $w['ab'];
            $nextAb = isset($wkz[$i + 1]) ? (float) $wkz[$i + 1]['ab'] : null;
            $active = $u >= $ab && ($nextAb === null || $u < $nextAb);
            $done   = $nextAb !== null && $u >= $nextAb;
            $rows[] = [
                'ab'     => $ab,
                'wkz'    => (float) $w['wkz'],
                'active' => $active,
                'done'   => $done,
            ];
        }
        return $rows;
    }
}
