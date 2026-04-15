<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained('datawarehouse_streams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'processing', 'success', 'error', 'partial']);
            $table->enum('mode', ['snapshot', 'append', 'upsert']);
            $table->integer('rows_received')->default(0);
            $table->integer('rows_imported')->default(0);
            $table->integer('rows_skipped')->default(0);
            $table->json('error_log')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index('stream_id');
            $table->index(['stream_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_imports');
    }
};
