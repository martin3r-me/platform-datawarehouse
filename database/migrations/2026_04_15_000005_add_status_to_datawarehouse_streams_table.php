<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->enum('status', ['onboarding', 'active', 'paused', 'archived'])
                ->default('onboarding')
                ->after('schema_version');
        });

        // Migrate existing data: is_active=true → active, is_active=false → paused
        DB::table('datawarehouse_streams')
            ->where('is_active', true)
            ->update(['status' => 'active']);

        DB::table('datawarehouse_streams')
            ->where('is_active', false)
            ->update(['status' => 'paused']);

        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropColumn('is_active');
            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('schema_version');
        });

        DB::table('datawarehouse_streams')
            ->where('status', 'active')
            ->update(['is_active' => true]);

        DB::table('datawarehouse_streams')
            ->whereIn('status', ['onboarding', 'paused', 'archived'])
            ->update(['is_active' => false]);

        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'status']);
            $table->dropColumn('status');
            $table->index(['team_id', 'is_active']);
        });
    }
};
