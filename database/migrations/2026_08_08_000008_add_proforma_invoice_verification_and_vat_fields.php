<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('vat_treatment')->default('exclusive')->after('adjustment');
            $table->decimal('vat_rate', 8, 3)->nullable()->after('vat_treatment');
            $table->decimal('vat_amount', 14, 2)->default(0)->after('vat_rate');
            $table->boolean('show_vat_separately')->default(true)->after('vat_amount');
            $table->string('verification_payload_version', 32)->nullable()->after('notes');
            $table->string('verification_id', 32)->nullable()->after('verification_payload_version');
            $table->string('verification_signature', 128)->nullable()->after('verification_id');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'vat_treatment',
                'vat_rate',
                'vat_amount',
                'show_vat_separately',
                'verification_payload_version',
                'verification_id',
                'verification_signature',
            ]);
        });
    }
};
