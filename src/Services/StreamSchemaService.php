<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseSchemaMigration;

class StreamSchemaService
{
    /**
     * Column names that collide with system columns added by createTable().
     * User-defined columns with these names are auto-renamed via
     * sanitizeColumnName() during onboarding.
     */
    public const RESERVED_COLUMNS = [
        'id',
        'import_id',
        'imported_at',
        'created_at',
        'updated_at',
        '_external_id',
        '_synced_at',
        '_source_run_id',
        '_row_hash',
        '_deleted_at',
        '_snapshot_at',
        '_valid_from',
        '_valid_to',
        '_is_current',
    ];

    /**
     * Return a column name safe to use for user-defined columns.
     *
     * Normalizes arbitrary source keys (incl. German umlauts, dots, slashes,
     * whitespace) into a valid MySQL identifier:
     *   - transliterate to ASCII (ä→a, ß→ss, …)
     *   - lowercase
     *   - collapse any run of non-[a-z0-9_] to a single underscore
     *   - strip leading/trailing underscores
     *   - prefix with "col_" if the result is empty or starts with a digit
     *   - truncate to 64 chars (MySQL identifier limit)
     *   - resolve collisions with RESERVED_COLUMNS by prefixing "ext_"
     */
    public static function sanitizeColumnName(string $name): string
    {
        // 1. Transliterate umlauts / diacritics to ASCII.
        $ascii = Str::ascii($name);

        // 2. Lowercase + replace every non-identifier-char with underscore.
        $clean = strtolower($ascii);
        $clean = preg_replace('/[^a-z0-9_]+/', '_', $clean);

        // 3. Collapse multiple underscores, trim edges.
        $clean = preg_replace('/_+/', '_', $clean);
        $clean = trim($clean, '_');

        // 4. Guard: empty / leading digit.
        if ($clean === '') {
            $clean = 'col';
        }
        if (ctype_digit($clean[0])) {
            $clean = 'col_' . $clean;
        }

        // 5. MySQL identifier limit (64); reserve 8 chars for potential "ext_" prefix.
        if (strlen($clean) > 56) {
            $clean = substr($clean, 0, 56);
            $clean = rtrim($clean, '_');
        }

        // 6. Resolve reserved-name collisions.
        if (!in_array($clean, self::RESERVED_COLUMNS, true)) {
            return $clean;
        }

        $candidate = 'ext_' . $clean;
        $suffix = 2;
        while (in_array($candidate, self::RESERVED_COLUMNS, true)) {
            $candidate = 'ext_' . $clean . '_' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    /**
     * Create the dynamic table for a stream based on its column definitions.
     *
     * System columns added uniformly for all streams:
     *   id, import_id, imported_at        (legacy, kept for BC)
     *   _external_id, _synced_at,
     *   _source_run_id, _row_hash         (all strategies)
     *
     * Strategy-specific:
     *   snapshot → _snapshot_at
     *   scd2     → _valid_from, _valid_to, _is_current
     *   current/scd2 → _deleted_at
     */
    public function createTable(DatawarehouseStream $stream, ?int $userId = null): void
    {
        $tableName = $stream->getDynamicTableName();
        $columns = $stream->columns()->where('is_active', true)->orderBy('position')->get();

        if ($columns->isEmpty()) {
            throw new \RuntimeException("Stream '{$stream->name}' has no columns defined.");
        }

        // Defense-in-depth: reject collisions with reserved column names
        // with a clear error instead of letting MySQL raise Duplicate column.
        foreach ($columns as $col) {
            if (in_array($col->column_name, self::RESERVED_COLUMNS, true)) {
                throw new \RuntimeException(
                    "Column name '{$col->column_name}' (from source '{$col->source_key}') ".
                    "collides with a reserved system column. Rename the column before activation."
                );
            }
        }

        $strategy = $stream->sync_strategy ?? 'append';
        $sql = null;

        try {
            Schema::create($tableName, function (Blueprint $table) use ($columns, $strategy) {
                $table->id();

                // Legacy bookkeeping (kept so existing dashboards keep working).
                $table->unsignedBigInteger('import_id')->nullable()->index();
                $table->timestamp('imported_at')->nullable();

                // Uniform system columns (all strategies).
                $table->string('_external_id')->nullable()->index();
                $table->timestamp('_synced_at')->nullable()->index();
                $table->unsignedBigInteger('_source_run_id')->nullable()->index();
                $table->char('_row_hash', 64)->nullable();

                // Strategy-specific system columns.
                if ($strategy === 'snapshot') {
                    $table->timestamp('_snapshot_at')->nullable()->index();
                }
                if ($strategy === 'scd2') {
                    $table->timestamp('_valid_from')->nullable()->index();
                    $table->timestamp('_valid_to')->nullable();
                    $table->boolean('_is_current')->default(true)->index();
                }
                if (in_array($strategy, ['current', 'scd2'], true)) {
                    $table->timestamp('_deleted_at')->nullable()->index();
                }

                foreach ($columns as $col) {
                    $colDef = $col->toLaravelColumnType();
                    $column = $table->{$colDef['type']}($col->column_name, ...$colDef['args']);

                    if ($col->is_nullable) {
                        $column->nullable();
                    }

                    if ($col->default_value !== null) {
                        $column->default($col->default_value);
                    }

                    if ($col->is_indexed) {
                        $table->index($col->column_name);
                    }
                }

                $table->timestamps();
            });

            $sql = "CREATE TABLE {$tableName} (...)"; // simplified for audit log

            $stream->update([
                'table_name'     => $tableName,
                'table_created'  => true,
                'schema_version' => $stream->schema_version + 1,
            ]);

            $this->logSchemaMigration($stream, $userId, 'create_table', null, null, [
                'columns' => $columns->pluck('column_name')->toArray(),
            ], $sql, 'success');

        } catch (\Throwable $e) {
            $this->logSchemaMigration($stream, $userId, 'create_table', null, null, null, $sql, 'error');
            throw $e;
        }
    }

    /**
     * Add a column to an existing dynamic table.
     */
    public function addColumn(DatawarehouseStream $stream, DatawarehouseStreamColumn $column, ?int $userId = null): void
    {
        $tableName = $stream->getDynamicTableName();

        if (!Schema::hasTable($tableName)) {
            throw new \RuntimeException("Table '{$tableName}' does not exist.");
        }

        $colDef = $column->toLaravelColumnType();
        $sql = null;

        try {
            Schema::table($tableName, function (Blueprint $table) use ($column, $colDef) {
                $col = $table->{$colDef['type']}($column->column_name, ...$colDef['args']);

                if ($column->is_nullable) {
                    $col->nullable();
                }

                if ($column->default_value !== null) {
                    $col->default($column->default_value);
                }

                if ($column->is_indexed) {
                    $table->index($column->column_name);
                }
            });

            $sql = "ALTER TABLE {$tableName} ADD COLUMN {$column->column_name}";

            $stream->increment('schema_version');

            $this->logSchemaMigration($stream, $userId, 'add_column', $column->column_name, null, [
                'data_type'  => $column->data_type,
                'is_nullable' => $column->is_nullable,
                'is_indexed' => $column->is_indexed,
            ], $sql, 'success');

        } catch (\Throwable $e) {
            $this->logSchemaMigration($stream, $userId, 'add_column', $column->column_name, null, null, $sql, 'error');
            throw $e;
        }
    }

    /**
     * Modify an existing column in the dynamic table.
     */
    public function modifyColumn(DatawarehouseStream $stream, DatawarehouseStreamColumn $column, array $oldDefinition, ?int $userId = null): void
    {
        $tableName = $stream->getDynamicTableName();

        if (!Schema::hasTable($tableName)) {
            throw new \RuntimeException("Table '{$tableName}' does not exist.");
        }

        $colDef = $column->toLaravelColumnType();
        $sql = null;

        try {
            Schema::table($tableName, function (Blueprint $table) use ($column, $colDef) {
                $col = $table->{$colDef['type']}($column->column_name, ...$colDef['args'])->change();

                if ($column->is_nullable) {
                    $col->nullable();
                }

                if ($column->default_value !== null) {
                    $col->default($column->default_value);
                }
            });

            $sql = "ALTER TABLE {$tableName} MODIFY COLUMN {$column->column_name}";

            $stream->increment('schema_version');

            $this->logSchemaMigration($stream, $userId, 'modify_column', $column->column_name, $oldDefinition, [
                'data_type'  => $column->data_type,
                'is_nullable' => $column->is_nullable,
            ], $sql, 'success');

        } catch (\Throwable $e) {
            $this->logSchemaMigration($stream, $userId, 'modify_column', $column->column_name, $oldDefinition, null, $sql, 'error');
            throw $e;
        }
    }

    /**
     * Drop a column from the dynamic table.
     */
    public function dropColumn(DatawarehouseStream $stream, string $columnName, array $oldDefinition = [], ?int $userId = null): void
    {
        $tableName = $stream->getDynamicTableName();

        if (!Schema::hasTable($tableName)) {
            throw new \RuntimeException("Table '{$tableName}' does not exist.");
        }

        $sql = null;

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName);
            });

            $sql = "ALTER TABLE {$tableName} DROP COLUMN {$columnName}";

            $stream->increment('schema_version');

            $this->logSchemaMigration($stream, $userId, 'drop_column', $columnName, $oldDefinition, null, $sql, 'success');

        } catch (\Throwable $e) {
            $this->logSchemaMigration($stream, $userId, 'drop_column', $columnName, $oldDefinition, null, $sql, 'error');
            throw $e;
        }
    }

    protected function logSchemaMigration(
        DatawarehouseStream $stream,
        ?int $userId,
        string $operation,
        ?string $columnName,
        ?array $oldDefinition,
        ?array $newDefinition,
        ?string $sql,
        string $status
    ): void {
        DatawarehouseSchemaMigration::create([
            'stream_id'      => $stream->id,
            'user_id'        => $userId,
            'version'        => $stream->schema_version,
            'operation'      => $operation,
            'column_name'    => $columnName,
            'old_definition' => $oldDefinition,
            'new_definition' => $newDefinition,
            'sql_executed'   => $sql,
            'status'         => $status,
        ]);
    }
}
