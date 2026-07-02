<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-team configuration for the RKV Rückvergütung (JRV) forecast: the JRV
 * staffeln (Event Rent + eventura), the eventura WKZ steps, the growth factor,
 * prior-year (2025) monthly reference values, and the stream/column mapping.
 * One row per team; the full parameter set lives in a single JSON `config`
 * column so it can be edited via LLM tool and rendered in the UI. Seeded from
 * the RKV_Tracker_2026 defaults on first access (see DatawarehouseRkvConfig).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_rkv_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('config');
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_rkv_configs');
    }
};
