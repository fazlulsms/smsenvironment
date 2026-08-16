<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\Standard;
use App\Models\User;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Quick Environmental Document shortcut: a faster INPUT path that resolves the
 * two approved SMSEA services against existing master data and prefills the normal
 * create form. It must never save, number, email or duplicate anything.
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

    // 1
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('quick-env.index'))->assertRedirect(route('login'));
    }

    // 2
    public function test_index_renders_both_services_and_the_fixed_entity(): void
    {
        $this->actingAs($this->user)->get(route('quick-env.index'))->assertOk()
            ->assertSee('Quick Environmental Document')
            ->assertSee('Environmental Impact Assessment')
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('Prepare Document');
    }

    // 3
    public function test_index_preselects_the_service_from_the_query(): void
    {
        $this->actingAs($this->user)->get(route('quick-env.index', ['service' => 'ept']))->assertOk()
            ->assertSee('value="ept" data-default-presentation="component_breakdown" checked', false);

        // Default (no query) preselects EIA.
        $this->actingAs($this->user)->get(route('quick-env.index'))->assertOk()
            ->assertSee('value="eia" data-default-presentation="consolidated" checked', false);
    }

    // 4
    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->user)->post(route('quick-env.prepare'), [])
            ->assertSessionHasErrors(['client_id', 'service', 'amount', 'document_type']);
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
    public function test_eia_proforma_prefills_a_consolidated_charge(): void
    {
        $client = $this->client();
        $response = $this->prepare(['client_id' => $client->id, 'service' => 'eia', 'amount' => '50000'])
            ->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame('consolidated', $old['charge_presentation']);
        $this->assertSame('Environmental Impact Assessment', $old['charge_title']);
        $this->assertEquals(50000.0, $old['consolidated']['amount']);
        // EIA is a single charge — no standard attached (no redundant one-line scope).
        $this->assertArrayNotHasKey('standards', $old);
    }

    // 9
    public function test_eia_charge_wording_comes_from_the_master_record(): void
    {
        $response = $this->prepare(['service' => 'eia']);
        $old = $response->getSession()->get('_old_input');

        $this->assertSame($this->standard('eia')->description, $old['consolidated']['description']);
        $this->assertStringContainsString('Professional services for Environmental Impact Assessment', $old['consolidated']['description']);
    }

    // 10
    public function test_ept_proforma_attaches_the_package_and_breaks_down_as_one_total(): void
    {
        $ept = $this->standard('environmental-parameter-testing');
        $response = $this->prepare(['service' => 'ept', 'amount' => '30000'])
            ->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame('component_breakdown', $old['charge_presentation']);
        $this->assertSame('Environmental Parameter Testing', $old['charge_title']);
        $this->assertSame([$ept->id], $old['standards']);
        // One consolidated amount — never separately priced component lines.
        $this->assertEquals(30000.0, $old['breakdown']['amount']);
        $this->assertArrayNotHasKey('components', $old['breakdown']);
    }

    // 11
    public function test_ept_quotation_folds_into_one_itemized_row_with_the_package(): void
    {
        $ept = $this->standard('environmental-parameter-testing');
        $response = $this->prepare(['service' => 'ept', 'amount' => '30000', 'document_type' => 'quotation'])
            ->assertRedirect(route('quotations.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame([$ept->id], $old['standards']);
        $this->assertCount(1, $old['items']);
        $this->assertSame('Environmental Parameter Testing', $old['items'][0]['description']);
        $this->assertEquals(30000.0, $old['items'][0]['amount']);
        // Quotations are itemized-only — no invoice presentation leaks in.
        $this->assertArrayNotHasKey('charge_presentation', $old);
    }

    // 12
    public function test_eia_quotation_folds_into_one_row_without_a_standard(): void
    {
        $response = $this->prepare(['service' => 'eia', 'amount' => '50000', 'document_type' => 'quotation'])
            ->assertRedirect(route('quotations.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertCount(1, $old['items']);
        $this->assertSame('Environmental Impact Assessment', $old['items'][0]['description']);
        $this->assertArrayNotHasKey('standards', $old);
    }

    // 13
    public function test_preparing_never_issues_a_document_or_consumes_a_number(): void
    {
        $invoices = ProformaInvoice::query()->withoutGlobalScopes()->count();
        $quotes = Quotation::query()->withoutGlobalScopes()->count();

        $this->prepare()->assertRedirect(route('proforma-invoices.create'));

        $this->assertSame($invoices, ProformaInvoice::query()->withoutGlobalScopes()->count());
        $this->assertSame($quotes, Quotation::query()->withoutGlobalScopes()->count());
    }

    // 14
    public function test_preparing_never_creates_a_client(): void
    {
        $before = Client::query()->count() + 1; // +1 for the client created inside prepare()
        $this->prepare();
        $this->assertSame($before, Client::query()->count());
    }

    // 15
    public function test_preparing_never_duplicates_the_master_records(): void
    {
        $before = Standard::query()->count();
        $this->prepare(['service' => 'ept']);
        $this->assertSame($before, Standard::query()->count());
    }

    // 16
    public function test_entity_is_forced_to_smsea_even_from_another_session_entity(): void
    {
        $eidikosId = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->value('id');

        $response = $this->actingAs($this->user)
            ->withSession(['business_entity_id' => $eidikosId])
            ->post(route('quick-env.prepare'), [
                'client_id' => $this->client()->id, 'service' => 'eia', 'amount' => '50000', 'document_type' => 'proforma_invoice',
            ])->assertRedirect(route('proforma-invoices.create'));

        $this->assertSame($this->smseaId(), (int) $response->getSession()->get('business_entity_id'));
    }

    // 17
    public function test_default_smsea_bank_is_resolved_server_side(): void
    {
        $bank = $this->smseaBank();
        $response = $this->prepare();

        $old = $response->getSession()->get('_old_input');
        $this->assertEquals($bank->id, $old['bank_account_id']);
    }

    // 18
    public function test_a_bank_from_another_entity_is_rejected(): void
    {
        $default = $this->smseaBank();
        $eidikosId = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->value('id');
        $foreign = BankAccount::query()->forEntity($eidikosId)->first();
        $this->assertNotNull($foreign);

        $response = $this->prepare(['bank_account_id' => $foreign->id]);

        $old = $response->getSession()->get('_old_input');
        // The cross-entity bank is dropped and the SMSEA default is used instead.
        $this->assertEquals($default->id, $old['bank_account_id']);
    }

    // 19
    public function test_missing_smsea_bank_still_prepares_and_warns(): void
    {
        $response = $this->prepare()->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertArrayNotHasKey('bank_account_id', $old);
        $this->assertStringContainsString('select a bank', $response->getSession()->get('status'));
    }

    // 21 — toggle override: EIA shown as a package (breakdown) attaches its scope
    public function test_eia_can_be_prepared_as_a_package_breakdown(): void
    {
        $eiaPackage = $this->standard('environmental-impact-assessment');
        $response = $this->prepare(['service' => 'eia', 'presentation' => 'component_breakdown', 'amount' => '50000'])
            ->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame('component_breakdown', $old['charge_presentation']);
        $this->assertSame([$eiaPackage->id], $old['standards']);      // package variant (with scope) attached
        $this->assertEquals(50000.0, $old['breakdown']['amount']);
        $this->assertArrayNotHasKey('consolidated', $old);
    }

    // 22 — toggle override: EPT shown as one consolidated fee (no scope, master wording)
    public function test_ept_can_be_prepared_as_a_consolidated_fee(): void
    {
        $response = $this->prepare(['service' => 'ept', 'presentation' => 'consolidated', 'amount' => '30000'])
            ->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame('consolidated', $old['charge_presentation']);
        $this->assertArrayNotHasKey('standards', $old);               // no package attached → no scope list
        $this->assertSame($this->standard('environmental-parameter-testing')->description, $old['consolidated']['description']);
        $this->assertEquals(30000.0, $old['consolidated']['amount']);
    }

    // 23 — presentation must be a known mode when supplied
    public function test_presentation_must_be_a_valid_mode(): void
    {
        $this->prepare(['presentation' => 'invoice'])->assertSessionHasErrors('presentation');
    }

    // 24 — EIA-as-package quotation carries the package standard on its row
    public function test_eia_package_quotation_attaches_the_standard(): void
    {
        $eiaPackage = $this->standard('environmental-impact-assessment');
        $response = $this->prepare(['service' => 'eia', 'presentation' => 'component_breakdown', 'document_type' => 'quotation', 'amount' => '50000'])
            ->assertRedirect(route('quotations.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertSame([$eiaPackage->id], $old['standards']);
        $this->assertSame('Environmental Impact Assessment', $old['items'][0]['description']);
    }

    // 20
    public function test_advanced_options_flow_through_and_currency_defaults_to_bdt(): void
    {
        // Minimal request: currency omitted so the create form keeps its BDT default.
        $plain = $this->prepare()->getSession()->get('_old_input');
        $this->assertArrayNotHasKey('currency', $plain);

        // Advanced values are carried into the invoice prefill.
        $response = $this->prepare([
            'service' => 'ept', 'amount' => '1000',
            'currency' => 'USD', 'conversion_rate' => '118',
            'site_name' => 'Bhaluka Plant', 'reference_no' => 'REF-9',
            'vat_treatment' => 'add', 'vat_rate' => '15',
        ]);
        $old = $response->getSession()->get('_old_input');
        $this->assertSame('USD', $old['currency']);
        $this->assertEquals(118, $old['conversion_rate']);
        $this->assertSame('Bhaluka Plant', $old['site_name']);
        $this->assertSame('REF-9', $old['reference_no']);
        $this->assertSame('add', $old['vat_treatment']);
        $this->assertEquals(15, $old['vat_rate']);
    }
}
