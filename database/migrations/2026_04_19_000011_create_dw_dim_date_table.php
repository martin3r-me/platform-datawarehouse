<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dw_dim_date', function (Blueprint $table) {
            $table->date('date_key')->primary();
            $table->string('weekday', 12);
            $table->tinyInteger('weekday_num')->unsigned();
            $table->boolean('is_weekend')->default(false);
            $table->tinyInteger('kw')->unsigned();
            $table->tinyInteger('month')->unsigned();
            $table->tinyInteger('quarter')->unsigned();
            $table->smallInteger('year');
            $table->boolean('is_feiertag')->default(false);
            $table->string('feiertag_name', 60)->nullable();
            $table->boolean('is_schulferien')->default(false);
            $table->string('schulferien_name', 60)->nullable();
            $table->string('bundesland', 5)->default('NW');

            $table->index('year');
            $table->index('month');
            $table->index('kw');
            $table->index('is_feiertag');
            $table->index('is_weekend');
            $table->index('bundesland');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dw_dim_date');
    }
};
