<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dashboard may render a registered custom view instead of the KPI-tile grid.
 * NULL = normal KPI dashboard; otherwise a key into config('datawarehouse.dashboard_views')
 * (e.g. 'rkv') that maps to a data service + a blade partial. Generic so future
 * computed/forecast views (not just RKV) can live as dashboards under /dashboards/{id}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_dashboards', function (Blueprint $table) {
            $table->string('view_type', 50)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_dashboards', function (Blueprint $table) {
            $table->dropColumn('view_type');
        });
    }
};
