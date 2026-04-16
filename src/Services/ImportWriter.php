<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Models\DatawarehouseStream;

/**
 * Unified writer that persists mapped rows into a stream's dynamic table
 * using one of four sync strategies:
 *
 *   append    → every row inserted, no dedup
 *   current   → upsert by natural_key, one row per external entity
 *   snapshot  → every row inserted with _snapshot_at (immutable history)
 *   scd2      → versioned history: hash-diff opens new valid_from row, closes old
 *
 * The writer is source-agnostic. Webhook, pull, and manual import streams all
 * produce the same shape of output and metadata here.
 */
class ImportWriter
{
    /** In-memory cache of table → column-list. */
    protected array $columnCache = [];

    /**
     * Persist the given already-mapped rows into the stream's dynamic table.
     *
     * Rows are expected as associative arrays keyed by real DB column names
     * (i.e. after StreamImportService::mapRow has run), without any of the
     * system columns — those are filled in here.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported:int, skipped:int, errors:array, unchanged:int}
     */
    public function write(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $strategy = $stream->sync_strategy ?? 'append';

        return match ($strategy) {
            'current'  => $this->writeCurrent($stream, $rows, $import),
            'snapshot' => $this->writeSnapshot($stream, $rows, $import),
            'scd2'     => $this->writeScd2($stream, $rows, $import),
            default    => $this->writeAppend($stream, $rows, $import),
        };
    }

    // -----------------------------------------------------------------
    // Strategies
    // -----------------------------------------------------------------

    /**
     * Append: insert every row as-is. No dedup, no keys.
     */
    protected function writeAppend(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $now = Carbon::now();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            $batch = [];
            foreach ($chunk as $index => $row) {
                try {
                    $batch[] = $this->withSystemCols($row, $stream, $import, $now, [
                        '_external_id' => $this->extractExternalId($row, $stream),
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => $e->getMessage()];
                }
            }
            if (!empty($batch)) {
                DB::table($stream->getDynamicTableName())->insert($batch);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'unchanged' => 0];
    }

    /**
     * Current: upsert by natural_key, mirror-of-source.
     * Unchanged rows (same _row_hash) are skipped when change_detection is on.
     * Missing rows are NOT handled here — soft-delete is a separate sweep step.
     */
    protected function writeCurrent(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $keyCol = $this->requireNaturalKey($stream);
        $table = $stream->getDynamicTableName();
        $now = Carbon::now();
        $detect = (bool) ($stream->change_detection ?? true)
            && $this->tableHasColumn($table, '_external_id')
            && $this->tableHasColumn($table, '_row_hash');

        $imported = 0;
        $skipped = 0;
        $unchanged = 0;
        $errors = [];

        // Preload hashes for change detection (single query, keyed by external id).
        $existingHashes = [];
        if ($detect) {
            $ids = array_values(array_filter(array_map(
                fn ($r) => $this->extractExternalId($r, $stream),
                $rows
            ), fn ($v) => $v !== null && $v !== ''));

            if (!empty($ids)) {
                $existingHashes = DB::table($table)
                    ->whereIn('_external_id', array_unique($ids))
                    ->pluck('_row_hash', '_external_id')
                    ->toArray();
            }
        }

        foreach ($rows as $index => $row) {
            try {
                $externalId = $this->extractExternalId($row, $stream);
                if ($externalId === null || $externalId === '') {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => "Missing natural_key '{$keyCol}'"];
                    continue;
                }

                $hash = $this->computeRowHash($row);
                if ($detect && isset($existingHashes[$externalId]) && $existingHashes[$externalId] === $hash) {
                    $unchanged++;
                    continue;
                }

                $payload = $this->withSystemCols($row, $stream, $import, $now, [
                    '_external_id' => $externalId,
                    '_row_hash'    => $hash,
                    '_deleted_at'  => null,
                ]);

                if ($this->tableHasColumn($table, '_external_id')) {
                    DB::table($table)->upsert(
                        [$payload],
                        ['_external_id'],
                        array_keys($payload),
                    );
                } else {
                    // Legacy table without system columns: fall back to append.
                    DB::table($table)->insert($payload);
                }

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['row' => $index, 'message' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'unchanged' => $unchanged];
    }

    /**
     * Snapshot: every row inserted with _snapshot_at = now().
     * The table grows with every run. Natural key is optional.
     */
    protected function writeSnapshot(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $now = Carbon::now();
        $snapshotAt = $now->copy();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            $batch = [];
            foreach ($chunk as $index => $row) {
                try {
                    $batch[] = $this->withSystemCols($row, $stream, $import, $now, [
                        '_external_id' => $this->extractExternalId($row, $stream),
                        '_snapshot_at' => $snapshotAt,
                        '_row_hash'    => $this->computeRowHash($row),
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => $e->getMessage()];
                }
            }
            if (!empty($batch)) {
                DB::table($stream->getDynamicTableName())->insert($batch);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'unchanged' => 0];
    }

    /**
     * SCD2: hash-diff against current row.
     *   - Unchanged  → skip
     *   - Changed    → close prior (_valid_to=now, _is_current=false) + insert new
     *   - New entity → insert new (_valid_from=now, _is_current=true)
     */
    protected function writeScd2(DatawarehouseStream $stream, array $rows, DatawarehouseImport $import): array
    {
        $keyCol = $this->requireNaturalKey($stream);
        $table = $stream->getDynamicTableName();
        $now = Carbon::now();

        // SCD2 requires a schema-level upgrade: the meta columns must exist.
        // Fail loud rather than silently losing history.
        foreach (['_external_id', '_row_hash', '_is_current', '_valid_from', '_valid_to'] as $col) {
            if (!$this->tableHasColumn($table, $col)) {
                throw new \RuntimeException(
                    "Table '{$table}' is missing system column '{$col}' required for scd2. ".
                    "Run 'php artisan datawarehouse:upgrade-schema {$stream->id}' first."
                );
            }
        }

        $imported = 0;
        $skipped = 0;
        $unchanged = 0;
        $errors = [];

        // Load current rows keyed by external id (single query).
        $ids = array_values(array_filter(array_map(
            fn ($r) => $this->extractExternalId($r, $stream),
            $rows
        ), fn ($v) => $v !== null && $v !== ''));

        $currentRows = [];
        if (!empty($ids)) {
            $currentRows = DB::table($table)
                ->whereIn('_external_id', array_unique($ids))
                ->where('_is_current', true)
                ->get(['id', '_external_id', '_row_hash'])
                ->keyBy('_external_id')
                ->toArray();
        }

        foreach ($rows as $index => $row) {
            try {
                $externalId = $this->extractExternalId($row, $stream);
                if ($externalId === null || $externalId === '') {
                    $skipped++;
                    $errors[] = ['row' => $index, 'message' => "Missing natural_key '{$keyCol}'"];
                    continue;
                }

                $hash = $this->computeRowHash($row);
                $existing = $currentRows[$externalId] ?? null;

                if ($existing && $existing->_row_hash === $hash) {
                    $unchanged++;
                    continue;
                }

                DB::transaction(function () use ($table, $existing, $row, $stream, $import, $now, $externalId, $hash) {
                    if ($existing) {
                        DB::table($table)
                            ->where('id', $existing->id)
                            ->update([
                                '_valid_to'   => $now,
                                '_is_current' => false,
                                'updated_at'  => $now,
                            ]);
                    }

                    $payload = $this->withSystemCols($row, $stream, $import, $now, [
                        '_external_id' => $externalId,
                        '_row_hash'    => $hash,
                        '_valid_from'  => $now,
                        '_valid_to'    => null,
                        '_is_current'  => true,
                        '_deleted_at'  => null,
                    ]);

                    DB::table($table)->insert($payload);
                });

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = ['row' => $index, 'message' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'unchanged' => $unchanged];
    }

    // -----------------------------------------------------------------
    // Soft-delete sweep (only meaningful after a *full* run)
    // -----------------------------------------------------------------

    /**
     * Mark rows as deleted whose _external_id did not appear in the full-run
     * external-id set. Must be called explicitly by the caller; automatic
     * dispatch is not possible because incremental runs must never trigger this.
     *
     * @param  array<int|string>  $seenExternalIds
     */
    public function softDeleteMissing(DatawarehouseStream $stream, array $seenExternalIds): int
    {
        if (!$stream->soft_delete || !in_array($stream->sync_strategy, ['current', 'scd2'], true)) {
            return 0;
        }

        $table = $stream->getDynamicTableName();

        // Silently skip if the legacy table lacks the meta columns; soft-
        // delete simply does nothing rather than crashing an otherwise
        // successful pull.
        if (!$this->tableHasColumn($table, '_deleted_at') || !$this->tableHasColumn($table, '_external_id')) {
            return 0;
        }

        $now = Carbon::now();

        $query = DB::table($table)->whereNull('_deleted_at');
        if ($stream->isScd2Strategy() && $this->tableHasColumn($table, '_is_current')) {
            $query->where('_is_current', true);
        }
        if (!empty($seenExternalIds)) {
            $query->whereNotIn('_external_id', array_unique(array_filter($seenExternalIds, fn ($v) => $v !== null && $v !== '')));
        }

        $update = ['_deleted_at' => $now];
        if ($this->tableHasColumn($table, 'updated_at')) {
            $update['updated_at'] = $now;
        }

        return $query->update($update);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Merge user columns with system columns + legacy bookkeeping fields,
     * then filter the result to columns that actually exist on the target
     * table. This makes the writer forward-compatible with legacy tables
     * created before the system-column expansion.
     */
    protected function withSystemCols(array $row, DatawarehouseStream $stream, DatawarehouseImport $import, Carbon $now, array $overrides = []): array
    {
        $base = [
            'import_id'       => $import->id,        // legacy
            'imported_at'     => $now,               // legacy
            '_synced_at'      => $now,
            '_source_run_id'  => $import->id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        $merged = array_merge($base, $row, $overrides);

        return $this->filterToTableColumns($stream->getDynamicTableName(), $merged);
    }

    /**
     * Intersect a row with the actual column set of the target table so we
     * never try to insert into a column that does not exist. Also useful for
     * upsert column lists.
     */
    protected function filterToTableColumns(string $table, array $row): array
    {
        $cols = $this->getTableColumns($table);
        if (empty($cols)) {
            return $row;
        }
        return array_intersect_key($row, array_flip($cols));
    }

    protected function getTableColumns(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            $this->columnCache[$table] = Schema::hasTable($table)
                ? Schema::getColumnListing($table)
                : [];
        }
        return $this->columnCache[$table];
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->getTableColumns($table), true);
    }

    /**
     * Extract the value of the natural-key column from a mapped row.
     * Returns null when the stream has no natural_key configured.
     */
    protected function extractExternalId(array $row, DatawarehouseStream $stream): ?string
    {
        $key = $stream->natural_key ?: $stream->upsert_key;
        if (!$key) {
            return null;
        }

        // natural_key may be stored as source_key → resolve to column_name via columns.
        if (array_key_exists($key, $row)) {
            return $row[$key] === null ? null : (string) $row[$key];
        }

        // Fallback: try via column mapping.
        $column = $stream->columns()->where('source_key', $key)->first();
        if ($column && array_key_exists($column->column_name, $row)) {
            return $row[$column->column_name] === null ? null : (string) $row[$column->column_name];
        }

        return null;
    }

    protected function requireNaturalKey(DatawarehouseStream $stream): string
    {
        $key = $stream->natural_key ?: $stream->upsert_key;
        if (!$key) {
            throw new \RuntimeException(
                "Stream '{$stream->name}' uses sync_strategy '{$stream->sync_strategy}' which requires a natural_key."
            );
        }
        return $key;
    }

    /**
     * Deterministic hash over the mapped user columns only (system cols excluded).
     */
    protected function computeRowHash(array $row): string
    {
        $filtered = [];
        foreach ($row as $k => $v) {
            if (str_starts_with((string) $k, '_')) continue;
            if (in_array($k, ['id', 'import_id', 'imported_at', 'created_at', 'updated_at'], true)) continue;
            $filtered[$k] = $v;
        }
        ksort($filtered);
        return hash('sha256', json_encode($filtered, JSON_UNESCAPED_UNICODE));
    }
}
