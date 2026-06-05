<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class DwhOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'datawarehouse.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/overview - Zeigt Übersicht über das Datawarehouse-Modul: Konzepte (Streams, Stream-Columns, Connections, Imports, KPIs, Dashboards), Stream-Typen (webhook/pull/manual), Sync-Strategien (append/current/snapshot/scd2), KPI-Aufbau und alle verfügbaren Tools.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'datawarehouse',
                'scope' => [
                    'team_scoped' => true,
                    'team_id_source' => 'ToolContext.team bzw. team_id Parameter',
                ],
                'concepts' => [
                    'streams' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseStream',
                        'table' => 'datawarehouse_streams',
                        'key_fields' => ['id', 'uuid', 'name', 'slug', 'source_type', 'connection_id', 'endpoint_key', 'sync_strategy', 'natural_key', 'pull_mode', 'incremental_field', 'pull_schedule', 'status', 'table_created', 'last_run_at', 'last_status', 'team_id'],
                        'note' => 'Ein Stream ist eine Datenquelle. Jeder Stream hat einen dynamisch angelegten Table (dw_{id}_{slug}) für die importierten Rows. Lebenszyklus: onboarding → active → (paused) → archived.',
                        'source_types' => [
                            'webhook_post' => 'Externer Sender pusht JSON per HTTP POST an einen Token-geschützten Endpoint.',
                            'pull_get'     => 'Datawarehouse holt Daten zyklisch von einem externen Provider (Lexoffice, Land, Sprache, …).',
                            'manual'       => 'Manueller Import per Datei-Upload oder API.',
                        ],
                        'sync_strategies' => [
                            'append'   => 'Alle Rows werden angehängt (Historie). Eignet sich für Events/Logs.',
                            'current'  => 'Nur der aktuelle Zustand pro natural_key wird gehalten (Upsert). natural_key erforderlich.',
                            'snapshot' => 'Voller Datensatz wird pro Run als _snapshot_at-Generation gespeichert.',
                            'scd2'     => 'Slowly Changing Dimension Typ 2: jede Änderung erzeugt eine neue Version mit _valid_from/_valid_to und _is_current. natural_key erforderlich.',
                        ],
                        'status_values' => ['onboarding', 'active', 'paused', 'archived'],
                        'pull_schedule_examples' => ['hourly', 'every_15_minutes', 'daily', 'weekly', 'cron-Expressions'],
                    ],
                    'stream_columns' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseStreamColumn',
                        'table' => 'datawarehouse_stream_columns',
                        'key_fields' => ['id', 'stream_id', 'source_key', 'column_name', 'label', 'data_type', 'precision', 'scale', 'unit', 'is_indexed', 'is_nullable', 'transform', 'position', 'is_active'],
                        'note' => 'Definiert eine Spalte des dynamisch erzeugten Tables. source_key = Pfad im Payload (z.B. "data.invoice.total"), column_name = sanitierter MySQL-Identifier.',
                        'data_types' => ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json'],
                        'transforms' => ['trim', 'url_decode', 'cast_german_decimal', 'lowercase', 'uppercase', 'strip_tags', 'to_integer', 'to_boolean'],
                    ],
                    'connections' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseConnection',
                        'table' => 'datawarehouse_connections',
                        'key_fields' => ['id', 'uuid', 'team_id', 'provider_key', 'name', 'description', 'is_active', 'last_check_at', 'last_check_status', 'last_check_error'],
                        'note' => 'Speichert Credentials (verschlüsselt) für einen Pull-Provider. Wird von Pull-Streams referenziert. Credentials werden NIE im Response zurückgegeben.',
                    ],
                    'providers' => [
                        'concept' => 'Codeseitige Singletons (keine DB-Einträge), registriert in der ProviderRegistry. Jeder Provider exposed authFields (Login-Felder) + endpoints (callable Resources).',
                        'note' => 'Anlegen einer Connection erfordert provider_key + Werte für die authFields. Stream kann dann auf einen endpoint_key des Providers zeigen.',
                    ],
                    'provider_definitions' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseProviderDefinition',
                        'table' => 'datawarehouse_provider_definitions',
                        'key_fields' => ['id', 'key', 'label', 'base_url', 'auth_type', 'auth_config', 'endpoints', 'is_active', 'team_id'],
                        'concept' => 'Konfigurierbare HTTP-Pull-Provider OHNE Code — per UI/LLM angelegt und team-scoped. Die ProviderRegistry verpackt sie zur Laufzeit in einen GenericHttpProvider, sodass sie wie Code-Provider als provider_key einer Connection nutzbar sind.',
                        'note' => 'Workflow: provider_definitions.POST (Endpunkte mit path/query/pagination/data_path/incremental definieren) → provider_definitions.test (Beispielzeilen prüfen) → Connection mit dem generierten key → pull_get-Stream + Spalten. auth_type: none|bearer|header|query.',
                        'tools' => ['datawarehouse.provider_definitions.GET', 'datawarehouse.provider_definition.GET', 'datawarehouse.provider_definitions.POST', 'datawarehouse.provider_definitions.PUT', 'datawarehouse.provider_definitions.DELETE', 'datawarehouse.provider_definitions.test'],
                    ],
                    'imports' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseImport',
                        'table' => 'datawarehouse_imports',
                        'key_fields' => ['id', 'stream_id', 'status', 'mode', 'rows_received', 'rows_imported', 'rows_skipped', 'error_log', 'duration_ms'],
                        'note' => 'Read-only Log eines Import-Laufs. status = processing/success/partial/error. errors enthalten { page, row, message }.',
                    ],
                    'kpis' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseKpi',
                        'table' => 'datawarehouse_kpis',
                        'key_fields' => ['id', 'uuid', 'name', 'description', 'icon', 'variant', 'unit', 'format', 'decimals', 'position', 'definition', 'cached_value', 'cached_at', 'display_range', 'cached_comparison_value', 'status', 'last_error'],
                        'note' => 'Ein KPI ist eine validierte SQL-Aggregation über einen oder mehrere Streams. Die definition (JSON) wird vor dem Speichern gegen Whitelists geprüft (ALLOWED_AGGREGATIONS, ALLOWED_OPERATORS, COLUMN_REGEX, ALIAS_REGEX) — eigene SQL-Fragmente sind nicht möglich.',
                        'definition_shape' => [
                            'streams' => '[{stream_id, alias: "s0|s1|..."}, ...] - Basis-Stream zuerst, dann optional Joins',
                            'aggregations' => '[{function: SUM|COUNT|AVG|MIN|MAX, column, stream_alias, operator?: +|-|*|/}, ...] - mindestens 1, mehrere werden zu einem Ausdruck verknüpft',
                            'aggregation' => '(legacy) einzelnes Aggregations-Objekt - wird automatisch in aggregations[] gewrappt',
                            'filters' => '[{stream_alias, column, operator: =|!=|<|>|<=|>=|LIKE, value}, ...] - optional',
                            'calendar_filters' => '{date_column, date_stream_alias?: s0, date_range?: current_month|..., conditions?: [{column: weekday|kw|month|..., operator, value}]} - optional, joint dw_dim_date',
                            'snapshot_mode' => '"latest" (default) | "all" - Steuerung für snapshot-Streams',
                        ],
                        'date_ranges' => [
                            'current_month', 'current_quarter', 'current_year', 'current_week',
                            'last_30_days', 'last_90_days', 'last_12_months',
                            'previous_month', 'previous_quarter', 'previous_year',
                            'year_to_date',
                        ],
                        'security_note' => 'KPI-Definitionen werden bei POST/PUT durch KpiDefinitionValidator geprüft. Eigene Spaltennamen, Aliasse und Operatoren außerhalb der Whitelists werden abgelehnt.',
                    ],
                    'dashboards' => [
                        'model' => 'Platform\\Datawarehouse\\Models\\DatawarehouseDashboard',
                        'table' => 'datawarehouse_dashboards',
                        'pivot_table' => 'datawarehouse_dashboard_kpis',
                        'key_fields' => ['id', 'uuid', 'name', 'description', 'icon', 'position'],
                        'note' => 'Container für KPIs. Reihenfolge der KPIs wird im Pivot (position) gespeichert.',
                    ],
                ],
                'relationships' => [
                    'stream_has_columns' => 'DatawarehouseStream → DatawarehouseStreamColumn',
                    'stream_has_imports' => 'DatawarehouseStream → DatawarehouseImport',
                    'stream_has_connection' => 'DatawarehouseStream → DatawarehouseConnection (nur Pull-Streams)',
                    'kpi_references_streams' => 'DatawarehouseKpi.definition.streams[] → DatawarehouseStream',
                    'dashboard_has_kpis' => 'DatawarehouseDashboard ⇄ DatawarehouseKpi (via datawarehouse_dashboard_kpis, mit position)',
                ],
                'related_tools' => [
                    'streams' => [
                        'list'     => 'datawarehouse.streams.GET',
                        'get'      => 'datawarehouse.stream.GET',
                        'create'   => 'datawarehouse.streams.POST',
                        'update'   => 'datawarehouse.streams.PUT',
                        'delete'   => 'datawarehouse.streams.DELETE',
                        'activate' => 'datawarehouse.streams.activate',
                        'pause'    => 'datawarehouse.streams.pause',
                        'resume'   => 'datawarehouse.streams.resume',
                        'archive'  => 'datawarehouse.streams.archive',
                    ],
                    'stream_columns' => [
                        'list'      => 'datawarehouse.stream_columns.GET',
                        'create'    => 'datawarehouse.stream_columns.POST',
                        'update'    => 'datawarehouse.stream_columns.PUT',
                        'delete'    => 'datawarehouse.stream_columns.DELETE',
                        'bulk_post' => 'datawarehouse.stream_columns.BULK_POST',
                    ],
                    'connections' => [
                        'list'   => 'datawarehouse.connections.GET',
                        'get'    => 'datawarehouse.connection.GET',
                        'create' => 'datawarehouse.connections.POST',
                        'update' => 'datawarehouse.connections.PUT',
                        'delete' => 'datawarehouse.connections.DELETE',
                        'test'   => 'datawarehouse.connections.test',
                    ],
                    'providers' => [
                        'list' => 'datawarehouse.providers.GET',
                        'get'  => 'datawarehouse.provider.GET',
                    ],
                    'kpis' => [
                        'list'              => 'datawarehouse.kpis.GET',
                        'get'               => 'datawarehouse.kpi.GET',
                        'create'            => 'datawarehouse.kpis.POST',
                        'update'            => 'datawarehouse.kpis.PUT',
                        'delete'            => 'datawarehouse.kpis.DELETE',
                        'execute'           => 'datawarehouse.kpis.execute',
                        'executeAllRanges'  => 'datawarehouse.kpis.executeAllRanges',
                    ],
                    'dashboards' => [
                        'list'       => 'datawarehouse.dashboards.GET',
                        'get'        => 'datawarehouse.dashboard.GET',
                        'create'     => 'datawarehouse.dashboards.POST',
                        'update'     => 'datawarehouse.dashboards.PUT',
                        'delete'     => 'datawarehouse.dashboards.DELETE',
                        'attachKpi'  => 'datawarehouse.dashboards.attachKpi',
                        'detachKpi'  => 'datawarehouse.dashboards.detachKpi',
                        'reorder'    => 'datawarehouse.dashboards.reorder',
                    ],
                    'imports' => [
                        'list' => 'datawarehouse.imports.GET',
                        'get'  => 'datawarehouse.import.GET',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Datawarehouse-Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['datawarehouse', 'overview', 'help'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
