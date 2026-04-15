<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_stream_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained('datawarehouse_streams')->cascadeOnDelete();
            $table->string('source_key');
            $table->string('column_name');
            $table->string('label');
            $table->enum('data_type', ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'text', 'json']);
            $table->integer('precision')->nullable();
            $table->integer('scale')->nullable();
            $table->string('unit')->nullable();
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_nullable')->default(true);
            $table->string('default_value')->nullable();
            $table->string('transform')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('stream_id');
            $table->unique(['stream_id', 'column_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_stream_columns');
    }
};
