<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->string('display_range', 30)->nullable()->default('current_month')->after('cached_at');
            $table->decimal('cached_comparison_value', 20, 4)->nullable()->after('display_range');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_kpis', function (Blueprint $table) {
            $table->dropColumn(['display_range', 'cached_comparison_value']);
        });
    }
};
