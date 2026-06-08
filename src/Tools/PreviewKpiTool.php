<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Services\KpiDefinitionValidator;
use Platform\Datawarehouse\Services\KpiQueryBuilder;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Ad-hoc dry-run of a KPI definition. Validates the definition against the
 * same whitelists as CreateKpiTool, computes the value via KpiQueryBuilder,
 * and returns it WITHOUT persisting a KPI or touching any cache.
 *
 * This is the read-only counterpart to datawarehouse.kpis.execute (which
 * requires a saved kpi_id). Use it to test a definition — e.g. a typ-filter —
 * before committing it as a real KPI.
 */
class PreviewKpiTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    public function getName(): string
    {
        return 'datawarehouse.kpis.preview';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/kpis/preview - Berechnet eine KPI-Definition AD HOC und read-only (es wird KEIN KPI angelegt und KEIN Cache geschrieben). ERFORDERLICH: definition (gleiche Struktur/Whitelists wie "datawarehouse.kpis.POST": streams[], aggregations[], optional filters[], calendar_filters{}, snapshot_mode). Optional: range (einer aus den erlaubten Date-Ranges; nur wirksam wenn die Definition eine calendar_filters.date_column hat). Ideal um eine Definition — z. B. mit Filter typ="Rechnung" — vor dem Anlegen zu testen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'    => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
                'definition' => ['type' => 'object', 'description' => 'KPI-Definition (ERFORDERLICH). Wie bei "datawarehouse.kpis.POST".'],
                'range'      => [
                    'type' => 'string',
                    'enum' => ['current_month', 'current_quarter', 'current_year', 'current_week', 'last_7_days', 'last_30_days', 'last_90_days', 'last_12_months', 'previous_month', 'previous_quarter', 'previous_year', 'year_to_date'],
                    'description' => 'Optional: expliziter Date-Range. Wirkt nur, wenn die Definition eine calendar_filters.date_column hat.',
                ],
            ],
            'required' => ['definition'],
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

            $definition = $arguments['definition'] ?? null;
            if (!is_array($definition)) {
                return ToolResult::error('VALIDATION_ERROR', 'definition muss ein Objekt sein.');
            }

            $validator = app(KpiDefinitionValidator::class);
            if ($error = $validator->validate($definition, $teamId)) {
                return ToolResult::error('VALIDATION_ERROR', 'KPI-Definition ungültig: ' . $error);
            }

            $range = $arguments['range'] ?? null;
            if ($range !== null && !array_key_exists($range, KpiQueryBuilder::DATE_RANGE_MAP)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger range. Erlaubt: ' . implode(', ', array_keys(KpiQueryBuilder::DATE_RANGE_MAP)) . '.');
            }

            // Transient KPI — never saved. KpiQueryBuilder only reads
            // ->definition and ->team_id, so this computes without side effects.
            $kpi = new DatawarehouseKpi();
            $kpi->team_id = $teamId;
            $kpi->definition = $definition;

            $builder = app(KpiQueryBuilder::class);

            try {
                $value = ($range !== null)
                    ? $builder->executeForRange($kpi, $range)
                    : $builder->execute($kpi);
            } catch (\InvalidArgumentException $e) {
                return ToolResult::error('VALIDATION_ERROR', 'KPI-Definition ungültig: ' . $e->getMessage());
            }

            return ToolResult::success([
                'range'   => $range,
                'value'   => $value,
                'cached'  => false,
                'team_id' => $teamId,
                'message' => 'Ad-hoc-Berechnung (read-only) — kein KPI angelegt, kein Cache geschrieben.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Berechnen der KPI-Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'kpis', 'preview', 'dry-run'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
