<?php

namespace Platform\Datawarehouse\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\StreamSchemaService;

/**
 * Retrofit legacy stream tables (created before the system-column expansion)
 * with the new meta columns so ImportWriter strategies keep working.
 *
 * Safe to run multiple times: each ALTER only adds a column when absent.
 */
class UpgradeSchemaCommand extends Command
{
    protected $signature = 'datawarehouse:upgrade-schema {stream? : Optional stream ID (otherwise all)}';

    protected $description = 'Add missing system columns to legacy stream tables.';

    public function handle(): int
    {
        $query = DatawarehouseStream::query()
            ->whereNotNull('table_name')
            ->where('table_created', true);

        if ($streamId = $this->argument('stream')) {
            $query->where('id', $streamId);
        }

        $streams = $query->get();
        if ($streams->isEmpty()) {
            $this->info('No streams to upgrade.');
            return self::SUCCESS;
        }

        $upgraded = 0;
        foreach ($streams as $stream) {
            $table = $stream->table_name;
            if (!$table || !Schema::hasTable($table)) {
                $this->warn("skip {$stream->id} ({$stream->name}): table '{$table}' missing");
                continue;
            }

            $added = $this->upgradeTable($stream, $table);
            if ($added > 0) {
                $upgraded++;
                $this->line("upgraded {$stream->id} ({$stream->name}): +{$added} columns");
            } else {
                $this->line("ok {$stream->id} ({$stream->name}): already current");
            }
        }

        $this->info("done. {$upgraded} of {$streams->count()} stream(s) upgraded.");
        return self::SUCCESS;
    }

    protected function upgradeTable(DatawarehouseStream $stream, string $table): int
    {
        $strategy = $stream->sync_strategy ?? 'append';
        $added = 0;

        $idx = fn (string $col) => StreamSchemaService::safeIndexName($table, $col);

        $want = [
            // Uniform meta columns (all strategies).
            '_external_id'    => fn (Blueprint $t) => $t->string('_external_id')->nullable()->index($idx('_external_id')),
            '_synced_at'      => fn (Blueprint $t) => $t->timestamp('_synced_at')->nullable()->index($idx('_synced_at')),
            '_source_run_id'  => fn (Blueprint $t) => $t->unsignedBigInteger('_source_run_id')->nullable()->index($idx('_source_run_id')),
            '_row_hash'       => fn (Blueprint $t) => $t->char('_row_hash', 64)->nullable(),
        ];

        if ($strategy === 'snapshot') {
            $want['_snapshot_at'] = fn (Blueprint $t) => $t->timestamp('_snapshot_at')->nullable()->index($idx('_snapshot_at'));
        }
        if ($strategy === 'scd2') {
            $want['_valid_from'] = fn (Blueprint $t) => $t->timestamp('_valid_from')->nullable()->index($idx('_valid_from'));
            $want['_valid_to']   = fn (Blueprint $t) => $t->timestamp('_valid_to')->nullable();
            $want['_is_current'] = fn (Blueprint $t) => $t->boolean('_is_current')->default(true)->index($idx('_is_current'));
        }
        if (in_array($strategy, ['current', 'scd2'], true)) {
            $want['_deleted_at'] = fn (Blueprint $t) => $t->timestamp('_deleted_at')->nullable()->index($idx('_deleted_at'));
        }

        foreach ($want as $column => $factory) {
            if (Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($factory) {
                $factory($t);
            });
            $added++;
        }

        if ($added > 0) {
            $stream->increment('schema_version');
        }

        return $added;
    }
}
