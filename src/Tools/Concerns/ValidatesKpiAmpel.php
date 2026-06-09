<?php

namespace Platform\Datawarehouse\Tools\Concerns;

use Platform\Datawarehouse\Models\DatawarehouseKpi;

/**
 * Shared validation for the configurable RAG (Ampel) threshold fields,
 * used by CreateKpiTool and UpdateKpiTool.
 */
trait ValidatesKpiAmpel
{
    /**
     * Validate Ampel-related arguments. Returns an error string or null.
     */
    protected function validateAmpelArgs(array $args, int $teamId, ?int $selfId = null): ?string
    {
        if (array_key_exists('target_direction', $args) && $args['target_direction'] !== null
            && !in_array($args['target_direction'], ['higher_better', 'lower_better'], true)) {
            return 'target_direction muss "higher_better" oder "lower_better" sein.';
        }

        foreach (['green_pct', 'yellow_pct'] as $key) {
            if (array_key_exists($key, $args) && $args[$key] !== null) {
                $v = (int) $args[$key];
                if ($v < 0 || $v > 1000) {
                    return "{$key} muss zwischen 0 und 1000 (% der Zielerreichung) liegen.";
                }
            }
        }

        if (!empty($args['target_kpi_id'])) {
            $tid = (int) $args['target_kpi_id'];
            if ($selfId !== null && $tid === $selfId) {
                return 'target_kpi_id darf nicht der KPI selbst sein.';
            }
            $exists = DatawarehouseKpi::query()->forTeam($teamId)->whereKey($tid)->exists();
            if (!$exists) {
                return "target_kpi_id {$tid} nicht gefunden (oder kein Zugriff).";
            }
        }

        return null;
    }
}
