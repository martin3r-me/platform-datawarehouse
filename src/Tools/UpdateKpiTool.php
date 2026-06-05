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

class UpdateKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

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
