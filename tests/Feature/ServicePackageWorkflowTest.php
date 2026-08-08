<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePackageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_service_still_works_as_flat_commercial_line(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $service = $this->service('Environmental Impact Assessment');

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'separate',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 30000,
        ]]))->assertRedirect();

        $item = Quotation::query()->firstOrFail()->items()->first();
        $this->assertSame('separate', $item->pricing_mode);
        $this->assertSame([], $item->scope_items);
        $this->assertSame('30000.00', $item->amount);
    }

    public function test_bundle_package_snapshots_components_in_order_without_double_counting(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $package = $this->parameterPackage();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $package->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 30000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $item = $quotation->items()->first();
        $this->assertSame('30000.00', $quotation->total);
        $this->assertSame('consolidated', $item->pricing_mode);
        $this->assertSame([
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
        ], $item->scope_items);
    }

    public function test_bundle_component_can_be_removed_for_one_document_only(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $package = $this->parameterPackage();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $package->id,
            'description' => '',
            'scope_items' => "Ambient Air Quality Assessment\nNoise Level Assessment",
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 30000,
        ]]))->assertRedirect();

        $this->assertSame([
            'Ambient Air Quality Assessment',
            'Noise Level Assessment',
        ], Quotation::query()->firstOrFail()->items()->first()->scope_items);
        $this->assertSame(3, $package->components()->count());
    }

    public function test_eia_and_parameter_package_can_be_priced_separately(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $eia = $this->service('Environmental Impact Assessment');
        $parameters = $this->parameterPackage();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $eia->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'separate',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 30000,
        ], [
            'service_id' => $parameters->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 20000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('50000.00', $quotation->total);
        $this->assertCount(2, $quotation->items);
    }

    public function test_eia_package_can_be_one_consolidated_price(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $package = $this->service('Environmental Impact Assessment Package', Service::TYPE_BUNDLE, [
            'Environmental Impact Assessment',
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
        ]);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $package->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 50000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('50000.00', $quotation->total);
        $this->assertSame([
            'Environmental Impact Assessment',
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
        ], $quotation->items()->first()->scope_items);
    }

    public function test_consolidated_service_uses_default_commercial_description(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $emp = $this->service('Environmental Management Plan', Service::TYPE_CONSOLIDATED);
        $emp->update([
            'quotation_scope' => 'Environmental Management Plan (EMP) - inclusive of document review, onsite assessment, data collection and final report preparation.',
        ]);

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $emp->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 45000,
        ]]))->assertRedirect();

        $item = Quotation::query()->firstOrFail()->items()->first();
        $this->assertStringContainsString('inclusive of document review', $item->description);
        $this->assertSame('45000.00', $item->amount);
    }

    public function test_package_snapshot_and_duplicate_preserve_old_scope(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $package = $this->parameterPackage();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $package->id,
            'description' => '',
            'scope_items' => '',
            'pricing_mode' => 'consolidated',
            'unit' => '',
            'quantity' => 1,
            'unit_rate' => 30000,
        ]]))->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $package->components()->create(['name' => 'Light Level Assessment', 'sort_order' => 4, 'is_active' => true]);
        $this->actingAs($user)->post(route('quotations.duplicate', $quotation))->assertRedirect();

        $copy = Quotation::query()->latest('id')->first();
        $this->assertSame([
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
        ], $copy->items()->first()->scope_items);
    }

    public function test_proforma_invoice_pdf_supports_package_scope(): void
    {
        [$user, $client, $bank] = $this->setupData();
        $package = $this->parameterPackage();

        $this->actingAs($user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $package->id,
                'description' => '',
                'scope_items' => '',
                'pricing_mode' => 'consolidated',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 30000,
            ]],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->firstOrFail();
        $this->assertSame('30000.00', $invoice->total);
        $this->assertSame('Ambient Air Quality Assessment', $invoice->items()->first()->scope_items[0]);
        $this->actingAs($user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    private function payload(Client $client, BankAccount $bank, array $items): array
    {
        return [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => $items,
        ];
    }

    private function parameterPackage(): Service
    {
        return $this->service('Environmental Parameter Assessment', Service::TYPE_BUNDLE, [
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
        ]);
    }

    private function service(string $name, string $type = Service::TYPE_STANDALONE, array $components = []): Service
    {
        $service = Service::query()->create([
            'name' => $name,
            'short_name' => $name,
            'service_type' => $type,
            'default_description' => $name,
            'quotation_scope' => $name,
            'invoice_description' => $name,
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        foreach ($components as $index => $component) {
            $service->components()->create([
                'name' => $component,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        return $service->refresh();
    }

    private function setupData(): array
    {
        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'default_payment_terms' => 'Default terms.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);

        $user = User::factory()->create();
        $client = Client::query()->create([
            'company_name' => 'Package Client Ltd.',
            'address' => 'Dhaka, Bangladesh',
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Package Bank',
            'account_number' => '123456',
            'is_active' => true,
        ]);

        return [$user, $client, $bank];
    }
}
