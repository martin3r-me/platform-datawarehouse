# LLM Guide - Datawarehouse Module

## Architektur

```
DatawarehouseServiceProvider
├── register()          # Config laden, ProviderRegistry-Singleton
└── boot()
    ├── PlatformCore::registerModule()
    ├── ModuleRouter::group()     # Web-Routes (auth)
    ├── ModuleRouter::apiGroup()  # API-Routes (no auth, token-based)
    ├── loadMigrationsFrom()
    ├── loadViewsFrom()
    ├── registerLivewireComponents()
    ├── registerPullProviders()   # Lexoffice, Land, Sprache, Feiertage, ...
    └── registerTools()           # MCP-Tools im ToolRegistry (datawarehouse.*)
```

## Datenmodell

```
datawarehouse_streams (Datenstrom-Definitionen)
  ├── datawarehouse_stream_columns (Spalten-Schema)
  ├── datawarehouse_imports (Import-Log)
  ├── datawarehouse_schema_migrations (Schema-Audit)
  └── dw_{id}_{slug} (dynamische Datentabelle)
```

## Services

### StreamSchemaService
- `createTable(stream)` - Erstellt dynamische Tabelle aus Spalten-Definitionen
- `addColumn(stream, column)` - Fügt Spalte hinzu
- `modifyColumn(stream, column, oldDef)` - Ändert Spalte
- `dropColumn(stream, columnName, oldDef)` - Entfernt Spalte
- Alle Operationen loggen in `datawarehouse_schema_migrations`

### StreamImportService
- `importFromPayload(stream, rawData)` - Haupteinstiegspunkt
- Unterstützt: snapshot, append, upsert Modi
- Normalisiert Payload (array of objects, single object, wrapped in data/rows/items)
- Transformationen pro Spalte (trim, url_decode, cast_german_decimal, etc.)
- Erstellt Import-Record mit Statistiken

## API-Endpoint

```
POST /api/datawarehouse/ingest/{endpoint_token}
Content-Type: application/json
```

- Token-basierte Auth (kein Session/API-Key nötig)
- Akzeptiert JSON-Body (Array von Objekten oder einzelnes Objekt)
- Antwort enthält import_id, status, rows_received/imported/skipped

## Dynamische Tabellen

- Name: `dw_{stream_id}_{slug}`
- Auto-Spalten: id, import_id, imported_at, created_at, updated_at
- Benutzerdefinierte Spalten aus `datawarehouse_stream_columns`
- Typen: string, integer, decimal, boolean, date, datetime, text, json

## MCP / AI-Tools

Alle Modul-Tools liegen unter `src/Tools/*Tool.php` und werden vom Service-Provider
im `Platform\Core\Tools\ToolRegistry` registriert (siehe `registerTools()`).
Einheitliches Namensschema `datawarehouse.{resource}.{verb}` — entspricht dem
Modul-Key, damit der `McpSessionToolManager` sie per Prefix-Filter findet.
Einstiegspunkt für LLMs ist `datawarehouse.overview.GET`: liefert eine Karte
der Konzepte (Streams/Stream-Columns/Connections/Imports/KPIs/Dashboards),
der Stream-Typen und der Sync-Strategien — vor jeder anderen Aktion zuerst lesen.

KPI-Schreibpfade laufen durch `Services/KpiDefinitionValidator`, der die gleichen
Whitelists wie der `KpiQueryBuilder` einsetzt (ALLOWED_AGGREGATIONS / ALLOWED_OPERATORS /
COLUMN_REGEX / ALIAS_REGEX + DB-Existenzcheck). Damit können MCP-Clients keine
SQL-Fragmente einschmuggeln, die der Query-Builder später durchwinkt.
