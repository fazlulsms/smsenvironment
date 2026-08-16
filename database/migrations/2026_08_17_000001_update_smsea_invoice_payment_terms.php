<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update the SMSEA proforma-invoice payment terms to the current four-point
 * wording. Settings data only — saved documents keep the terms they were issued
 * with; new invoices pick these up via DocumentContentService defaults.
 */
return new class extends Migration
{
    private string $terms = "100% advance payment is required before scheduling or commencing the assignment.\nPayment shall be made by cash, account payee cheque, pay order or bank transfer in favour of SMS Environmental Alliance.\nVAT and AIT shall be applied as stated in the Proforma Invoice. Where not included, applicable VAT shall be added to the payable amount, and AIT shall be deducted at source in accordance with prevailing laws.\nPlease mention the Proforma Invoice reference when making payment or sharing payment confirmation.";

    public function up(): void
    {
        $smseaId = DB::table('business_entities')->where('entity_code', 'SMSEA')->value('id');

        if (! $smseaId) {
            return;
        }

        DB::table('settings')
            ->where('business_entity_id', $smseaId)
            ->update(['invoice_payment_terms' => $this->terms, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $smseaId = DB::table('business_entities')->where('entity_code', 'SMSEA')->value('id');

        if (! $smseaId) {
            return;
        }

        DB::table('settings')
            ->where('business_entity_id', $smseaId)
            ->update(['invoice_payment_terms' => "Payment should be made by account payee cheque or bank transfer.\nVAT/AIT will be treated as per applicable government rules.", 'updated_at' => now()]);
    }
};
