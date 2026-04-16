<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('datawarehouse_connections')) {
            return;
        }

        Schema::create('datawarehouse_connections', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Provider key as registered in the ProviderRegistry (e.g. 'lexoffice').
            $table->string('provider_key');

            // User-friendly name like "Lexoffice Produktiv".
            $table->string('name');
            $table->text('description')->nullable();

            // Encrypted credentials (api_key, oauth tokens, …). Cast by the model.
            $table->text('credentials')->nullable();

            // Non-secret meta (account id, base url overrides, …).
            $table->json('meta')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('last_check_at')->nullable();
            $table->enum('last_check_status', ['success', 'error'])->nullable();
            $table->text('last_check_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'provider_key']);
            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_connections');
    }
};
