<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datawarehouse_schema_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stream_id')->constrained('datawarehouse_streams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version');
            $table->enum('operation', ['create_table', 'add_column', 'modify_column', 'drop_column', 'drop_table']);
            $table->string('column_name')->nullable();
            $table->json('old_definition')->nullable();
            $table->json('new_definition')->nullable();
            $table->text('sql_executed')->nullable();
            $table->enum('status', ['success', 'error', 'rolled_back']);
            $table->timestamps();

            $table->index('stream_id');
            $table->index(['stream_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datawarehouse_schema_migrations');
    }
};
