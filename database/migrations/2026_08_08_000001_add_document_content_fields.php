<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('short_name')->nullable()->after('name');
            $table->string('quotation_subject_template')->nullable()->after('default_rate');
            $table->text('quotation_scope')->nullable()->after('quotation_subject_template');
            $table->text('compliance_note')->nullable()->after('quotation_scope');
            $table->text('invoice_description')->nullable()->after('compliance_note');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('quotation_subject_pattern')->nullable()->after('default_payment_terms');
            $table->text('quotation_intro_text')->nullable()->after('quotation_subject_pattern');
            $table->text('quotation_compliance_note')->nullable()->after('quotation_intro_text');
            $table->text('quotation_closing_text')->nullable()->after('quotation_compliance_note');
            $table->text('quotation_validity_text')->nullable()->after('quotation_closing_text');
            $table->text('quotation_default_notes')->nullable()->after('quotation_validity_text');
            $table->string('invoice_charge_for_pattern')->nullable()->after('quotation_default_notes');
            $table->text('invoice_payment_terms')->nullable()->after('invoice_charge_for_pattern');
            $table->text('invoice_validity_text')->nullable()->after('invoice_payment_terms');
            $table->text('invoice_default_notes')->nullable()->after('invoice_validity_text');
            $table->string('currency_major_name')->default('Taka')->after('default_currency');
            $table->string('currency_minor_name')->default('Paisa')->after('currency_major_name');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->text('compliance_note')->nullable()->after('intro_text');
            $table->text('closing_text')->nullable()->after('compliance_note');
            $table->text('validity_text')->nullable()->after('closing_text');
        });

        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->text('validity_text')->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn('validity_text');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['compliance_note', 'closing_text', 'validity_text']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'quotation_subject_pattern',
                'quotation_intro_text',
                'quotation_compliance_note',
                'quotation_closing_text',
                'quotation_validity_text',
                'quotation_default_notes',
                'invoice_charge_for_pattern',
                'invoice_payment_terms',
                'invoice_validity_text',
                'invoice_default_notes',
                'currency_major_name',
                'currency_minor_name',
            ]);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'quotation_subject_template',
                'quotation_scope',
                'compliance_note',
                'invoice_description',
            ]);
        });
    }
};
