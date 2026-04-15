# LLM Guide - Datawarehouse Module

## Architektur

```
DatawarehouseServiceProvider
├── register()          # Config laden
└── boot()
    ├── PlatformCore::registerModule()
    ├── ModuleRouter::group()     # Web-Routes (auth)
    ├── ModuleRouter::apiGroup()  # API-Routes (no auth, token-based)
    ├── loadMigrationsFrom()
    ├── loadViewsFrom()
    └── registerLivewireComponents()
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
