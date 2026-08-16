<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Standard;
use App\Models\User;
use App\Services\Commercial\CommercialDraftResolver;
use App\Services\Commercial\CommercialInstructionExtractor;
use App\Support\CurrentEntity;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardSeeder::class);
        $this->user = User::factory()->create();
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
        Setting::current();
        $this->client = Client::query()->create(['company_name' => 'UNI Garments Limited', 'address' => 'Chattogram']);
        $this->bank = BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '1', 'is_active' => true, 'is_default' => true]);
    }

    private function envCategory(): ServiceCategory
    {
        return ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->firstOrFail();
    }

    private function ept(): Standard
    {
        return Standard::query()->where('name', 'Environmental Parameter Testing')->firstOrFail();
    }

    private function store(array $payload): ProformaInvoice
    {
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge([
            'client_id' => $this->client->id, 'bank_account_id' => $this->bank->id,
            'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
        ], $payload))->assertRedirect();

        return ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
    }

    public function test_master_ept_is_configured_as_a_package_with_seven_scope_items(): void
    {
        $this->assertCount(7, $this->ept()->defaultScope());
        $this->assertContains('Stack Emission Test', $this->ept()->defaultScope());
    }

    public function test_itemized_ept_row_receives_its_package_scope_as_one_priced_line(): void
    {
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Parameter Testing', 'amount' => 30000]],
        ]);

        $item = $invoice->items->first();
        $this->assertCount(1, $invoice->items);                    // ONE commercial line
        $this->assertEquals(30000, (float) $item->amount);          // one amount, no per-component pricing
        $this->assertCount(7, $item->scope_items);                  // package scope attached
        $this->assertContains('Ambient Air Quality Assessment', $item->scope_items);
        $this->assertContains('ODS Assessment / Inventory', $item->scope_items);

        // The itemized table renders the scope as "Including:" (Description | Amount, no Unit/Qty/Rate).
        $rows = $invoice->items->map(fn ($i) => ['title' => $i->description, 'activities' => collect($i->scope_items ?: []), 'item' => $i]);
        $html = view('documents.invoice_charge_table', ['invoice' => $invoice, 'serviceRows' => $rows, 'currency' => 'BDT'])->render();
        $this->assertStringContainsString('Including:', $html);
        $this->assertStringContainsString('Stack Emission Test', $html);
        $this->assertStringNotContainsString('Qty', $html);
    }

    public function test_saved_scope_is_authoritative_after_the_master_package_changes(): void
    {
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Parameter Testing', 'amount' => 30000]],
        ]);

        // Master package is later reduced — the historical document must not change.
        $this->ept()->update(['default_scope' => 'Only One Parameter Now']);

        $this->assertCount(7, $invoice->fresh('items')->items->first()->scope_items);
    }

    public function test_edit_preserves_the_rows_submitted_scope(): void
    {
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Parameter Testing', 'amount' => 30000]],
        ]);

        // Edit form resubmits the saved scope (as item_row renders it) with a trimmed list.
        $this->actingAs($this->user)->put(route('proforma-invoices.update', $invoice), [
            'client_id' => $this->client->id, 'bank_account_id' => $this->bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Parameter Testing', 'scope_items' => "Stack Emission Test\nNoise Level Assessment", 'amount' => 32000]],
        ])->assertRedirect();

        $item = $invoice->fresh('items')->items->first();
        $this->assertSame(['Stack Emission Test', 'Noise Level Assessment'], $item->scope_items); // edit survived
        $this->assertEquals(32000, (float) $item->amount);
    }

    public function test_itemized_blank_row_inherits_the_service_name_not_the_service_placeholder(): void
    {
        $eia = Standard::query()->where('code', 'EIA')->firstOrFail();

        // Mirrors the reported bug: EIA selected, one blank default row priced.
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$eia->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => '', 'amount' => 20000]],
        ]);

        $item = $invoice->items->first();
        $this->assertCount(1, $invoice->items);
        $this->assertSame('Environmental Impact Assessment', $item->description); // not the literal "Service"
        $this->assertSame([], $item->scope_items ?? []);
        $this->assertEquals(20000, (float) $item->amount);
    }

    public function test_empty_junk_itemized_rows_are_dropped(): void
    {
        $eia = Standard::query()->where('code', 'EIA')->firstOrFail();

        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$eia->id],
            'charge_presentation' => 'itemized',
            'items' => [
                ['description' => 'Environmental Impact Assessment', 'amount' => 20000],
                ['description' => '', 'amount' => 0], // junk blank row
            ],
        ]);

        $this->assertCount(1, $invoice->items);
        $this->assertSame('Environmental Impact Assessment', $invoice->items->first()->description);
    }

    public function test_eia_itemized_gets_no_manufactured_scope(): void
    {
        $eia = Standard::query()->where('code', 'EIA')->firstOrFail();
        $this->assertSame([], $eia->defaultScope());

        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$eia->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Impact Assessment', 'amount' => 50000]],
        ]);

        $this->assertSame([], $invoice->items->first()->scope_items ?? []); // no empty "Including" invented
    }

    public function test_multiple_package_rows_keep_independent_scope(): void
    {
        // A second scoped package alongside EPT.
        $water = $this->envCategory()->standards()->create([
            'slug' => 'water-testing-pkg', 'name' => 'Water Quality Testing', 'active' => true, 'display_order' => 99,
            'default_scope' => "pH\nBOD\nCOD",
        ]);

        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id, $water->id],
            'charge_presentation' => 'itemized',
            'items' => [
                ['description' => 'Environmental Parameter Testing', 'amount' => 30000],
                ['description' => 'Water Quality Testing', 'amount' => 20000],
            ],
        ]);

        $rows = $invoice->items->keyBy('description');
        $this->assertCount(7, $rows['Environmental Parameter Testing']->scope_items);
        $this->assertSame(['pH', 'BOD', 'COD'], $rows['Water Quality Testing']->scope_items);
        $this->assertEquals(50000, (float) $invoice->total);
    }

    public function test_breakdown_mode_still_attaches_the_package_scope(): void
    {
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'component_breakdown', 'breakdown' => ['amount' => 30000],
        ]);

        $this->assertCount(7, $invoice->items->first()->scope_items);
        $this->assertEquals(30000, (float) $invoice->total);
    }

    public function test_resolver_suggests_breakdown_for_a_single_scoped_package(): void
    {
        $draft = app(CommercialDraftResolver::class)->resolve([
            'selected_packages' => ['Environmental Parameter Testing'], 'consolidated_amount' => '30000', 'currency' => 'BDT',
        ] + CommercialInstructionExtractor::blank(), BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));

        $this->assertSame('component_breakdown', $draft['charge_presentation']['value']);
        $this->assertTrue($draft['standards'][0]['has_scope']);
    }

    public function test_edit_form_shows_the_package_scope_indicator_not_a_permanent_textarea(): void
    {
        $invoice = $this->store([
            'service_category_id' => $this->envCategory()->id, 'standards' => [$this->ept()->id],
            'charge_presentation' => 'itemized',
            'items' => [['description' => 'Environmental Parameter Testing', 'amount' => 30000]],
        ]);

        $html = $this->actingAs($this->user)->get(route('proforma-invoices.edit', $invoice))->assertOk()->getContent();
        $this->assertStringContainsString('package items · View / Edit scope', $html); // progressive-disclosure indicator
        $this->assertStringNotContainsString('data-scope-items', $html);                // old duplicate field stays gone
        $this->assertStringNotContainsString('Optional', $html);
    }
}
