<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an is_group flag so a KPI can act as a pure navigation folder
 * (e.g. "Rohertrag") that only groups child KPIs and carries no own
 * aggregation/value. Additive and defaulting to false — existing KPIs
 * stay normal value KPIs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('parent_kpi_id');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
    }
};
