<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\Log;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\ProviderRegistry;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Drives one pull-stream fetch cycle:
 *   connection → provider → endpoint → paginate → write → bookkeeping.
 *
 * The service is idempotent per run: a single call to pull() creates one
 * DatawarehouseImport record and returns it, regardless of how many pages
 * the provider yields.
 */
class PullStreamService
{
    public function __construct(
        protected ProviderRegistry $registry,
        protected ImportWriter $writer,
        protected StreamSchemaService $schemaService,
    ) {}

    public function pull(DatawarehouseStream $stream, ?int $userId = null): DatawarehouseImport
    {
        $startTime = microtime(true);

        if (!$stream->isPull()) {
            return $this->fail($stream, $userId, 'Stream is not a pull stream.', $startTime);
        }
        if ($stream->isOnboarding()) {
            return $this->fail($stream, $userId, 'Stream is still in onboarding.', $startTime);
        }
        if (!$stream->connection_id || !$stream->endpoint_key) {
            return $this->fail($stream, $userId, 'Pull stream requires a connection and endpoint.', $startTime);
        }

        $connection = $stream->connection;
        if (!$connection || !$connection->is_active) {
            return $this->fail($stream, $userId, 'Connection missing or inactive.', $startTime);
        }

        if (!$this->registry->has($connection->provider_key)) {
            return $this->fail($stream, $userId, "Provider '{$connection->provider_key}' is not registered.", $startTime);
        }

        $provider = $this->registry->get($connection->provider_key);
        $endpoints = $provider->endpoints();
        if (!isset($endpoints[$stream->endpoint_key])) {
            return $this->fail($stream, $userId, "Endpoint '{$stream->endpoint_key}' not available on provider '{$connection->provider_key}'.", $startTime);
        }
        $endpoint = $endpoints[$stream->endpoint_key];

        // Ensure dynamic table exists (pull streams may be activated from a sample just like webhooks).
        if (!$stream->table_created) {
            try {
                $this->schemaService->createTable($stream, $userId);
                $stream->refresh();
            } catch (\Throwable $e) {
                return $this->fail($stream, $userId, 'Table creation failed: ' . $e->getMessage(), $startTime);
            }
        }

        $import = DatawarehouseImport::create([
            'stream_id'     => $stream->id,
            'user_id'       => $userId,
            'status'        => 'processing',
            'mode'          => $stream->mode,
            'rows_received' => 0,
        ]);

        $cursor = $stream->last_cursor;
        $incremental = $stream->pull_mode === 'incremental';
        $incrementalField = $stream->incremental_field ?: $endpoint->incrementalField;
        $since = $incremental && $stream->last_pull_at ? $stream->last_pull_at : null;

        $columnMap = $stream->columns()->where('is_active', true)->get()->keyBy('source_key');

        $totalReceived = 0;
        $totalImported = 0;
        $totalSkipped = 0;
        $totalUnchanged = 0;
        $allErrors = [];
        $seenExternalIds = [];
        $lastCursor = null;

        try {
            $pageNo = 0;
            do {
                $pageNo++;
                $context = new PullContext(
                    connection:       $connection,
                    stream:           $stream,
                    endpoint:         $endpoint,
                    cursor:           $cursor,
                    incremental:      $incremental && $incrementalField !== null,
                    incrementalField: $incrementalField,
                    since:            $since,
                );

                /** @var PullResult $result */
                $result = $provider->fetch($context);

                $totalReceived += $result->count();
                $seenExternalIds = array_merge($seenExternalIds, $result->seenExternalIds);

                // Map raw provider rows → DB columns, then hand to writer.
                $mapped = [];
                foreach ($result->rows as $index => $row) {
                    try {
                        $mapped[] = $this->mapRow($row, $columnMap);
                    } catch (\Throwable $e) {
                        $totalSkipped++;
                        $allErrors[] = ['page' => $pageNo, 'row' => $index, 'message' => $e->getMessage()];
                    }
                }

                if (!empty($mapped)) {
                    $writeResult = $this->writer->write($stream, $mapped, $import);
                    $totalImported += $writeResult['imported'] ?? 0;
                    $totalSkipped  += $writeResult['skipped']  ?? 0;
                    $totalUnchanged += $writeResult['unchanged'] ?? 0;
                    if (!empty($writeResult['errors'])) {
                        foreach ($writeResult['errors'] as $e) {
                            $e['page'] = $pageNo;
                            $allErrors[] = $e;
                        }
                    }
                }

                $cursor = $result->nextCursor;
                $lastCursor = $cursor;
            } while ($cursor !== null && $pageNo < 10000); // hard cap as safety net

            // Soft-delete sweep only makes sense for full runs.
            $deleted = 0;
            if (!$incremental && $stream->soft_delete) {
                $deleted = $this->writer->softDeleteMissing($stream, $seenExternalIds);
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $import->update([
                'status'        => $allErrors ? 'partial' : 'success',
                'rows_received' => $totalReceived,
                'rows_imported' => $totalImported,
                'rows_skipped'  => $totalSkipped,
                'error_log'     => $allErrors ?: null,
                'duration_ms'   => $durationMs,
            ]);

            $stream->update([
                'last_run_at'  => now(),
                'last_pull_at' => now(),
                'last_status'  => $allErrors ? 'partial' : 'success',
                // Only persist the last cursor on incremental runs; for full runs,
                // the next run should start from scratch.
                'last_cursor'  => $incremental ? $lastCursor : null,
            ]);

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $import->update([
                'status'      => 'error',
                'error_log'   => [['message' => $e->getMessage()]],
                'duration_ms' => $durationMs,
            ]);

            $stream->update([
                'last_run_at' => now(),
                'last_status' => 'error',
            ]);

            Log::error("Datawarehouse pull failed for stream {$stream->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }

        return $import;
    }

    /**
     * Fetch a single page from the provider and store the first row as a
     * sample payload on the stream. Intended for onboarding: the user gets
     * to see what the provider returns before the stream is activated.
     *
     * Bypasses the isOnboarding() guard used by pull() and does NOT write
     * to a dynamic table or create an import record.
     *
     * @return array<string, mixed>  The stored sample row (flattened).
     * @throws \RuntimeException on any configuration or provider failure.
     */
    public function fetchSample(DatawarehouseStream $stream): array
    {
        if (!$stream->isPull()) {
            throw new \RuntimeException('Stream is not a pull stream.');
        }
        if (!$stream->connection_id || !$stream->endpoint_key) {
            throw new \RuntimeException('Pull stream requires a connection and endpoint.');
        }

        $connection = $stream->connection;
        if (!$connection || !$connection->is_active) {
            throw new \RuntimeException('Connection missing or inactive.');
        }

        if (!$this->registry->has($connection->provider_key)) {
            throw new \RuntimeException("Provider '{$connection->provider_key}' is not registered.");
        }

        $provider  = $this->registry->get($connection->provider_key);
        $endpoints = $provider->endpoints();
        if (!isset($endpoints[$stream->endpoint_key])) {
            throw new \RuntimeException("Endpoint '{$stream->endpoint_key}' not available on provider '{$connection->provider_key}'.");
        }
        $endpoint = $endpoints[$stream->endpoint_key];

        $context = new PullContext(
            connection:       $connection,
            stream:           $stream,
            endpoint:         $endpoint,
            cursor:           null,
            incremental:      false,
            incrementalField: null,
            since:            null,
        );

        $result = $provider->fetch($context);

        if (empty($result->rows)) {
            throw new \RuntimeException('Provider returned no rows for the first page.');
        }

        $firstRow = $result->rows[0];

        // Persist the sample under metadata.sample_payload (same shape
        // StreamOnboarding already consumes from webhook-based samples).
        $metadata = $stream->metadata ?? [];
        $metadata['sample_payload'] = [$firstRow];
        $metadata['sample_fetched_at'] = now()->toIso8601String();
        $stream->update(['metadata' => $metadata]);

        return $firstRow;
    }

    /**
     * Map a raw provider row to DB columns using the stream's column definitions.
     * Unknown fields are ignored (provider may return more than the user wants).
     */
    protected function mapRow(array $row, $columnMap): array
    {
        $mapped = [];
        foreach ($columnMap as $sourceKey => $column) {
            $value = data_get($row, $sourceKey);
            $value = $column->applyTransform($value);

            // See StreamImportService::mapRow — nested structures are
            // JSON-serialized so they can be bound to scalar columns.
            if ($value !== null && (is_array($value) || is_object($value))) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $mapped[$column->column_name] = $value;
        }
        return $mapped;
    }

    protected function fail(DatawarehouseStream $stream, ?int $userId, string $message, float $startTime): DatawarehouseImport
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
