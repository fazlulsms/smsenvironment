<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional site name for the commercial table (falls back to the client company
 * name when blank). Snapshotted directly on the invoice row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('site_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', fn (Blueprint $table) => $table->dropColumn('site_name'));
    }
};
