<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            if (!Schema::hasColumn('datawarehouse_streams', 'sync_strategy')) {
                // append: every row new (event log)
                // current: upsert by natural_key, mirror of source
                // snapshot: each run stored with timestamp, no update
                // scd2:    versioned history with valid_from/valid_to
                $table->enum('sync_strategy', ['append', 'current', 'snapshot', 'scd2'])
                    ->default('append')
                    ->after('mode');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'natural_key')) {
                // Column name (in the dynamic table) that holds the external/source ID.
                // Required for 'current' and 'scd2'.
                $table->string('natural_key')->nullable()->after('sync_strategy');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'change_detection')) {
                // If true, writer computes _row_hash and skips unchanged rows.
                $table->boolean('change_detection')->default(true)->after('natural_key');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'soft_delete')) {
                // If true and strategy is current/scd2: rows missing in a full sync
                // get _deleted_at set (only meaningful with full, non-incremental runs).
                $table->boolean('soft_delete')->default(false)->after('change_detection');
            }
        });

        // Backfill sync_strategy from legacy 'mode' so existing webhook streams keep working.
        //   mode=append   → sync_strategy=append
        //   mode=upsert   → sync_strategy=current   + natural_key ← upsert_key
        //   mode=snapshot → sync_strategy=snapshot
        DB::table('datawarehouse_streams')
            ->where('mode', 'append')
            ->whereNull('sync_strategy')
            ->update(['sync_strategy' => 'append']);

        DB::table('datawarehouse_streams')
            ->where('mode', 'snapshot')
            ->whereNull('sync_strategy')
            ->update(['sync_strategy' => 'snapshot']);

        DB::table('datawarehouse_streams')
            ->where('mode', 'upsert')
            ->whereNull('sync_strategy')
            ->update([
                'sync_strategy' => 'current',
                'natural_key'   => DB::raw('upsert_key'),
            ]);
    }

    public function down(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            foreach (['soft_delete', 'change_detection', 'natural_key', 'sync_strategy'] as $col) {
                if (Schema::hasColumn('datawarehouse_streams', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
