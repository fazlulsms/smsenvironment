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
 * End-to-end acceptance for the Quick Environmental shortcut: "Prepare & View"
 * creates the document through the normal store (numbering, snapshots, QR,
 * verification) and lands on its view page; the document renders a correct PDF.
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

    private function prepare(array $payload)
    {
        return $this->actingAs($this->user)->post(route('quick-env.prepare'), $payload);
    }

    public function test_case_a_eia_package_lands_on_the_view_page_and_renders(): void
    {
        $client = Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);

        // EIA now defaults to a package breakdown. Prepare & View lands on the
        // document's own view page.
        $this->prepare(['client_id' => $client->id, 'service' => 'eia', 'amount' => '50000', 'document_type' => 'proforma_invoice'])
            ->assertRedirect(route('proforma-invoices.show', ProformaInvoice::query()->latest('id')->firstOrFail()));

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertCount(7, $invoice->items->first()->scope_items);

        $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk()
            ->assertSee('Environmental Impact Assessment')
            ->assertSee('Stack Emission Test');
        $this->actingAs($this->user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    public function test_case_b_ept_package_saves_one_total_with_scope(): void
    {
        $client = Client::query()->create(['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur']);

        $this->prepare(['client_id' => $client->id, 'service' => 'ept', 'amount' => '30000', 'document_type' => 'proforma_invoice'])
            ->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertEquals(30000, (float) $invoice->items->first()->amount);
        $this->assertCount(7, $invoice->items->first()->scope_items);

        $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk()
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('ODS Assessment / Inventory');
        $this->actingAs($this->user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    public function test_case_c_ept_quotation_saves_and_renders(): void
    {
        $client = Client::query()->create(['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur']);

        $this->prepare(['client_id' => $client->id, 'service' => 'ept', 'amount' => '30000', 'document_type' => 'quotation'])
            ->assertRedirect();

        $quotation = Quotation::query()->latest('id')->with('items')->firstOrFail();
        $this->assertNotEmpty($quotation->number);
        $this->assertCount(7, $quotation->items->first()->scope_items);

        $this->actingAs($this->user)->get(route('quotations.show', $quotation))->assertOk()
            ->assertSee('Environmental Parameter Testing');
        $this->actingAs($this->user)->get(route('quotations.pdf', $quotation))->assertOk();
    }

    public function test_case_d_eia_consolidated_saves_a_clean_single_fee(): void
    {
        $client = Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);

        $this->prepare(['client_id' => $client->id, 'service' => 'eia', 'presentation' => 'consolidated', 'amount' => '50000', 'document_type' => 'proforma_invoice'])
            ->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('consolidated', $invoice->charge_presentation);

        $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk()
            ->assertSee('Environmental Impact Assessment')
            ->assertDontSee('Environmental Impact Assessment (Single)');
        $this->actingAs($this->user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }
}
