<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\User;
use App\Support\CurrentEntity;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end acceptance for the Quick Environmental shortcut: it drives the real
 * chain — Prepare (shortcut) → the flashed prefill → the NORMAL create/store form →
 * a saved, numbered document and a rendered PDF — exactly as a user would, proving
 * the shortcut reuses the existing document engine rather than a parallel one.
 */
class QuickEnvironmentalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardSeeder::class);
        $this->user = User::factory()->create();
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
        BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance', 'bank_name' => 'Prime Bank Ltd.',
            'account_number' => '1', 'is_active' => true, 'is_default' => true,
        ]);
    }

    /** Prepare via the shortcut and return the flashed prefill (the create-form input). */
    private function prepared(array $payload): array
    {
        $response = $this->actingAs($this->user)->post(route('quick-env.prepare'), $payload)->assertRedirect();

        return $response->getSession()->get('_old_input');
    }

    public function test_case_a_eia_proforma_saves_a_clean_consolidated_invoice(): void
    {
        $client = Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);

        $old = $this->prepared([
            'client_id' => $client->id, 'service' => 'eia', 'amount' => '50000', 'document_type' => 'proforma_invoice',
        ]);

        // Submit the prefill through the normal store, as clicking Save would.
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge($old, ['date' => '2026-08-16']))->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertNotEmpty($invoice->number);                         // a number is consumed only now
        $this->assertSame('SMSEA', $invoice->entity_code);
        $this->assertSame('consolidated', $invoice->charge_presentation);
        $this->assertEquals(50000, (float) $invoice->items->first()->amount);

        $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk()
            ->assertSee('Environmental Impact Assessment')
            ->assertDontSee('Environmental Impact Assessment (Single)'); // no redundant one-line scope

        $this->actingAs($this->user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    public function test_case_b_ept_proforma_saves_one_total_with_the_package_scope(): void
    {
        $client = Client::query()->create(['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur']);

        $old = $this->prepared([
            'client_id' => $client->id, 'service' => 'ept', 'amount' => '30000', 'document_type' => 'proforma_invoice',
        ]);

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge($old, ['date' => '2026-08-16']))->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $item = $invoice->items->first();
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertCount(1, $invoice->items);                          // one commercial line
        $this->assertEquals(30000, (float) $item->amount);               // one consolidated total
        $this->assertCount(7, $item->scope_items);                       // the seven configured parameters

        $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk()
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('Stack Emission Test')
            ->assertSee('ODS Assessment / Inventory');

        $this->actingAs($this->user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    public function test_case_c_ept_quotation_saves_the_same_package_as_a_quotation(): void
    {
        $client = Client::query()->create(['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur']);

        $old = $this->prepared([
            'client_id' => $client->id, 'service' => 'ept', 'amount' => '30000', 'document_type' => 'quotation',
        ]);

        $this->actingAs($this->user)->post(route('quotations.store'), array_merge($old, ['date' => '2026-08-16']))->assertRedirect();

        $quotation = Quotation::query()->latest('id')->with('items')->firstOrFail();
        $item = $quotation->items->first();
        $this->assertNotEmpty($quotation->number);
        $this->assertSame('SMSEA', $quotation->entity_code);
        $this->assertCount(1, $quotation->items);
        $this->assertEquals(30000, (float) $item->amount);
        $this->assertCount(7, $item->scope_items);                       // package scope attached on the row

        $this->actingAs($this->user)->get(route('quotations.show', $quotation))->assertOk()
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('Stack Emission Test');

        $this->actingAs($this->user)->get(route('quotations.pdf', $quotation))->assertOk();
    }
}
