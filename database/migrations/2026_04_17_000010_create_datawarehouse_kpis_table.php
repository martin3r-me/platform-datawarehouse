<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_kpis', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id');

            $table->string('name');
            $table->string('icon', 50)->default('chart-bar');
            $table->string('variant', 20)->default('primary');
            $table->string('unit', 20)->nullable();
            $table->string('format', 20)->default('number');
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->json('definition');

            $table->decimal('cached_value', 20, 4)->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->unsignedInteger('cache_ttl_seconds')->default(300);

            $table->string('status', 20)->default('draft');
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')
                ->references('id')->on('teams')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_kpis');
    }
};
