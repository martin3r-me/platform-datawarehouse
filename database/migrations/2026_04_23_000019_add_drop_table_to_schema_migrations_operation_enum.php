<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE datawarehouse_schema_migrations MODIFY COLUMN `operation` ENUM('create_table', 'add_column', 'modify_column', 'drop_column', 'drop_table') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE datawarehouse_schema_migrations MODIFY COLUMN `operation` ENUM('create_table', 'add_column', 'modify_column', 'drop_column') NOT NULL");
    }
};
