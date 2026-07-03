<?php

namespace Platform\Datawarehouse\Services;

use Platform\Datawarehouse\Models\DatawarehouseKpi;

/**
 * Validates a dashboard panel's type + config for a team. Used by both the LLM
 * tools and the UI editor so the rules live in one place. Returns null when
 * valid, otherwise a human-readable error string.
 */
class PanelConfigValidator
{
    public const GRANULARITIES = ['month', 'quarter'];

    public function validate(string $type, array $config, int $teamId): ?string
    {
        $registry = (array) config('datawarehouse.dashboard_panels', []);
        if (!isset($registry[$type])) {
            return "Unbekannter Panel-Typ '{$type}'. Erlaubt: " . implode(', ', array_keys($registry)) . '.';
        }

        switch ($type) {
            case 'kpi_value':
                return $this->requireKpi($config['kpi_id'] ?? null, $teamId, 'kpi_id');

            case 'kpi_chart':
                if ($err = $this->requireKpi($config['kpi_id'] ?? null, $teamId, 'kpi_id')) {
                    return $err;
                }
                $g = $config['granularity'] ?? 'month';
                if (!in_array($g, self::GRANULARITIES, true)) {
                    return "granularity muss 'month' oder 'quarter' sein.";
                }
                return null;

            case 'progress':
                $items = $config['items'] ?? [];
                if (!is_array($items) || $items === []) {
                    return 'progress braucht mindestens ein items[]-Element mit kpi_id.';
                }
                foreach ($items as $i => $item) {
                    if (!is_array($item) || ($err = $this->requireKpi($item['kpi_id'] ?? null, $teamId, "items[$i].kpi_id"))) {
                        return $err ?? "items[$i] braucht eine kpi_id.";
                    }
                }
                return null;

            case 'summary':
                $ids = $config['kpi_ids'] ?? [];
                if (!is_array($ids) || $ids === []) {
                    return 'summary braucht mindestens eine kpi_id in kpi_ids[].';
                }
                foreach ($ids as $i => $id) {
                    if ($err = $this->requireKpi($id, $teamId, "kpi_ids[$i]")) {
                        return $err;
                    }
                }
                return null;
        }

        return null;
    }

    private function requireKpi(mixed $id, int $teamId, string $field): ?string
    {
        if (!is_numeric($id)) {
            return "{$field} ist erforderlich (KPI-ID).";
        }
        $exists = DatawarehouseKpi::where('team_id', $teamId)->whereKey((int) $id)->exists();

        return $exists ? null : "{$field}: KPI #{$id} nicht gefunden.";
    }
}
