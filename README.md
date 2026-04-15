# Datawarehouse Module

Systematisches Einsammeln, Speichern und Bereitstellen von Daten aus verschiedenen Quellen.

## Features

- **Datenströme (Streams)**: Konfigurierbare Datenquellen mit Schema-Definition
- **Dynamische Tabellen**: Automatisch erstellte Tabellen basierend auf Spaltendefinitionen
- **Webhook-Ingest**: POST-Endpoint mit Token-basierter Authentifizierung
- **Import-Modi**: Snapshot (Truncate+Insert), Append, Upsert
- **Schema-Audit**: Lückenlose Dokumentation aller Schema-Änderungen
- **Import-Log**: Statistiken zu jedem Import (Zeilen, Fehler, Dauer)

## Struktur

```
datawarehouse/
├── config/datawarehouse.php
├── database/migrations/
│   ├── ...create_datawarehouse_streams_table.php
│   ├── ...create_datawarehouse_stream_columns_table.php
│   ├── ...create_datawarehouse_imports_table.php
│   └── ...create_datawarehouse_schema_migrations_table.php
├── routes/
│   ├── web.php         # Dashboard (auth)
│   └── api.php         # Webhook-Ingest (token)
└── src/
    ├── DatawarehouseServiceProvider.php
    ├── Http/Controllers/IngestController.php
    ├── Livewire/Dashboard.php, Sidebar.php
    ├── Models/
    │   ├── DatawarehouseStream.php
    │   ├── DatawarehouseStreamColumn.php
    │   ├── DatawarehouseImport.php
    │   └── DatawarehouseSchemaMigration.php
    └── Services/
        ├── StreamSchemaService.php
        └── StreamImportService.php
```

## Webhook-Endpoint

```
POST /api/datawarehouse/ingest/{endpoint_token}
Content-Type: application/json

[
  {"field1": "value1", "field2": 42},
  {"field1": "value2", "field2": 99}
]
```

Response:
```json
{
  "import_id": 1,
  "status": "success",
  "rows_received": 2,
  "rows_imported": 2,
  "rows_skipped": 0,
  "duration_ms": 45,
  "errors": null
}
```

## Import-Modi

- **snapshot**: Löscht alle Daten der Tabelle und fügt neue ein (Truncate + Insert)
- **append**: Fügt neue Zeilen hinzu
- **upsert**: Insert oder Update basierend auf `upsert_key`

## Dynamische Tabellen

Tabellennamen: `dw_{stream_id}_{slug}`

Jede dynamische Tabelle hat automatisch:
- `id` (PK)
- `import_id` (Referenz zum Import)
- `imported_at` (Zeitstempel)
- Konfigurierte Spalten aus `datawarehouse_stream_columns`
- `created_at`, `updated_at`
