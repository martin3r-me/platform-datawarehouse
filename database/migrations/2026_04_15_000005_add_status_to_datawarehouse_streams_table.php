<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add status column
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->enum('status', ['onboarding', 'active', 'paused', 'archived'])
                ->default('onboarding')
                ->after('schema_version');
        });

        // 2. Migrate existing data: is_active=true → active, is_active=false → paused
        DB::table('datawarehouse_streams')
            ->where('is_active', true)
            ->update(['status' => 'active']);

        DB::table('datawarehouse_streams')
            ->where('is_active', false)
            ->update(['status' => 'paused']);

        // 3. Add new composite index BEFORE dropping the old one,
        //    so the FK on team_id keeps a backing index.
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->index(['team_id', 'status']);
        });

        // 4. Drop old index + column
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        // 1. Re-add is_active column
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('schema_version');
        });

        // 2. Restore data
        DB::table('datawarehouse_streams')
            ->where('status', 'active')
            ->update(['is_active' => true]);

        DB::table('datawarehouse_streams')
            ->whereIn('status', ['onboarding', 'paused', 'archived'])
            ->update(['is_active' => false]);

        // 3. Re-add old composite index before dropping the new one
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->index(['team_id', 'is_active']);
        });

        // 4. Drop new index + status column
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
