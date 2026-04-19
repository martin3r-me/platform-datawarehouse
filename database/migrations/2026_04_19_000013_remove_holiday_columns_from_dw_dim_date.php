<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dw_dim_date', function (Blueprint $table) {
            $table->dropIndex(['is_feiertag']);
            $table->dropIndex(['bundesland']);

            $table->dropColumn([
                'is_feiertag',
                'feiertag_name',
                'is_schulferien',
                'schulferien_name',
                'bundesland',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('dw_dim_date', function (Blueprint $table) {
            $table->boolean('is_feiertag')->default(false)->after('year');
            $table->string('feiertag_name', 60)->nullable()->after('is_feiertag');
            $table->boolean('is_schulferien')->default(false)->after('feiertag_name');
            $table->string('schulferien_name', 60)->nullable()->after('is_schulferien');
            $table->string('bundesland', 5)->default('NW')->after('schulferien_name');

            $table->index('is_feiertag');
            $table->index('bundesland');
        });
    }
};
