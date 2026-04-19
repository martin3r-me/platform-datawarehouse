<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_id');
            $table->decimal('value', 20, 4)->nullable();
            $table->timestamp('calculated_at');
            $table->string('trigger', 20);

            $table->index(['kpi_id', 'calculated_at']);

            $table->foreign('kpi_id')
                ->references('id')->on('datawarehouse_kpis')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_kpi_snapshots');
    }
};
