<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private BankAccount $bank;

    private Service $emp;

    private Service $eia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Setting::current();
        $this->client = Client::query()->create(['company_name' => 'Client Ltd.', 'address' => 'Dhaka']);
        $this->bank = BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '1', 'is_active' => true, 'is_default' => true]);
        $this->emp = Service::query()->create(['name' => 'Environmental Management Plan', 'short_name' => 'Environmental Management Plan', 'service_type' => 'consolidated', 'invoice_description' => 'EMP — inclusive of document review, onsite assessment and reporting.', 'default_rate' => 5000, 'is_active' => true]);
        $this->eia = Service::query()->create(['name' => 'Environmental Impact Assessment', 'short_name' => 'Environmental Impact Assessment', 'service_type' => 'bundle', 'invoice_description' => 'EIA — assessment of environmental aspects and impacts.', 'default_rate' => 30000, 'is_active' => true]);
    }

    private function store(array $payload): ProformaInvoice
    {
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge([
            'client_id' => $this->client->id, 'bank_account_id' => $this->bank->id,
            'date' => '2026-08-12', 'vat_treatment' => 'exclusive',
        ], $payload))->assertRedirect();

        return ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
    }

    /** All commercial fields are consistent after a consolidated save. */
    public function test_consolidated_save_is_internally_consistent(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Environmental Management Plan',
            'consolidated' => ['service_id' => $this->emp->id, 'description' => 'EMP — inclusive of document review, onsite assessment and reporting.', 'amount' => 5000],
        ]);

        $this->assertSame('Environmental Management Plan', $invoice->charge_title);
        $this->assertSame($invoice->charge_title, $invoice->charge_for, 'charge_for must mirror charge_title');
        $this->assertSame($this->emp->id, $invoice->items->first()->service_id);
        $this->assertStringContainsString('EMP', $invoice->items->first()->description);
        $this->assertEquals(5000, (float) $invoice->total);

        // No leftover from another service anywhere.
        $this->assertStringNotContainsString('Energy Audit', $invoice->charge_title.$invoice->charge_for.$invoice->items->first()->description);
    }

    /** Editing and switching the service replaces the previous saved content. */
    public function test_editing_and_switching_service_replaces_saved_content(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Environmental Management Plan',
            'consolidated' => ['service_id' => $this->emp->id, 'description' => 'EMP description', 'amount' => 5000],
        ]);

        $this->actingAs($this->user)->put(route('proforma-invoices.update', $invoice), [
            'client_id' => $this->client->id, 'bank_account_id' => $this->bank->id, 'date' => '2026-08-12', 'vat_treatment' => 'exclusive',
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Environmental Impact Assessment',
            'consolidated' => ['service_id' => $this->eia->id, 'description' => 'EIA description', 'amount' => 30000],
        ])->assertRedirect();

        $invoice->refresh()->load('items');
        $this->assertSame('Environmental Impact Assessment', $invoice->charge_title);
        $this->assertSame('Environmental Impact Assessment', $invoice->charge_for);
        $this->assertSame($this->eia->id, $invoice->items->first()->service_id);
        $this->assertSame('EIA description', $invoice->items->first()->description);
        $this->assertStringNotContainsString('EMP', $invoice->items->first()->description);
    }

    /** Service = none keeps manually typed custom content. */
    public function test_service_none_preserves_manual_content(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Special Consultancy Service',
            'consolidated' => ['service_id' => '', 'description' => 'Custom consultancy assignment', 'amount' => 1000],
        ]);

        $this->assertSame('Special Consultancy Service', $invoice->charge_title);
        $this->assertNull($invoice->items->first()->service_id);
        $this->assertSame('Custom consultancy assignment', $invoice->items->first()->description);
    }

    /** Breakdown saves the package components and one total. */
    public function test_breakdown_saves_components_and_total(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'component_breakdown',
            'charge_title' => 'EIA Package',
            'breakdown' => ['service_id' => $this->eia->id, 'components' => "Ambient Air Quality\nStack Emission Test\nNoise Level", 'amount' => 50000],
        ]);

        $this->assertSame('EIA Package', $invoice->charge_title);
        $this->assertSame('EIA Package', $invoice->charge_for);
        $this->assertCount(3, $invoice->items->first()->scope_items);
        $this->assertEquals(50000, (float) $invoice->total);
    }

    /** The invoice detail view renders the saved snapshot with no Unit/Qty/Rate. */
    public function test_detail_view_uses_saved_snapshot_without_unit_qty_rate(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Environmental Management Plan',
            'consolidated' => ['service_id' => $this->emp->id, 'description' => 'EMP saved description', 'amount' => 5000],
        ]);

        $response = $this->actingAs($this->user)->get(route('proforma-invoices.show', $invoice))->assertOk();
        $response->assertSee('Environmental Management Plan');
        $response->assertSee('EMP saved description');
        $response->assertDontSee('Qty');
        $response->assertDontSee('Unit Rate');
        $response->assertDontSee('Energy Audit');
    }

    public function test_charge_sync_works_under_a_secondary_entity(): void
    {
        $eco = BusinessEntity::query()->where('entity_code', 'ECOVERITAS')->firstOrFail();
        app(CurrentEntity::class)->use($eco->id);
        Setting::current();
        $client = Client::query()->create(['company_name' => 'Eco Client', 'address' => 'X']);
        $bank = BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Bank', 'account_number' => '9', 'is_active' => true, 'is_default' => true]);

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-12', 'vat_treatment' => 'exclusive',
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Environmental Management Plan',
            'consolidated' => ['service_id' => $this->emp->id, 'description' => 'EMP under EcoVeritas', 'amount' => 7000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('ECOVERITAS', $invoice->entity_code);
        $this->assertSame('Environmental Management Plan', $invoice->charge_title);
        $this->assertSame($invoice->charge_title, $invoice->charge_for);
        $this->assertSame($this->emp->id, $invoice->items->first()->service_id);
    }
}
