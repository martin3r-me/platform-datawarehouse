<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Services\KpiDefinitionValidator;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;
use Platform\Datawarehouse\Tools\Concerns\ValidatesKpiAmpel;
use Platform\Datawarehouse\Tools\Concerns\ValidatesKpiHierarchy;

class CreateKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;
    use ValidatesKpiHierarchy;
    use ValidatesKpiAmpel;

    public function getName(): string
    {
        return 'datawarehouse.kpis.POST';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/kpis - Legt einen neuen KPI an. ERFORDERLICH: name, definition (validiert gegen die gleichen Whitelists wie der KpiQueryBuilder — eigene SQL-Fragmente sind nicht möglich). definition muss enthalten: streams[{stream_id, alias: "s0"|...}], aggregations[{function: SUM|COUNT|AVG|MIN|MAX, column, stream_alias, operator?}]. Optional: filters[], calendar_filters{}, snapshot_mode. Siehe "datawarehouse.overview.GET" für die volle Struktur.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'name'          => ['type' => 'string', 'description' => 'Anzeigename (ERFORDERLICH).'],
                'description'   => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'icon'          => ['type' => 'string', 'description' => 'Optional: Heroicon-Name.'],
                'variant'       => ['type' => 'string', 'description' => 'Optional: UI-Variante.'],
                'unit'          => ['type' => 'string', 'description' => 'Optional: Anzeige-Einheit (z.B. "€", "kg").'],
                'format'        => ['type' => 'string', 'description' => 'Optional: Format-Hinweis (z.B. "currency", "percent", "number").'],
                'decimals'      => ['type' => 'integer', 'description' => 'Optional: Anzahl Nachkommastellen.'],
                'position'      => ['type' => 'integer', 'description' => 'Optional: Sortierposition.'],
                'parent_kpi_id' => ['type' => 'integer', 'description' => 'Optional: ID eines Eltern-KPI für die Drill-down-Hierarchie (z. B. "RR" als Eltern von "2500"). null/weglassen = Top-Level-KPI.'],
                'is_group'      => ['type' => 'boolean', 'description' => 'Optional: true = reiner Navigations-Ordner (gruppiert nur Kind-KPIs, hat keinen eigenen Wert). definition ist dann nicht erforderlich. Default false.'],
                'target_value'     => ['type' => ['number', 'null'], 'description' => 'Optional: fixer Zielwert für die Ampel.'],
                'target_kpi_id'    => ['type' => ['integer', 'null'], 'description' => 'Optional: Referenz-KPI als Ziel (z. B. Plan-KPI) → Ampel rechnet Ist/Plan. Alternativ zu target_value.'],
                'target_direction' => ['type' => 'string', 'enum' => ['higher_better', 'lower_better'], 'description' => 'Optional: Richtung der Ampel. higher_better (Umsatz/AE) oder lower_better (Kosten/Storno). Default higher_better.'],
                'green_pct'        => ['type' => 'integer', 'description' => 'Optional: Zielerreichung in %, ab der grün gilt (Default 100).'],
                'yellow_pct'       => ['type' => 'integer', 'description' => 'Optional: Zielerreichung in %, ab der gelb gilt (darunter rot; Default 80).'],
                'display_range' => [
                    'type' => 'string',
                    'enum' => ['current_month', 'current_quarter', 'current_year', 'current_week', 'last_7_days', 'last_30_days', 'last_90_days', 'last_12_months', 'previous_month', 'previous_quarter', 'previous_year', 'year_to_date'],
                    'description' => 'Optional: Standard-Anzeigezeitraum für gecachte Werte.',
                ],
                'definition'    => [
                    'type' => 'object',
                    'description' => 'KPI-Definition (erforderlich, außer bei is_group=true). Wird gegen Whitelists validiert. Mindestens: { streams: [{stream_id, alias: "s0"}], aggregations: [{function: SUM, column: "betrag", stream_alias: "s0"}] }.',
                    'additionalProperties' => true,
                ],
            ],
            'required' => ['name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $isGroup = (bool) ($arguments['is_group'] ?? false);

            if ($isGroup) {
                // A group is a pure navigation folder — no aggregation, no value.
                $definition = [];
            } else {
                $definition = $arguments['definition'] ?? null;
                if (!is_array($definition)) {
                    return ToolResult::error('VALIDATION_ERROR', 'definition muss ein Objekt sein (oder is_group=true für einen Ordner).');
                }

                $validator = app(KpiDefinitionValidator::class);
                if ($error = $validator->validate($definition, $teamId)) {
                    return ToolResult::error('VALIDATION_ERROR', $error);
                }
            }

            $displayRange = $arguments['display_range'] ?? null;
            if ($displayRange !== null && !in_array($displayRange, KpiDefinitionValidator::ALLOWED_DATE_RANGES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiges display_range.');
            }

            $parentKpiId = isset($arguments['parent_kpi_id']) && $arguments['parent_kpi_id'] !== null
                ? (int) $arguments['parent_kpi_id']
                : null;
            if ($error = $this->validateKpiParent($parentKpiId, $teamId)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            if ($error = $this->validateAmpelArgs($arguments, $teamId)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }

            $kpi = DatawarehouseKpi::create([
                'team_id'       => $teamId,
                'user_id'       => $context->user->id,
                'name'          => $name,
                'description'   => $arguments['description'] ?? null,
                'icon'          => $arguments['icon'] ?? 'chart-bar',
                'variant'       => $arguments['variant'] ?? 'primary',
                'unit'          => $arguments['unit'] ?? '',
                'format'        => $arguments['format'] ?? 'number',
                'decimals'      => isset($arguments['decimals']) ? (int)$arguments['decimals'] : 0,
                'position'      => isset($arguments['position']) ? (int)$arguments['position'] : 0,
                'parent_kpi_id' => $parentKpiId,
                'is_group'      => $isGroup,
                'target_value'     => isset($arguments['target_value']) && $arguments['target_value'] !== null ? (float) $arguments['target_value'] : null,
                'target_kpi_id'    => !empty($arguments['target_kpi_id']) ? (int) $arguments['target_kpi_id'] : null,
                'target_direction' => $arguments['target_direction'] ?? 'higher_better',
                'green_pct'        => isset($arguments['green_pct']) ? (int) $arguments['green_pct'] : null,
                'yellow_pct'       => isset($arguments['yellow_pct']) ? (int) $arguments['yellow_pct'] : null,
                'definition'    => $definition,
                'display_range' => $displayRange,
                'status'        => 'active',
            ]);

            return ToolResult::success([
                'id'            => $kpi->id,
                'uuid'          => $kpi->uuid,
                'name'          => $kpi->name,
                'parent_kpi_id' => $kpi->parent_kpi_id !== null ? (int) $kpi->parent_kpi_id : null,
                'is_group'      => (bool) $kpi->is_group,
                'display_range' => $kpi->display_range,
                'status'        => $kpi->status,
                'team_id'       => $kpi->team_id,
                'message'       => 'KPI angelegt. Nutze "datawarehouse.kpis.execute" um den aktuellen Wert zu berechnen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des KPI: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'kpis', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
