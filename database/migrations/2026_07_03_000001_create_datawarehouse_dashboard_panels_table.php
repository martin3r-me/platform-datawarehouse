<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable dashboard panels: an additive layer on top of the KPI-tile pivot.
 * Each panel has a type (see config datawarehouse.dashboard_panels), an optional
 * title and a JSON config (which KPI(s) + options). Panels render above the KPI
 * grid and can be built for any KPI/stream — configurable via LLM tool and UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_dashboard_panels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dashboard_id')->index();
            $table->string('type', 50);
            $table->string('title')->nullable();
            $table->json('config')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('dashboard_id')->references('id')->on('datawarehouse_dashboards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_dashboard_panels');
    }
};
