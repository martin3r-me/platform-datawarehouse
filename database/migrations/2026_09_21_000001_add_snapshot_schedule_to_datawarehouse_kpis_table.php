<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a per-KPI snapshot cadence. Until now the scheduler recomputed every
 * active KPI every minute, which does not scale with the number of KPIs —
 * the same gating the streams already have via `pull_schedule`.
 *
 * Default 'auto': recompute when the underlying streams delivered new data,
 * on a calendar day change for rolling date ranges, and at the latest every
 * 24h. Existing KPIs adopt it, which lowers their load without changing what
 * a dashboard shows. See KpiSnapshotSchedule for the other values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->string('snapshot_schedule', 40)->default('auto')->after('display_range');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropColumn('snapshot_schedule');
        });
    }
};
