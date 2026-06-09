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

class UpdateKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;
    use ValidatesKpiHierarchy;
    use ValidatesKpiAmpel;

    public function getName(): string
    {
        return 'datawarehouse.kpis.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /datawarehouse/kpis/{id} - Aktualisiert einen KPI. ERFORDERLICH: kpi_id. Wenn definition mitgesendet wird, läuft sie durch dieselben Whitelists wie beim Create. Eine neue definition setzt den Cache zurück (cached_value=null, cached_at=null) — der nächste "datawarehouse.kpis.execute" oder Scheduler-Lauf berechnet neu.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'kpi_id'        => ['type' => 'integer', 'description' => 'ID des KPI (ERFORDERLICH).'],
                'name'          => ['type' => 'string'],
                'description'   => ['type' => 'string'],
                'icon'          => ['type' => 'string'],
                'variant'       => ['type' => 'string'],
                'unit'          => ['type' => 'string'],
                'format'        => ['type' => 'string'],
                'decimals'      => ['type' => 'integer'],
                'position'      => ['type' => 'integer'],
                'parent_kpi_id' => ['type' => ['integer', 'null'], 'description' => 'Optional: Eltern-KPI für die Drill-down-Hierarchie setzen. null = zum Top-Level lösen. Selbst-/Zyklusbezüge werden abgelehnt.'],
                'is_group'      => ['type' => 'boolean', 'description' => 'Optional: zu Navigations-Ordner machen (true) oder zurück zu Wert-KPI (false). Bei false muss eine gültige definition vorhanden/mitgesendet sein.'],
                'target_value'     => ['type' => ['number', 'null'], 'description' => 'Optional: fixer Zielwert für die Ampel (null = entfernen).'],
                'target_kpi_id'    => ['type' => ['integer', 'null'], 'description' => 'Optional: Referenz-KPI als Ziel (z. B. Plan) → Ampel rechnet Ist/Plan (null = entfernen).'],
                'target_direction' => ['type' => 'string', 'enum' => ['higher_better', 'lower_better'], 'description' => 'Optional: higher_better (Default) oder lower_better.'],
                'green_pct'        => ['type' => ['integer', 'null'], 'description' => 'Optional: Zielerreichung %, ab der grün gilt (Default 100).'],
                'yellow_pct'       => ['type' => ['integer', 'null'], 'description' => 'Optional: Zielerreichung %, ab der gelb gilt (darunter rot; Default 80).'],
                'display_range' => [
                    'type' => 'string',
                    'enum' => ['current_month', 'current_quarter', 'current_year', 'current_week', 'last_7_days', 'last_30_days', 'last_90_days', 'last_12_months', 'previous_month', 'previous_quarter', 'previous_year', 'year_to_date'],
                ],
                'definition' => [
                    'type' => 'object',
                    'description' => 'Optional: vollständige neue Definition (kein Partial-Merge — der Builder validiert die ganze Struktur).',
                    'additionalProperties' => true,
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

            foreach (['name', 'description', 'icon', 'variant', 'unit', 'format'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $kpi->{$field} = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }
            foreach (['decimals', 'position'] as $intField) {
                if (array_key_exists($intField, $arguments)) {
                    $kpi->{$intField} = (int)$arguments[$intField];
                }
            }
            if (array_key_exists('display_range', $arguments)) {
                $range = $arguments['display_range'];
                if ($range !== null && !in_array($range, KpiDefinitionValidator::ALLOWED_DATE_RANGES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiges display_range.');
                }
                $kpi->display_range = $range;
            }

            if (array_key_exists('parent_kpi_id', $arguments)) {
                $parentKpiId = $arguments['parent_kpi_id'] !== null ? (int) $arguments['parent_kpi_id'] : null;
                if ($error = $this->validateKpiParent($parentKpiId, $teamId, $kpi)) {
                    return ToolResult::error('VALIDATION_ERROR', $error);
                }
                $kpi->parent_kpi_id = $parentKpiId;
            }

            if (array_key_exists('is_group', $arguments)) {
                $kpi->is_group = (bool) $arguments['is_group'];
            }

            if ($error = $this->validateAmpelArgs($arguments, $teamId, (int) $kpi->id)) {
                return ToolResult::error('VALIDATION_ERROR', $error);
            }
            if (array_key_exists('target_value', $arguments)) {
                $kpi->target_value = $arguments['target_value'] !== null ? (float) $arguments['target_value'] : null;
            }
            if (array_key_exists('target_kpi_id', $arguments)) {
                $kpi->target_kpi_id = $arguments['target_kpi_id'] !== null ? (int) $arguments['target_kpi_id'] : null;
            }
            if (array_key_exists('target_direction', $arguments) && $arguments['target_direction'] !== null) {
                $kpi->target_direction = $arguments['target_direction'];
            }
            if (array_key_exists('green_pct', $arguments)) {
                $kpi->green_pct = $arguments['green_pct'] !== null ? (int) $arguments['green_pct'] : null;
            }
            if (array_key_exists('yellow_pct', $arguments)) {
                $kpi->yellow_pct = $arguments['yellow_pct'] !== null ? (int) $arguments['yellow_pct'] : null;
            }

            // A value KPI must keep a usable definition; a group needs none.
            if (!$kpi->is_group) {
                $def = $kpi->definition ?? [];
                $hasAgg = !empty($def['aggregations'] ?? []) || !empty($def['aggregation'] ?? []);
                if (empty($def['streams'] ?? []) || !$hasAgg) {
                    return ToolResult::error('VALIDATION_ERROR', 'Eine Nicht-Gruppe braucht eine gültige definition (streams + aggregations). Sende definition mit oder setze is_group=true.');
                }
            }

            $definitionChanged = false;
            if (array_key_exists('definition', $arguments)) {
                $definition = $arguments['definition'];
                if (!is_array($definition)) {
                    return ToolResult::error('VALIDATION_ERROR', 'definition muss ein Objekt sein.');
                }
                $validator = app(KpiDefinitionValidator::class);
                if ($error = $validator->validate($definition, $teamId)) {
                    return ToolResult::error('VALIDATION_ERROR', $error);
                }
                $kpi->definition = $definition;
                $kpi->cached_value = null;
                $kpi->cached_comparison_value = null;
                $kpi->cached_at = null;
                $kpi->status = 'active';
                $kpi->last_error = null;
                $definitionChanged = true;
            }

            $kpi->save();

            return ToolResult::success([
                'id'                 => $kpi->id,
                'name'               => $kpi->name,
                'parent_kpi_id'      => $kpi->parent_kpi_id !== null ? (int) $kpi->parent_kpi_id : null,
                'is_group'           => (bool) $kpi->is_group,
                'display_range'      => $kpi->display_range,
                'status'             => $kpi->status,
                'team_id'            => $kpi->team_id,
                'definition_changed' => $definitionChanged,
                'message'            => $definitionChanged
                    ? 'KPI aktualisiert. Cache wurde geleert — nutze "datawarehouse.kpis.execute" zum Neuberechnen.'
                    : 'KPI aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des KPI: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'kpis', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
