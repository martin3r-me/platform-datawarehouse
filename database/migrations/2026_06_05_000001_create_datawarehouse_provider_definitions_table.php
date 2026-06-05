<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('datawarehouse_provider_definitions')) {
            return;
        }

        Schema::create('datawarehouse_provider_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Globally-unique generated provider key (e.g. "cfg_a1b2c3d4"). Referenced
            // from a connection's provider_key just like a code provider's key.
            $table->string('key')->unique();

            $table->string('label');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);

            // Base URL of the third-party service. Empty → falls back to config('app.url').
            $table->string('base_url')->nullable();

            // How requests authenticate: none | bearer | header | query.
            $table->enum('auth_type', ['none', 'bearer', 'header', 'query'])->default('none');

            // Non-secret auth wiring: { header_name?, query_param? }. The secret value
            // itself lives (encrypted) on the connection's credentials, never here.
            $table->json('auth_config')->nullable();

            // Declarative endpoint definitions (path, query, pagination, incremental, …).
            $table->json('endpoints')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_provider_definitions');
    }
};
