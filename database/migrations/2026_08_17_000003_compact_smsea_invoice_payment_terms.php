<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compact the SMSEA proforma-invoice payment terms from four clauses to three
 * (same commercial meaning, the advance-payment and payment-mode clauses merged)
 * so the section takes less vertical space on the invoice. Settings data only.
 */
return new class extends Migration
{
    private string $terms = "100% advance payment is required before scheduling or commencing the assignment. Payment may be made by cash, account payee cheque, pay order or bank transfer in favour of SMS Environmental Alliance.\nVAT and AIT shall be treated as stated in the Proforma Invoice. Where not included, applicable VAT shall be added to the payable amount and AIT shall be deducted at source in accordance with prevailing laws.\nPlease mention the Proforma Invoice reference when making payment or sharing payment confirmation.";

    private string $previous = "100% advance payment is required before scheduling or commencing the assignment.\nPayment shall be made by cash, account payee cheque, pay order or bank transfer in favour of SMS Environmental Alliance.\nVAT and AIT shall be applied as stated in the Proforma Invoice. Where not included, applicable VAT shall be added to the payable amount, and AIT shall be deducted at source in accordance with prevailing laws.\nPlease mention the Proforma Invoice reference when making payment or sharing payment confirmation.";

    public function up(): void
    {
        $this->set($this->terms);
    }

    public function down(): void
    {
        $this->set($this->previous);
    }

    private function set(string $terms): void
    {
        $smseaId = DB::table('business_entities')->where('entity_code', 'SMSEA')->value('id');

        if (! $smseaId) {
            return;
        }

        DB::table('settings')->where('business_entity_id', $smseaId)
            ->update(['invoice_payment_terms' => $terms, 'updated_at' => now()]);
    }
};
