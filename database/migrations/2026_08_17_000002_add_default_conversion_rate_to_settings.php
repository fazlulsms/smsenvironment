<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-entity default foreign-currency conversion rate (e.g. USD -> BDT) that the
 * quick shortcut and the invoice form prefill and can update via a "set as default"
 * tick, so a working rate persists until it is next changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('default_conversion_rate', 12, 4)->nullable()->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('default_conversion_rate');
        });
    }
};
