<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_streams', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->enum('source_type', ['manual', 'webhook_post', 'pull_get']);
            $table->string('frequency')->nullable();
            $table->enum('mode', ['snapshot', 'append', 'upsert']);
            $table->string('upsert_key')->nullable();
            $table->string('endpoint_token', 64);
            $table->string('pull_url')->nullable();
            $table->json('pull_headers')->nullable();
            $table->string('table_name')->nullable();
            $table->boolean('table_created')->default(false);
            $table->integer('schema_version')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->enum('last_status', ['success', 'error', 'partial'])->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index('endpoint_token');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_streams');
    }
};
