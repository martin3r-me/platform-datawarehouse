<?php

namespace Platform\Datawarehouse\Services;

use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseKpi;

/**
 * Resolves a dashboard's panels into render-ready data. Reuses the KPI engine:
 * cached values / targets (ampel) for value/progress/summary panels and
 * KpiQueryBuilder::executeBreakdown for chart panels (optionally stacked by
 * child KPIs). Missing/foreign KPIs are skipped gracefully.
 */
class DashboardPanelService
{
    private const PALETTE = ['#166EE1', '#0F9D74', '#F59E0B', '#8B5CF6', '#EF4444', '#14B8A6', '#EC4899', '#64748B'];

    public function __construct(private KpiQueryBuilder $builder) {}

    /**
     * @return array<int,array{id:int,type:string,title:?string,width:string,data:array}>
     */
    public function resolve(DatawarehouseDashboard $dashboard): array
    {
        $registry = (array) config('datawarehouse.dashboard_panels', []);
        $teamId = (int) $dashboard->team_id;
        $out = [];

        foreach ($dashboard->panels as $panel) {
            $type = $panel->type;
            if (!isset($registry[$type])) {
                continue;
            }
            $cfg = $panel->config ?? [];

            $data = match ($type) {
                'kpi_value' => ['kpi' => $this->kpi($teamId, $cfg['kpi_id'] ?? null)],
                'kpi_chart' => $this->chartData($teamId, $cfg),
                'progress'  => ['items' => $this->progressData($teamId, $cfg['items'] ?? [])],
                'summary'   => ['cards' => $this->summaryData($teamId, $cfg['kpi_ids'] ?? [])],
                default     => [],
            };

            $out[] = [
                'id'    => $panel->id,
                'type'  => $type,
                'title' => $panel->title,
                'width' => $registry[$type]['width'] ?? 'half',
                'data'  => $data,
            ];
        }

        return $out;
    }

    private function kpi(int $teamId, mixed $id): ?DatawarehouseKpi
    {
        if (!is_numeric($id)) {
            return null;
        }
        return DatawarehouseKpi::where('team_id', $teamId)->find((int) $id);
    }

    private function chartData(int $teamId, array $cfg): array
    {
        $kpi = $this->kpi($teamId, $cfg['kpi_id'] ?? null);
        $granularity = in_array($cfg['granularity'] ?? 'month', ['month', 'quarter'], true) ? $cfg['granularity'] : 'month';
        $stack = (bool) ($cfg['stack_children'] ?? false);

        if (!$kpi) {
            return ['kpi' => null, 'bars' => [], 'legend' => [], 'max' => 1.0, 'unit' => '', 'decimals' => 0, 'granularity' => $granularity];
        }

        $rows = [];
        try {
            $rows = $this->builder->executeBreakdown($kpi, $granularity);
        } catch (\Throwable) {
            $rows = [];
        }

        // Optional: split each period by child KPI (stacked, one colour per child).
        $order = [];
        $childMaps = [];
        if ($stack) {
            foreach ($kpi->children()->get() as $child) {
                $order[] = $child->name;
                $map = [];
                try {
                    foreach ($this->builder->executeBreakdown($child, $granularity) as $r) {
                        $map[$r['period']] = (float) $r['value'];
                    }
                } catch (\Throwable) {
                    // ignore child without usable definition
                }
                $childMaps[$child->name] = $map;
            }
        }
        $childColors = [];
        foreach (array_values($order) as $i => $name) {
            $childColors[$name] = self::PALETTE[$i % count(self::PALETTE)];
        }

        $bars = [];
        $max = 0.0;
        foreach ($rows as $r) {
            $total = (float) $r['value'];
            $segments = [];
            foreach ($order as $name) {
                $v = $childMaps[$name][$r['period']] ?? 0.0;
                if ($v != 0.0) {
                    $segments[] = ['name' => $name, 'color' => $childColors[$name], 'value' => $v];
                }
            }
            if ($segments === []) {
                $segments = [['name' => $kpi->name, 'color' => self::PALETTE[0], 'value' => $total]];
            }
            $max = max($max, $total, array_sum(array_column($segments, 'value')));
            $bars[] = ['label' => $r['label'], 'total' => $total, 'segments' => $segments];
        }

        return [
            'kpi'         => $kpi,
            'bars'        => $bars,
            'legend'      => array_map(fn ($n) => ['name' => $n, 'color' => $childColors[$n]], $order),
            'max'         => max(1.0, $max),
            'unit'        => $kpi->unit,
            'decimals'    => $kpi->decimals ?? 0,
            'granularity' => $granularity,
        ];
    }

    private function progressData(int $teamId, array $items): array
    {
        $out = [];
        $i = 0;
        foreach ($items as $item) {
            $kpi = $this->kpi($teamId, is_array($item) ? ($item['kpi_id'] ?? null) : null);
            if (!$kpi) {
                continue;
            }
            $value = (float) ($kpi->cached_value ?? 0);
            $target = $kpi->resolveTarget();
            $pct = ($target && $target > 0) ? min(100, round($value / $target * 100, 1)) : 0;
            $out[] = [
                'label'    => $kpi->name,
                'value'    => $value,
                'target'   => $target,
                'pct'      => $pct,
                'unit'     => $kpi->unit,
                'decimals' => $kpi->decimals ?? 0,
                'color'    => self::PALETTE[$i % count(self::PALETTE)],
            ];
            $i++;
        }
        return $out;
    }

    private function summaryData(int $teamId, array $ids): array
    {
        $cards = [];
        foreach ($ids as $id) {
            $kpi = $this->kpi($teamId, $id);
            if (!$kpi) {
                continue;
            }
            $cards[] = [
                'label'    => $kpi->name,
                'value'    => $kpi->cached_value !== null ? (float) $kpi->cached_value : null,
                'unit'     => $kpi->unit,
                'decimals' => $kpi->decimals ?? 0,
            ];
        }
        return $cards;
    }
}
