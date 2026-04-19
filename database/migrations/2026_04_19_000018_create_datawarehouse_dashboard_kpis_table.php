<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_dashboard_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dashboard_id');
            $table->unsignedBigInteger('kpi_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['dashboard_id', 'kpi_id']);

            $table->foreign('dashboard_id')
                ->references('id')->on('datawarehouse_dashboards')
                ->cascadeOnDelete();

            $table->foreign('kpi_id')
                ->references('id')->on('datawarehouse_kpis')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_dashboard_kpis');
    }
};
