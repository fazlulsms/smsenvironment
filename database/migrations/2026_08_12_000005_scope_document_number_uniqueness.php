<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbers are unique per entity, not globally: two entities may each
 * legitimately issue their own "0001". Replace the global unique index on
 * `number` with a composite (business_entity_id, number) unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotations', 'proforma_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['number']);
                $blueprint->unique(['business_entity_id', 'number']);
            });
        }
    }

    public function down(): void
    {
        foreach (['quotations', 'proforma_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['business_entity_id', 'number']);
                $blueprint->unique(['number']);
            });
        }
    }
};
