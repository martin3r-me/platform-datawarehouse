<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Partially updates the team's RKV Rückvergütung (JRV) configuration. Only the
 * sections present in `config` are changed (deep-merged); everything else keeps
 * its current value. Takes effect immediately on the "RKV Rückvergütung" page —
 * a year-end agreement change is just an updated staffel/factor here.
 */
class SetRkvConfigTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.rkv_config.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/rkv/config - Ändert die RKV-Rückvergütungs-Konfiguration (partielles Deep-Merge; nur mitgeschickte Abschnitte werden überschrieben). ERFORDERLICH: config (Objekt). Editierbare Felder: factor (Zahl > 0), ist_through_month (0–12), er.staffel[] / ev.staffel[] (je {l,v,b,s}: s=Satz 0..1, v=Bandstart, b=Bandende oder null für offen), ev.wkz[] ({ab,wkz}), ev.jrv_schwelle, vorjahr.er{1..12}/vorjahr.ev{1..12}. Wirkt sofort auf die Seite "RKV Rückvergütung". Nutze zuerst "datawarehouse.rkv_config.GET" für die aktuelle Struktur.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'config'  => ['type' => 'object', 'description' => 'Partielle Konfiguration (Deep-Merge). Nur enthaltene Abschnitte werden geändert. Struktur wie in "datawarehouse.rkv_config.GET".'],
            ],
            'required' => ['config'],
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

            $patch = $arguments['config'] ?? null;
            if (!is_array($patch) || $patch === []) {
                return ToolResult::error('VALIDATION_ERROR', 'config muss ein nicht-leeres Objekt sein.');
            }

            if ($error = $this->validatePatch($patch)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            $config = DatawarehouseRkvConfig::forTeamOrDefault($teamId, $context->user?->id);
            $config->applyPatch($patch);

            return ToolResult::success([
                'team_id' => $teamId,
                'config'  => $config->config,
                'message' => 'RKV-Konfiguration aktualisiert — wirkt sofort auf die JRV-Hochrechnung.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Speichern der RKV-Konfiguration: ' . $e->getMessage());
        }
    }

    private function validatePatch(array $patch): ?string
    {
        if (array_key_exists('factor', $patch)) {
            if (!is_numeric($patch['factor']) || (float) $patch['factor'] <= 0) {
                return 'factor muss eine Zahl > 0 sein.';
            }
        }

        if (array_key_exists('ist_through_month', $patch)) {
            $m = $patch['ist_through_month'];
            if (!is_int($m) && !(is_numeric($m) && (int) $m == $m)) {
                return 'ist_through_month muss eine ganze Zahl 0–12 sein.';
            }
            if ((int) $m < 0 || (int) $m > 12) {
                return 'ist_through_month muss zwischen 0 und 12 liegen.';
            }
        }

        foreach (['er', 'ev'] as $key) {
            if (!isset($patch[$key]) || !is_array($patch[$key])) {
                continue;
            }
            $sec = $patch[$key];

            if (isset($sec['staffel'])) {
                if ($error = $this->validateStaffel($sec['staffel'], "$key.staffel")) {
                    return $error;
                }
            }
            if (isset($sec['wkz'])) {
                if ($error = $this->validateWkz($sec['wkz'], "$key.wkz")) {
                    return $error;
                }
            }
            if (isset($sec['jrv_schwelle']) && (!is_numeric($sec['jrv_schwelle']) || (float) $sec['jrv_schwelle'] < 0)) {
                return "$key.jrv_schwelle muss eine Zahl >= 0 sein.";
            }
        }

        if (isset($patch['vorjahr']) && is_array($patch['vorjahr'])) {
            foreach (['er', 'ev'] as $key) {
                if (!isset($patch['vorjahr'][$key])) {
                    continue;
                }
                if (!is_array($patch['vorjahr'][$key])) {
                    return "vorjahr.$key muss ein Objekt {monat: wert} sein.";
                }
                foreach ($patch['vorjahr'][$key] as $val) {
                    if (!is_numeric($val)) {
                        return "vorjahr.$key enthält einen nicht-numerischen Wert.";
                    }
                }
            }
        }

        return null;
    }

    private function validateStaffel(mixed $staffel, string $path): ?string
    {
        if (!is_array($staffel) || $staffel === []) {
            return "$path muss ein nicht-leeres Array sein.";
        }
        $prevV = -1;
        foreach (array_values($staffel) as $i => $s) {
            if (!is_array($s) || !isset($s['v'], $s['s'])) {
                return "$path" . "[$i] braucht mindestens v und s.";
            }
            if (!is_numeric($s['v']) || (float) $s['v'] < 0) {
                return "$path" . "[$i].v muss eine Zahl >= 0 sein.";
            }
            if (!is_numeric($s['s']) || (float) $s['s'] < 0 || (float) $s['s'] > 1) {
                return "$path" . "[$i].s (Satz) muss zwischen 0 und 1 liegen (z. B. 0.05 für 5 %).";
            }
            $b = $s['b'] ?? null;
            if ($b !== null && (!is_numeric($b) || (float) $b < (float) $s['v'])) {
                return "$path" . "[$i].b muss null oder >= v sein.";
            }
            if ((float) $s['v'] < $prevV) {
                return "$path muss nach v aufsteigend sortiert sein.";
            }
            $prevV = (float) $s['v'];
        }
        return null;
    }

    private function validateWkz(mixed $wkz, string $path): ?string
    {
        if (!is_array($wkz)) {
            return "$path muss ein Array sein.";
        }
        foreach (array_values($wkz) as $i => $w) {
            if (!is_array($w) || !isset($w['ab'], $w['wkz'])) {
                return "$path" . "[$i] braucht ab und wkz.";
            }
            if (!is_numeric($w['ab']) || (float) $w['ab'] < 0) {
                return "$path" . "[$i].ab muss eine Zahl >= 0 sein.";
            }
            if (!is_numeric($w['wkz']) || (float) $w['wkz'] < 0) {
                return "$path" . "[$i].wkz muss eine Zahl >= 0 sein.";
            }
        }
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'rkv', 'config', 'staffeln'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
