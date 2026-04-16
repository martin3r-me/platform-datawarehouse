<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            if (!Schema::hasColumn('datawarehouse_streams', 'connection_id')) {
                $table->foreignId('connection_id')
                    ->nullable()
                    ->after('source_type')
                    ->constrained('datawarehouse_connections')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'endpoint_key')) {
                // Provider endpoint, e.g. 'contacts', 'invoices'.
                $table->string('endpoint_key')->nullable()->after('connection_id');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'pull_config')) {
                // Free-form per-endpoint config (filters, extra params, …).
                $table->json('pull_config')->nullable()->after('endpoint_key');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'pull_schedule')) {
                // Cron expression or human-friendly interval key ('hourly', 'daily', …).
                $table->string('pull_schedule')->nullable()->after('pull_config');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'pull_mode')) {
                $table->enum('pull_mode', ['full', 'incremental'])->nullable()->after('pull_schedule');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'incremental_field')) {
                $table->string('incremental_field')->nullable()->after('pull_mode');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'last_cursor')) {
                // JSON cursor returned by the provider on the last run.
                $table->json('last_cursor')->nullable()->after('incremental_field');
            }

            if (!Schema::hasColumn('datawarehouse_streams', 'last_pull_at')) {
                $table->timestamp('last_pull_at')->nullable()->after('last_cursor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            foreach (['last_pull_at', 'last_cursor', 'incremental_field', 'pull_mode', 'pull_schedule', 'pull_config', 'endpoint_key'] as $col) {
                if (Schema::hasColumn('datawarehouse_streams', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('datawarehouse_streams', 'connection_id')) {
                $table->dropConstrainedForeignId('connection_id');
            }
        });
    }
};
