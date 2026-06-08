<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional self-referencing parent_kpi_id so KPIs can form a
 * drill-down hierarchy (e.g. "RR" → "2500", "2525"). Purely additive and
 * nullable: existing KPIs stay top-level (parent_kpi_id = null). Deleting a
 * parent detaches its children (nullOnDelete) rather than cascading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_kpi_id')->nullable()->after('position');
            $table->index('parent_kpi_id');

            $table->foreign('parent_kpi_id')
                ->references('id')->on('datawarehouse_kpis')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropForeign(['parent_kpi_id']);
            $table->dropIndex(['parent_kpi_id']);
            $table->dropColumn('parent_kpi_id');
        });
    }
};
