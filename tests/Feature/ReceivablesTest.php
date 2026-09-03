<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function invoice(float $total = 100000, string $currency = 'BDT'): ProformaInvoice
    {
        $this->useSmsea();
        $client = Client::query()->create(['company_name' => 'Acme Ltd.', 'address' => 'Dhaka']);

        return ProformaInvoice::query()->create([
            'client_id' => $client->id, 'number' => 'SMSEA/PI/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated', 'currency' => $currency,
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => $total, 'adjustment' => 0, 'total' => $total,
        ]);
    }

    public function test_invoice_can_be_marked_won_and_lost_with_reason(): void
    {
        $user = User::factory()->staff()->create();
        $invoice = $this->invoice();

        $this->actingAs($user)->patch(route('proforma-invoices.status', $invoice), ['commercial_status' => 'won'])->assertRedirect();
        $this->assertSame('won', $invoice->fresh()->commercial_status);

        $this->actingAs($user)->patch(route('proforma-invoices.status', $invoice), [
            'commercial_status' => 'lost', 'lost_reason' => 'Price', 'lost_note' => 'Too high',
        ])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('lost', $invoice->commercial_status);
        $this->assertSame('Price', $invoice->lost_reason);
    }

    public function test_partial_and_full_payment_calculations(): void
    {
        $user = User::factory()->staff()->create();
        $invoice = $this->invoice(100000);

        $this->actingAs($user)->post(route('proforma-invoices.payments.store', $invoice), [
            'amount' => 60000, 'received_date' => now()->toDateString(), 'method' => 'Bank Transfer',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(60000.0, $invoice->receivedAmount());
        $this->assertSame(40000.0, $invoice->dueAmount());
        $this->assertSame('partial', $invoice->paymentStatus());

        $this->actingAs($user)->post(route('proforma-invoices.payments.store', $invoice), [
            'amount' => 40000, 'received_date' => now()->toDateString(),
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(0.0, $invoice->dueAmount());
        $this->assertSame('paid', $invoice->paymentStatus());
        $this->assertCount(2, $invoice->payments);
    }

    public function test_overpayment_is_prevented(): void
    {
        $user = User::factory()->staff()->create();
        $invoice = $this->invoice(100000);

        $this->actingAs($user)->post(route('proforma-invoices.payments.store', $invoice), [
            'amount' => 150000, 'received_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $invoice->fresh()->receivedAmount());
    }

    public function test_payment_stored_in_invoice_currency(): void
    {
        $user = User::factory()->staff()->create();
        $invoice = $this->invoice(5000, 'USD');

        $this->actingAs($user)->post(route('proforma-invoices.payments.store', $invoice), [
            'amount' => 2000, 'received_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('USD', $invoice->payments()->first()->currency);
    }

    public function test_only_admin_can_delete_payment(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $invoice = $this->invoice();
        $this->actingAs($staff)->post(route('proforma-invoices.payments.store', $invoice), ['amount' => 1000, 'received_date' => now()->toDateString()]);
        $payment = $invoice->payments()->first();

        $this->actingAs($staff)->delete(route('proforma-invoices.payments.destroy', [$invoice, $payment]))->assertForbidden();
        $this->assertDatabaseHas('invoice_payments', ['id' => $payment->id]);

        $this->actingAs($admin)->delete(route('proforma-invoices.payments.destroy', [$invoice, $payment]))->assertRedirect();
        $this->assertDatabaseMissing('invoice_payments', ['id' => $payment->id]);
    }

    public function test_receivables_page_loads_and_filters(): void
    {
        $user = User::factory()->staff()->create();
        $this->invoice();

        $this->actingAs($user)->get(route('receivables.index'))->assertOk();
        $this->actingAs($user)->get(route('receivables.index', ['filter' => 'unpaid']))->assertOk();
    }
}
