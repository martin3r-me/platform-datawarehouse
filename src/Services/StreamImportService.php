<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseImport;

class StreamImportService
{
    public function __construct(
        protected StreamSchemaService $schemaService,
    ) {}

    /**
     * Main entry point: parse raw data and import into the dynamic table.
     */
    public function importFromPayload(DatawarehouseStream $stream, array|string $rawData, ?int $userId = null): DatawarehouseImport
    {
        $startTime = microtime(true);

        // Parse JSON string if needed
        if (is_string($rawData)) {
            $rawData = json_decode($rawData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFailedImport($stream, $userId, 'Invalid JSON: ' . json_last_error_msg(), $startTime);
            }
        }

        // Normalize to array of rows
        $rows = $this->normalizeRows($rawData);

        if (empty($rows)) {
            return $this->createFailedImport($stream, $userId, 'No rows found in payload.', $startTime);
        }

        // Ensure table exists
        if (!$stream->table_created) {
            try {
                $this->schemaService->createTable($stream, $userId);
                $stream->refresh();
            } catch (\Throwable $e) {
                return $this->createFailedImport($stream, $userId, 'Table creation failed: ' . $e->getMessage(), $startTime);
            }
        }

        // Create import record
        $import = DatawarehouseImport::create([
            'stream_id'     => $stream->id,
            'user_id'       => $userId,
            'status'        => 'processing',
            'mode'          => $stream->mode,
            'rows_received' => count($rows),
        ]);

        try {
            $result = match ($stream->mode) {
                'snapshot' => $this->importSnapshot($stream, $rows, $import),
                'append'   => $this->importAppend($stream, $rows, $import),
                'upsert'   => $this->importUpsert($stream, $rows, $import),
            };

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $import->update([
                'status'        => $result['errors'] ? 'partial' : 'success',
                'rows_imported' => $result['imported'],
                'rows_skipped'  => $result['skipped'],
                'error_log'     => $result['errors'] ?: null,
                'duration_ms'   => $durationMs,
            ]);

            $stream->update([
                'last_run_at' => now(),
                'last_status' => $result['errors'] ? 'partial' : 'success',
            ]);

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $import->update([
                'status'     => 'error',
                'error_log'  => [['message' => $e->getMessage()]],
                'duration_ms' => $durationMs,
            ]);

            $stream->update([
                'last_run_at' => now(),
                'last_status' => 'error',
            ]);

            Log::error("Datawarehouse import failed for stream {$stream->id}: {$e->getMessage()}");
        }

        return $import;
    }

    protected function importSnapshot(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $tableName = $stream->getDynamicTableName();

        DB::table($tableName)->truncate();

        return $this->insertRows($stream, $rows, $import);
    }

    protected function importAppend(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        return $this->insertRows($stream, $rows, $import);
    }

    protected function importUpsert(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        if (empty($stream->upsert_key)) {
            throw new \RuntimeException("Upsert mode requires an upsert_key to be set.");
        }

        $tableName = $stream->getDynamicTableName();
        $columns = $stream->columns()->where('is_active', true)->get();
        $columnMap = $columns->keyBy('source_key');
        $upsertKey = $stream->upsert_key;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $mapped = $this->mapRow($row, $columnMap);
                $mapped['import_id'] = $import->id;
                $mapped['imported_at'] = now();

                $keyColumn = $columnMap->get($upsertKey)?->column_name ?? $upsertKey;

                if (!isset($mapped[$keyColumn])) {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => "Missing upsert key '{$upsertKey}'"];
                    continue;
                }

                // Columns to update (all mapped columns except the upsert key)
                $updateColumns = array_keys($mapped);

                DB::table($tableName)->upsert(
                    [$mapped],
                    [$keyColumn],
                    $updateColumns,
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['row' => $index, 'message' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    protected function insertRows(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $tableName = $stream->getDynamicTableName();
        $columns = $stream->columns()->where('is_active', true)->get();
        $columnMap = $columns->keyBy('source_key');

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Insert in chunks for performance
        $chunks = array_chunk($rows, 500);

        foreach ($chunks as $chunk) {
            $insertData = [];

            foreach ($chunk as $index => $row) {
                try {
                    $mapped = $this->mapRow($row, $columnMap);
                    $mapped['import_id'] = $import->id;
                    $mapped['imported_at'] = now();
                    $mapped['created_at'] = now();
                    $mapped['updated_at'] = now();
                    $insertData[] = $mapped;
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => $e->getMessage()];
                }
            }

            if (!empty($insertData)) {
                DB::table($tableName)->insert($insertData);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Map a raw data row to database columns using column definitions.
     */
    protected function mapRow(array $row, $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $sourceKey => $column) {
            $value = data_get($row, $sourceKey);
            $value = $column->applyTransform($value);
            $mapped[$column->column_name] = $value;
        }

        return $mapped;
    }

    /**
     * Normalize payload to array of rows.
     * Supports: array of objects, single object (wraps in array), or nested data key.
     */
    protected function normalizeRows(array $data): array
    {
        // If it's already a list of rows
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }

        // Check common wrapper keys
        foreach (['data', 'rows', 'items', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return isset($data[$key][0]) && is_array($data[$key][0])
                    ? $data[$key]
                    : [$data[$key]];
            }
        }

        // Single object → wrap in array
        if (!empty($data) && !isset($data[0])) {
            return [$data];
        }

        return $data;
    }

    protected function createFailedImport(DatawarehouseStream $stream, ?int $userId, string $message, float $startTime): DatawarehouseImport
    {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $import = DatawarehouseImport::create([
            'stream_id'   => $stream->id,
            'user_id'     => $userId,
            'status'      => 'error',
            'mode'        => $stream->mode,
            'error_log'   => [['message' => $message]],
            'duration_ms' => $durationMs,
        ]);

        $stream->update([
            'last_run_at' => now(),
            'last_status' => 'error',
        ]);

        return $import;
    }
}
