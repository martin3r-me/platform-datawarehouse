<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\Log;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseImport;

class StreamImportService
{
    public function __construct(
        protected StreamSchemaService $schemaService,
        protected ImportWriter $writer,
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
            // Map raw rows to DB columns once, then hand off to the strategy writer.
            $columnMap = $stream->columns()->where('is_active', true)->get()->keyBy('source_key');
            $mappedRows = [];
            $mapErrors = [];
            foreach ($rows as $index => $row) {
                try {
                    $mappedRows[] = $this->mapRow($row, $columnMap);
                } catch (\Throwable $e) {
                    $mapErrors[] = ['row' => $index, 'message' => $e->getMessage()];
                }
            }

            $result = $this->writer->write($stream, $mappedRows, $import);
            $result['skipped'] += count($mapErrors);
            $result['errors'] = array_merge($mapErrors, $result['errors'] ?? []);

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

    /**
     * Map a raw data row to database columns using column definitions.
     */
    protected function mapRow(array $row, $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $sourceKey => $column) {
            $value = data_get($row, $sourceKey);
            $value = $column->applyTransform($value);

            // Nested structures (arrays / stdClass) can't be bound to scalar
            // columns, so we serialize them to JSON. For data_type=json this
            // produces a valid JSON literal; for string/text columns it
            // prevents "Array to string conversion" errors.
            if ($value !== null && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

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
