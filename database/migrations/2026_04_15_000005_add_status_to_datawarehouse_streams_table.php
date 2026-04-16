<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add status column (only if not already present)
        if (!Schema::hasColumn('datawarehouse_streams', 'status')) {
            Schema::table('datawarehouse_streams', function (Blueprint $table) {
                $table->enum('status', ['onboarding', 'active', 'paused', 'archived'])
                    ->default('onboarding')
                    ->after('schema_version');
            });
        }

        // 2. Migrate existing data (only if is_active still exists)
        if (Schema::hasColumn('datawarehouse_streams', 'is_active')) {
            DB::table('datawarehouse_streams')
                ->where('is_active', true)
                ->update(['status' => 'active']);

            DB::table('datawarehouse_streams')
                ->where('is_active', false)
                ->update(['status' => 'paused']);
        }

        // 3. Add new composite index BEFORE dropping the old one,
        //    so the FK on team_id keeps a backing index.
        if (!$this->indexExists('datawarehouse_streams', 'datawarehouse_streams_team_id_status_index')) {
            Schema::table('datawarehouse_streams', function (Blueprint $table) {
                $table->index(['team_id', 'status']);
            });
        }

        // 4. Drop old index + column (only if still present)
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            if ($this->indexExists('datawarehouse_streams', 'datawarehouse_streams_team_id_is_active_index')) {
                $table->dropIndex(['team_id', 'is_active']);
            }
            if (Schema::hasColumn('datawarehouse_streams', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }

    public function down(): void
    {
        // 1. Re-add is_active column
        if (!Schema::hasColumn('datawarehouse_streams', 'is_active')) {
            Schema::table('datawarehouse_streams', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('schema_version');
            });
        }

        // 2. Restore data
        if (Schema::hasColumn('datawarehouse_streams', 'status')) {
            DB::table('datawarehouse_streams')
                ->where('status', 'active')
                ->update(['is_active' => true]);

            DB::table('datawarehouse_streams')
                ->whereIn('status', ['onboarding', 'paused', 'archived'])
                ->update(['is_active' => false]);
        }

        // 3. Re-add old composite index before dropping the new one
        if (!$this->indexExists('datawarehouse_streams', 'datawarehouse_streams_team_id_is_active_index')) {
            Schema::table('datawarehouse_streams', function (Blueprint $table) {
                $table->index(['team_id', 'is_active']);
            });
        }

        // 4. Drop new index + status column
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            if ($this->indexExists('datawarehouse_streams', 'datawarehouse_streams_team_id_status_index')) {
                $table->dropIndex(['team_id', 'status']);
            }
            if (Schema::hasColumn('datawarehouse_streams', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(1) AS cnt FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $indexName]
        );

        return ($result[0]->cnt ?? 0) > 0;
    }
};
