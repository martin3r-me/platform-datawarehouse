<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_stream_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();

            // The column that holds the foreign value.
            $table->unsignedBigInteger('source_stream_id');
            $table->string('source_column', 64);

            // The stream & column it points to (the "lookup" side).
            $table->unsignedBigInteger('target_stream_id');
            $table->string('target_column', 64);

            // Human-readable name, e.g. "Verantwortlicher", "Kunde".
            $table->string('label')->nullable();

            // Future-proof: belongs_to (n:1 default), has_many, etc.
            $table->string('relation_type', 30)->default('belongs_to');

            $table->timestamps();

            $table->foreign('source_stream_id')
                ->references('id')->on('datawarehouse_streams')
                ->cascadeOnDelete();

            $table->foreign('target_stream_id')
                ->references('id')->on('datawarehouse_streams')
                ->cascadeOnDelete();

            // Prevent duplicate relations for the same column pair.
            $table->unique(['source_stream_id', 'source_column', 'target_stream_id'], 'dw_rel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_stream_relations');
    }
};
