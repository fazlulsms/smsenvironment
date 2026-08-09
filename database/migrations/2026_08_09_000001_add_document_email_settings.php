<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('quotation_email_subject_template')->nullable()->after('quotation_subject_pattern');
            $table->text('quotation_email_body_template')->nullable()->after('quotation_email_subject_template');
            $table->string('proforma_invoice_email_subject_template')->nullable()->after('invoice_charge_for_pattern');
            $table->text('proforma_invoice_email_body_template')->nullable()->after('proforma_invoice_email_subject_template');
            $table->text('default_email_cc')->nullable()->after('proforma_invoice_email_body_template');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'quotation_email_subject_template',
                'quotation_email_body_template',
                'proforma_invoice_email_subject_template',
                'proforma_invoice_email_body_template',
                'default_email_cc',
            ]);
        });
    }
};
