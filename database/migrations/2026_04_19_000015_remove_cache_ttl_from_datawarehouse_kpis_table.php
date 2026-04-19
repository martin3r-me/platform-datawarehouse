<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropColumn('cache_ttl_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->unsignedInteger('cache_ttl_seconds')->default(300)->after('cached_at');
        });
    }
};
