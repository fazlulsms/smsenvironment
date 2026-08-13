<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\ChargeParticular;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentEntity;
use Database\Seeders\ChargeParticularSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeParticularLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(ChargeParticularSeeder::class);
    }

    public function test_seeder_is_idempotent_and_dedupes_aliases(): void
    {
        $count = ChargeParticular::query()->count();
        $this->seed(ChargeParticularSeeder::class);
        $this->assertSame($count, ChargeParticular::query()->count());

        // "Administrative Fee" is an alias, not its own record.
        $this->assertFalse(ChargeParticular::query()->where('name', 'Administrative Fee')->exists());
        $admin = ChargeParticular::query()->where('name', 'Administration Fee')->firstOrFail();
        $this->assertStringContainsString('administrative', (string) $admin->search_keywords);
    }

    public function test_forgiving_search(): void
    {
        $names = fn (string $t) => ChargeParticular::query()->search($t)->pluck('name');

        $this->assertTrue($names('travel')->contains('Travel & Operational Cost'));
        $this->assertTrue($names('admin')->contains('Administration Fee'));            // via alias keyword
        $this->assertTrue($names('license')->contains('Registration & License Fee'));
        $this->assertTrue($names('SLCP')->contains('SLCP Verification Fee (Step-03)'));
        $this->assertTrue($names('Higg')->contains('Higg FEM Verification Fee'));
        $this->assertTrue($names('BCP')->contains('Better Cotton Platform (BCP) Registration Support Fee')); // acronym
        $this->assertTrue($names('documentation')->contains('Documentation Support Fee'));
    }

    public function test_library_is_global_across_entities(): void
    {
        $before = ChargeParticular::query()->count();
        $eco = BusinessEntity::query()->where('entity_code', 'ECOVERITAS')->firstOrFail();
        app(CurrentEntity::class)->use($eco->id);
        // No entity scoping — the same rows are visible under any entity.
        $this->assertSame($before, ChargeParticular::query()->count());
    }

    public function test_itemized_form_has_one_particular_field_and_no_scope_textarea(): void
    {
        $entity = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->firstOrFail();
        app(CurrentEntity::class)->use($entity->id);
        Setting::current();

        foreach (['proforma-invoices.create', 'quotations.create'] as $route) {
            $html = $this->actingAs($this->user)->get(route($route))->assertOk()->getContent();
            $this->assertStringNotContainsString('data-scope-items', $html, $route);       // removed duplicate field
            $this->assertStringNotContainsString('Optional', $html, $route);              // no "Optional Including scope" hint
            $this->assertStringContainsString('data-cpi-search', $html, $route);          // itemized library helper present
        }

        // Breakdown mode exposes the searchable Charge Particular widget (invoice).
        $invoiceHtml = $this->actingAs($this->user)->get(route('proforma-invoices.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-cp-widget', $invoiceHtml);
        $this->assertStringContainsString('Search the library or type a particular', $invoiceHtml);
    }

    public function test_breakdown_snapshot_is_independent_of_master_wording(): void
    {
        $entity = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->firstOrFail();
        app(CurrentEntity::class)->use($entity->id);
        Setting::current();
        $client = Client::query()->create(['company_name' => 'Snap Ltd.', 'address' => 'Dhaka']);
        $bank = BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'The City Bank Limited', 'account_number' => '1401991721001', 'is_active' => true, 'is_default' => true]);
        $cat = ServiceCategory::query()->create(['code' => 'X', 'name' => 'X', 'selection_label' => 'Select', 'active' => true, 'display_order' => 1]);
        $std = $cat->standards()->create(['slug' => 'slcp', 'name' => 'SLCP Verification', 'code' => 'SLCP', 'short_name' => 'SLCP', 'active' => true, 'display_order' => 1]);

        // Breakdown components taken from the library wording (as the widget would submit).
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$std->id],
            'charge_presentation' => 'component_breakdown',
            'breakdown' => ['components' => "SLCP Verification Fee (Step-03)\nTravel & Operational Cost\nAdministration Fee", 'amount' => 139130],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame(['SLCP Verification Fee (Step-03)', 'Travel & Operational Cost', 'Administration Fee'], $invoice->items->first()->scope_items);

        // Rename the master particulars — the saved document must not change.
        ChargeParticular::query()->where('name', 'Travel & Operational Cost')->update(['name' => 'Travel, Accommodation & Operational Cost']);
        ChargeParticular::query()->where('name', 'Administration Fee')->update(['name' => 'Admin & Coordination Fee']);

        $this->assertSame(['SLCP Verification Fee (Step-03)', 'Travel & Operational Cost', 'Administration Fee'], $invoice->fresh('items')->items->first()->scope_items);
    }
}
