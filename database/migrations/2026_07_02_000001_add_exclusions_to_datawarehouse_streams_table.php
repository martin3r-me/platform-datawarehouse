<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds configurable exclusion rules to a stream. Rows matching ANY rule are
 * excluded from ALL KPI calculations on that stream ("bereinigt") — e.g. for
 * the RKV rebate base, lines like Provisionsgutschriften, Mankos, Verluste or
 * negative returns must not count. Rules are data (editable via tool/UI), not
 * hardcoded, so year-end agreement changes are a config change.
 *
 * Shape (JSON array): [{ "field": "artikelbezeichnung", "op": "contains",
 * "value": "provisionsgutschrift", "note": "..." }, ...]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->json('exclusions')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('datawarehouse_streams', function (Blueprint $table) {
            $table->dropColumn('exclusions');
        });
    }
};
