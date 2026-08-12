<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic dual-currency + reference fields. Entity-agnostic: SMSEA leaves them
     * null (single-currency, snapshot default currency) and is unaffected; Eidikos
     * surfaces them (Reference No. + USD→BDT conversion). No conversion rate is ever
     * hard-coded — it is entered per invoice.
     */
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('number');
            $table->string('currency', 8)->nullable()->after('site_name');
            $table->decimal('conversion_rate', 14, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn(['reference_no', 'currency', 'conversion_rate']);
        });
    }
};
