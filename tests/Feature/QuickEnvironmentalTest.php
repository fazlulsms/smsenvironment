<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Standard;
use App\Models\User;
use App\Support\CurrentEntity;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Quick Environmental Document shortcut. "Prepare & View" resolves the chosen
 * service against existing master data and hands the payload to the NORMAL store,
 * creating the document under SMSEA and opening its view page. It must resolve
 * everything server-side (entity, client, bank, service) and never build a
 * parallel engine.
 */
class QuickEnvironmentalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(StandardSeeder::class);
    }

    private function smseaId(): int
    {
        return BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id');
    }

    private function client(): Client
    {
        return Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);
    }

    private function standard(string $slug): Standard
    {
        $catId = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->value('id');

        return Standard::query()->where('service_category_id', $catId)->where('slug', $slug)->firstOrFail();
    }

    private function smseaBank(bool $default = true): BankAccount
    {
        return BankAccount::query()->forceCreate([
            'business_entity_id' => $this->smseaId(),
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'account_number' => '12345678',
            'is_active' => true,
            'is_default' => $default,
        ]);
    }

    private function prepare(array $overrides = [])
    {
        return $this->actingAs($this->user)->post(route('quick-env.prepare'), array_merge([
            'client_id' => $this->client()->id,
            'service' => 'eia',
            'amount' => '50000',
            'document_type' => 'proforma_invoice',
        ], $overrides));
    }

    private function latestInvoice(): ProformaInvoice
    {
        return ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
    }

    private function latestQuotation(): Quotation
    {
        return Quotation::query()->latest('id')->with('items')->firstOrFail();
    }

    // 1
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('quick-env.index'))->assertRedirect(route('login'));
    }

    // 2
    public function test_index_renders_services_and_the_prepare_and_view_action(): void
    {
        $this->actingAs($this->user)->get(route('quick-env.index'))->assertOk()
            ->assertSee('Environmental Impact Assessment')
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('Prepare &amp; View', false);
    }

    // 3
    public function test_index_preselects_the_service_from_the_query(): void
    {
        $this->actingAs($this->user)->get(route('quick-env.index', ['service' => 'ept']))->assertOk()
            ->assertSee('value="ept" data-default-presentation="component_breakdown" checked', false);

        // Default (no query) preselects EIA — now defaulting to a package breakdown.
        $this->actingAs($this->user)->get(route('quick-env.index'))->assertOk()
            ->assertSee('value="eia" data-default-presentation="component_breakdown" checked', false);
    }

    // 4
    public function test_required_fields_are_validated_and_nothing_is_created(): void
    {
        $this->actingAs($this->user)->post(route('quick-env.prepare'), [])
            ->assertSessionHasErrors(['client_id', 'service', 'amount', 'document_type']);

        $this->assertSame(0, ProformaInvoice::query()->withoutGlobalScopes()->count());
    }

    // 5
    public function test_service_must_be_one_of_the_two_approved_records(): void
    {
        $this->prepare(['service' => 'iso_9001'])->assertSessionHasErrors('service');
    }

    // 6
    public function test_amount_must_be_positive(): void
    {
        $this->prepare(['amount' => '0'])->assertSessionHasErrors('amount');
        $this->prepare(['amount' => '-5'])->assertSessionHasErrors('amount');
    }

    // 7
    public function test_document_type_must_be_a_known_target(): void
    {
        $this->prepare(['document_type' => 'contract'])->assertSessionHasErrors('document_type');
    }

    // 8
    public function test_presentation_must_be_a_valid_mode(): void
    {
        $this->prepare(['presentation' => 'invoice'])->assertSessionHasErrors('presentation');
    }

    // 9 — EIA default: created as a package breakdown with its parameters
    public function test_eia_default_creates_a_package_breakdown_invoice(): void
    {
        $this->prepare(['service' => 'eia', 'amount' => '50000'])->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertNotEmpty($invoice->number);              // a number is consumed now
        $this->assertSame('SMSEA', $invoice->entity_code);
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertCount(7, $invoice->items->first()->scope_items);
        $this->assertEquals(50000, (float) $invoice->items->first()->amount);
    }

    // 10 — EIA toggled to a single consolidated fee (master wording, no scope)
    public function test_eia_consolidated_creates_a_single_fee_invoice(): void
    {
        $this->prepare(['service' => 'eia', 'presentation' => 'consolidated', 'amount' => '50000'])->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertSame('consolidated', $invoice->charge_presentation);
        $this->assertCount(0, $invoice->items->first()->scope_items ?: []);
        $this->assertSame($this->standard('eia')->description, $invoice->items->first()->description);
    }

    // 11 — EPT default: package breakdown, one total, seven parameters
    public function test_ept_default_creates_a_package_breakdown_invoice(): void
    {
        $this->prepare(['service' => 'ept', 'amount' => '30000'])->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertCount(1, $invoice->items);
        $this->assertCount(7, $invoice->items->first()->scope_items);
        $this->assertEquals(30000, (float) $invoice->items->first()->amount);
    }

    // 12 — EPT toggled to a single consolidated fee (its master wording)
    public function test_ept_consolidated_creates_a_single_fee_invoice(): void
    {
        $this->prepare(['service' => 'ept', 'presentation' => 'consolidated', 'amount' => '30000'])->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertSame('consolidated', $invoice->charge_presentation);
        $this->assertSame($this->standard('environmental-parameter-testing')->description, $invoice->items->first()->description);
    }

    // 13 — EPT quotation: one line, package scope attached, opens the quotation view
    public function test_ept_quotation_creates_a_quotation_with_the_package_scope(): void
    {
        $this->prepare(['service' => 'ept', 'amount' => '30000', 'document_type' => 'quotation'])
            ->assertRedirect();

        $quotation = $this->latestQuotation();
        $this->assertNotEmpty($quotation->number);
        $this->assertSame('SMSEA', $quotation->entity_code);
        $this->assertCount(1, $quotation->items);
        $this->assertSame('Environmental Parameter Testing', $quotation->items->first()->description);
        $this->assertCount(7, $quotation->items->first()->scope_items);
        $this->assertEquals(30000, (float) $quotation->items->first()->amount);
    }

    // 14 — EIA consolidated quotation: one clean line, no scope
    public function test_eia_consolidated_quotation_creates_one_clean_line(): void
    {
        $this->prepare(['service' => 'eia', 'presentation' => 'consolidated', 'amount' => '50000', 'document_type' => 'quotation'])
            ->assertRedirect();

        $quotation = $this->latestQuotation();
        $this->assertCount(1, $quotation->items);
        $this->assertSame('Environmental Impact Assessment', $quotation->items->first()->description);
        $this->assertCount(0, $quotation->items->first()->scope_items ?: []);
    }

    // 15 — one prepare consumes exactly one document
    public function test_preparing_creates_exactly_one_document(): void
    {
        $this->prepare()->assertRedirect();

        $this->assertSame(1, ProformaInvoice::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Quotation::query()->withoutGlobalScopes()->count());
    }

    // 16 — entity is forced to SMSEA even from another session entity
    public function test_entity_is_forced_to_smsea_even_from_another_session_entity(): void
    {
        $eidikosId = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->value('id');

        $this->actingAs($this->user)
            ->withSession(['business_entity_id' => $eidikosId])
            ->post(route('quick-env.prepare'), [
                'client_id' => $this->client()->id, 'service' => 'eia', 'amount' => '50000', 'document_type' => 'proforma_invoice',
            ])->assertRedirect();

        $this->assertSame('SMSEA', $this->latestInvoice()->entity_code);
    }

    // 17 — default SMSEA bank resolved server-side and stamped on the document
    public function test_default_smsea_bank_is_resolved_server_side(): void
    {
        $bank = $this->smseaBank();
        $this->prepare()->assertRedirect();

        $this->assertEquals($bank->id, $this->latestInvoice()->bank_account_id);
    }

    // 18 — a bank from another entity is rejected, the SMSEA default is used
    public function test_a_bank_from_another_entity_is_rejected(): void
    {
        $default = $this->smseaBank();
        $eidikosId = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->value('id');
        $foreign = BankAccount::query()->forEntity($eidikosId)->first();
        $this->assertNotNull($foreign);

        $this->prepare(['bank_account_id' => $foreign->id])->assertRedirect();

        $this->assertEquals($default->id, $this->latestInvoice()->bank_account_id);
    }

    // 19 — with no SMSEA bank the document is still created (bank added later for PDF)
    public function test_missing_smsea_bank_still_creates_the_document(): void
    {
        $this->prepare()->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertNotEmpty($invoice->number);
        $this->assertNull($invoice->bank_account_id);
    }

    // 20 — the master records are never duplicated
    public function test_preparing_never_duplicates_the_master_records(): void
    {
        $before = Standard::query()->count();
        $this->prepare(['service' => 'ept']);
        $this->assertSame($before, Standard::query()->count());
    }

    // 21 — advanced options flow onto the created invoice; currency defaults to BDT
    public function test_advanced_options_flow_onto_the_document(): void
    {
        $this->smseaBank();

        // Minimal request keeps the BDT default (stored as the base currency).
        $this->prepare(['service' => 'eia'])->assertRedirect();
        $this->assertContains($this->latestInvoice()->currency, [null, 'BDT'], true);

        // Advanced values are carried through to the saved invoice.
        $this->prepare([
            'service' => 'ept', 'amount' => '1000',
            'currency' => 'USD', 'conversion_rate' => '118',
            'site_name' => 'Bhaluka Plant', 'reference_no' => 'REF-9',
            'vat_treatment' => 'add', 'vat_rate' => '15',
        ])->assertRedirect();

        $invoice = $this->latestInvoice();
        $this->assertSame('USD', $invoice->currency);
        $this->assertSame('Bhaluka Plant', $invoice->site_name);
        $this->assertSame('REF-9', $invoice->reference_no);
        $this->assertSame('add', $invoice->vat_treatment);
    }

    private function setDefaults(?string $conversion, ?string $vat): void
    {
        app(CurrentEntity::class)->use($this->smseaId());
        Setting::current()->forceFill(array_filter([
            'default_conversion_rate' => $conversion,
            'quotation_vat_rate' => $vat,
        ], fn ($v) => $v !== null))->save();
    }

    private function smseaSettings(): Setting
    {
        app(CurrentEntity::class)->use($this->smseaId());

        return Setting::current();
    }

    // 22 — the index prefills the saved conversion / VAT defaults
    public function test_index_prefills_the_saved_rate_defaults(): void
    {
        $this->setDefaults('118', '15');

        $this->actingAs($this->user)->get(route('quick-env.index'))->assertOk()
            ->assertSee('value="118"', false)
            ->assertSee('value="15"', false);
    }

    // 23 — ticking "set as default" persists a new conversion rate
    public function test_ticking_set_default_persists_the_conversion_rate(): void
    {
        $this->prepare(['service' => 'ept', 'currency' => 'USD', 'conversion_rate' => '120', 'set_default_conversion_rate' => '1'])
            ->assertRedirect();

        $this->assertEquals(120, (float) $this->smseaSettings()->default_conversion_rate);
    }

    // 24 — ticking "set as default" persists a new VAT rate
    public function test_ticking_set_default_persists_the_vat_rate(): void
    {
        $this->prepare(['service' => 'ept', 'vat_rate' => '7.5', 'set_default_vat_rate' => '1'])
            ->assertRedirect();

        $this->assertEquals(7.5, (float) $this->smseaSettings()->quotation_vat_rate);
    }

    // 25 — a stored conversion default is applied when the field is left blank
    public function test_stored_conversion_default_is_applied_when_blank(): void
    {
        $this->setDefaults('118', null);

        $this->prepare(['service' => 'ept', 'currency' => 'USD', 'amount' => '1000'])->assertRedirect();

        $this->assertEquals(118, (float) $this->latestInvoice()->conversion_rate);
    }

    // 26 — without the tick, the stored default is never overwritten
    public function test_default_is_unchanged_without_the_tick(): void
    {
        $this->setDefaults('118', null);

        $this->prepare(['service' => 'ept', 'currency' => 'USD', 'conversion_rate' => '999'])->assertRedirect();

        $this->assertEquals(118, (float) $this->smseaSettings()->default_conversion_rate);
    }

    // 27 — new invoices carry the current four-point SMSEA payment terms
    public function test_created_invoice_uses_the_updated_payment_terms(): void
    {
        $this->prepare(['service' => 'eia'])->assertRedirect();

        $this->assertStringContainsString('100% advance payment is required', $this->latestInvoice()->payment_terms);
    }
}
