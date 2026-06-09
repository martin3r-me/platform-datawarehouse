<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds configurable RAG (Ampel) thresholds to KPIs. A KPI gets a target —
 * either a fixed value or a reference to another KPI (e.g. the Plan KPI) —
 * a direction (higher/lower is better) and two achievement-% thresholds
 * (green/yellow). All nullable & additive: without a target there is no Ampel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->decimal('target_value', 20, 4)->nullable()->after('display_range');
            $table->unsignedBigInteger('target_kpi_id')->nullable()->after('target_value');
            $table->string('target_direction', 20)->default('higher_better')->after('target_kpi_id');
            $table->unsignedSmallInteger('green_pct')->nullable()->after('target_direction');
            $table->unsignedSmallInteger('yellow_pct')->nullable()->after('green_pct');

            $table->foreign('target_kpi_id')
                ->references('id')->on('datawarehouse_kpis')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropForeign(['target_kpi_id']);
            $table->dropColumn(['target_value', 'target_kpi_id', 'target_direction', 'green_pct', 'yellow_pct']);
        });
    }
};
