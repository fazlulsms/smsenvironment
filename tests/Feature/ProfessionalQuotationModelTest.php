<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalQuotationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_quotation_snapshots_professional_terms_and_acceptance(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 30000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('exclusive', $quotation->vat_treatment);
        $this->assertTrue($quotation->include_acceptance);
        $this->assertStringContainsString('Scope of Service', $quotation->terms_conditions);
        $this->assertStringContainsString('authorize SMS Environmental Alliance', $quotation->acceptance_text);
        $this->assertStringContainsString('regulatory requirements', $quotation->intro_text);
    }

    public function test_vat_exclusive_keeps_payable_total_as_net_amount(): void
    {
        [$user, $client, $bank, $service] = $this->setupData(['quotation_vat_treatment' => 'exclusive']);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 50000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('0.00', $quotation->vat_amount);
        $this->assertSame('50000.00', $quotation->total);
    }

    public function test_vat_included_keeps_payable_total_without_extra_vat(): void
    {
        [$user, $client, $bank, $service] = $this->setupData(['quotation_vat_treatment' => 'included']);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 50000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('included', $quotation->vat_treatment);
        $this->assertSame('0.00', $quotation->vat_amount);
        $this->assertSame('50000.00', $quotation->total);
    }

    public function test_vat_add_uses_configured_rate(): void
    {
        [$user, $client, $bank, $service] = $this->setupData([
            'quotation_vat_treatment' => 'add',
            'quotation_vat_rate' => 15,
        ]);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 50000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('7500.00', $quotation->vat_amount);
        $this->assertSame('57500.00', $quotation->total);
    }

    public function test_vat_decimal_calculation_is_rounded_deterministically(): void
    {
        [$user, $client, $bank, $service] = $this->setupData([
            'quotation_vat_treatment' => 'add',
            'quotation_vat_rate' => 7.5,
        ]);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 33333.33,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('2500.00', $quotation->vat_amount);
        $this->assertSame('35833.33', $quotation->total);
    }

    public function test_vat_not_applicable_does_not_add_vat(): void
    {
        [$user, $client, $bank, $service] = $this->setupData(['quotation_vat_treatment' => 'not_applicable']);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 2,
            'unit_rate' => 10000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('not_applicable', $quotation->vat_treatment);
        $this->assertSame('0.00', $quotation->vat_amount);
        $this->assertSame('20000.00', $quotation->total);
    }

    public function test_document_level_vat_override_wins_over_settings(): void
    {
        [$user, $client, $bank, $service] = $this->setupData(['quotation_vat_treatment' => 'exclusive']);

        $payload = $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 10000,
        ]]);
        $payload['vat_treatment'] = 'add';
        $payload['vat_rate'] = 5;

        $this->actingAs($user)->post(route('quotations.store'), $payload)->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('add', $quotation->vat_treatment);
        $this->assertSame('500.00', $quotation->vat_amount);
        $this->assertSame('10500.00', $quotation->total);
    }

    public function test_acceptance_can_be_excluded(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $payload = $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 10000,
        ]]);
        $payload['include_acceptance'] = false;

        $this->actingAs($user)->post(route('quotations.store'), $payload)->assertRedirect();

        $this->assertFalse(Quotation::query()->firstOrFail()->include_acceptance);
    }

    public function test_tax_notes_are_snapshotted_on_quotation(): void
    {
        [$user, $client, $bank, $service] = $this->setupData([
            'quotation_vat_note' => 'VAT custom note.',
            'quotation_ait_note' => 'AIT custom note.',
        ]);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 10000,
        ]]))->assertRedirect();

        Setting::current()->update(['quotation_vat_note' => 'Changed later.']);
        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('VAT custom note.', $quotation->vat_note);
        $this->assertSame('AIT custom note.', $quotation->ait_note);
    }

    public function test_multiple_services_and_adjustment_are_deterministic(): void
    {
        [$user, $client, $bank, $service] = $this->setupData(['quotation_vat_treatment' => 'add', 'quotation_vat_rate' => 10]);
        $second = Service::query()->create([
            'name' => 'Environmental Management Plan',
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        $payload = $this->payload($client, $bank, [
            ['service_id' => $service->id, 'quantity' => 1, 'unit_rate' => 20000],
            ['service_id' => $second->id, 'quantity' => 1, 'unit_rate' => 30000],
        ]);
        $payload['adjustment'] = -5000;

        $this->actingAs($user)->post(route('quotations.store'), $payload)->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();

        $this->assertSame('50000.00', $quotation->subtotal);
        $this->assertSame('-5000.00', $quotation->adjustment);
        $this->assertSame('4500.00', $quotation->vat_amount);
        $this->assertSame('49500.00', $quotation->total);
    }

    public function test_historical_quotation_without_new_fields_renders_safely(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => 'OLD-QT-001',
            'date' => '2026-08-08',
            'client_snapshot' => $client->only(['company_name', 'contact_person', 'designation', 'email', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'subject' => 'Historical quotation',
            'intro_text' => 'Old intro.',
            'subtotal' => 1000,
            'adjustment' => 0,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'service_id' => $service->id,
            'description' => 'Old service',
            'unit' => 'Job',
            'quantity' => 1,
            'unit_rate' => 1000,
            'amount' => 1000,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();
    }

    private function payload(Client $client, BankAccount $bank, array $items): array
    {
        return [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'adjustment' => 0,
            'items' => array_map(fn (array $item) => [
                'service_id' => $item['service_id'],
                'description' => '',
                'scope_items' => $item['scope_items'] ?? '',
                'unit' => 'Job',
                'quantity' => $item['quantity'],
                'unit_rate' => $item['unit_rate'],
            ], $items),
        ];
    }

    private function setupData(array $settings = []): array
    {
        $user = User::factory()->create();
        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Consultant',
            'default_payment_terms' => 'Payment Requirement: 100% advance.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            'quotation_ait_note' => 'VAT and AIT shall be treated as applicable.',
            'quotation_include_acceptance' => true,
            ...$settings,
        ]);
        $client = Client::query()->create([
            'company_name' => 'Phase 1.6 Client Ltd.',
            'contact_person' => 'Client Person',
            'designation' => 'Manager',
            'email' => 'client@example.com',
            'address' => 'Dhaka, Bangladesh',
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Environmental Impact Assessment',
            'quotation_scope' => 'Document review, site assessment, data collection, impact assessment and reporting.',
            'compliance_note' => "Bangladesh Environmental Conservation Rules\nDepartment of Environment requirements",
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        return [$user, $client, $bank, $service];
    }
}
