<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire selected standards into documents. Two parts:
 *  - An immutable JSON snapshot (+ live category ref) on each document, so PDFs,
 *    previews and emails render exactly what was saved even if the master changes.
 *  - A live document_standards pivot for future reporting/filtering by
 *    entity / client / category / standard (rebuilt on each save; never used for
 *    rendering, so it can be recomputed safely without touching history).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotations', 'proforma_invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('service_category_id')->nullable()->after('client_id')->nullOnDelete();
                $table->json('standards_snapshot')->nullable()->after('settings_snapshot');
            });
        }

        Schema::create('document_standards', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');   // 'quotation' | 'proforma_invoice'
            $table->unsignedBigInteger('document_id');
            $table->foreignId('standard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()->nullOnDelete();
            $table->foreignId('business_entity_id')->nullable()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['standard_id']);
            $table->index(['business_entity_id']);
            $table->index(['service_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_standards');

        foreach (['quotations', 'proforma_invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('service_category_id');
                $table->dropColumn('standards_snapshot');
            });
        }
    }
};
