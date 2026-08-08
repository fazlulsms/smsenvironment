<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('quotation_vat_treatment')->default('exclusive')->after('quotation_default_notes');
            $table->decimal('quotation_vat_rate', 8, 3)->nullable()->after('quotation_vat_treatment');
            $table->boolean('quotation_show_vat_separately')->default(true)->after('quotation_vat_rate');
            $table->text('quotation_vat_note')->nullable()->after('quotation_show_vat_separately');
            $table->text('quotation_ait_note')->nullable()->after('quotation_vat_note');
            $table->text('quotation_terms_conditions')->nullable()->after('quotation_ait_note');
            $table->boolean('quotation_include_acceptance')->default(true)->after('quotation_terms_conditions');
            $table->text('quotation_acceptance_text')->nullable()->after('quotation_include_acceptance');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('vat_treatment')->default('exclusive')->after('adjustment');
            $table->decimal('vat_rate', 8, 3)->nullable()->after('vat_treatment');
            $table->decimal('vat_amount', 14, 2)->default(0)->after('vat_rate');
            $table->boolean('show_vat_separately')->default(true)->after('vat_amount');
            $table->text('vat_note')->nullable()->after('show_vat_separately');
            $table->text('ait_note')->nullable()->after('vat_note');
            $table->text('terms_conditions')->nullable()->after('ait_note');
            $table->boolean('include_acceptance')->default(true)->after('terms_conditions');
            $table->text('acceptance_text')->nullable()->after('include_acceptance');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'vat_treatment',
                'vat_rate',
                'vat_amount',
                'show_vat_separately',
                'vat_note',
                'ait_note',
                'terms_conditions',
                'include_acceptance',
                'acceptance_text',
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'quotation_vat_treatment',
                'quotation_vat_rate',
                'quotation_show_vat_separately',
                'quotation_vat_note',
                'quotation_ait_note',
                'quotation_terms_conditions',
                'quotation_include_acceptance',
                'quotation_acceptance_text',
            ]);
        });
    }
};
