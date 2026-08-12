<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flexible commercial presentation for documents. The item rows already carry
 * description/scope/qty/rate/amount, so only a document-level mode + optional
 * charge title are needed. Added to quotations too so the same capability can
 * be reused there later without another schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['proforma_invoices', 'quotations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('charge_presentation')->default('itemized');
                $blueprint->string('charge_title')->nullable();
            });
            DB::table($table)->whereNull('charge_presentation')->update(['charge_presentation' => 'itemized']);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('default_charge_presentation')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['proforma_invoices', 'quotations'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn(['charge_presentation', 'charge_title']));
        }

        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('default_charge_presentation'));
    }
};
