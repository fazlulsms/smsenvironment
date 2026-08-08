<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\QuotationVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_verification_is_generated_for_new_quotation_with_snapshot_payload(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 50000,
        ]]))->assertRedirect();

        $quotation = Quotation::with('items')->firstOrFail();
        $verification = app(QuotationVerificationService::class);
        $payload = $verification->payloadText($quotation);

        $this->assertSame(QuotationVerificationService::PAYLOAD_VERSION, $quotation->verification_payload_version);
        $this->assertNotEmpty($quotation->verification_id);
        $this->assertNotEmpty($quotation->verification_signature);
        $this->assertStringContainsString('Reference: '.$quotation->number, $payload);
        $this->assertStringContainsString('Date: 2026-08-08', $payload);
        $this->assertStringContainsString('Client: Phase 1.6 Verification Client Ltd.', $payload);
        $this->assertStringContainsString('Address: Long Client Address, Dhaka, Bangladesh', $payload);
        $this->assertStringContainsString('Environmental Impact Assessment', $payload);
        $this->assertStringContainsString('Net Amount: BDT 50,000.00', $payload);
        $this->assertStringContainsString('VAT Treatment: Exclusive', $payload);
        $this->assertStringContainsString('Total Payable: BDT 50,000.00', $payload);
        $this->assertStringContainsString('Verification ID: '.$quotation->verification_id, $payload);
    }

    public function test_vat_amount_multiple_services_long_client_and_package_are_included(): void
    {
        [$user, $client, $bank, $service] = $this->setupData([
            'quotation_vat_treatment' => 'add',
            'quotation_vat_rate' => 15,
        ]);
        $package = $this->packageService();

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 30000,
        ], [
            'service_id' => $package->id,
            'quantity' => 1,
            'unit_rate' => 20000,
        ]]))->assertRedirect();

        $quotation = Quotation::with('items')->firstOrFail();
        $payload = app(QuotationVerificationService::class)->payloadText($quotation);

        $this->assertStringContainsString('Phase 1.6 Verification Client Ltd.', $payload);
        $this->assertStringContainsString('Environmental Impact Assessment', $payload);
        $this->assertStringContainsString('Environmental Parameter Assessment Package', $payload);
        $this->assertStringContainsString('Net Amount: BDT 50,000.00', $payload);
        $this->assertStringContainsString('VAT @ 15%: BDT 7,500.00', $payload);
        $this->assertStringContainsString('Total Payable: BDT 57,500.00', $payload);
    }

    public function test_signature_is_deterministic_and_changes_when_important_fields_change(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $quotation = $this->createQuotation($user, $client, $bank, $service, 50000);
        $verification = app(QuotationVerificationService::class);

        $signature = $verification->signature($quotation);
        $this->assertSame($signature, $verification->signature($quotation->fresh('items')));

        $amountChanged = $quotation->replicate(['verification_payload_version', 'verification_id', 'verification_signature']);
        $amountChanged->subtotal = 80000;
        $amountChanged->total = 80000;
        $amountChanged->setRelation('items', $quotation->items);

        $clientChanged = $quotation->replicate(['verification_payload_version', 'verification_id', 'verification_signature']);
        $clientChanged->client_snapshot = [
            ...$quotation->client_snapshot,
            'company_name' => 'Altered Client Ltd.',
        ];
        $clientChanged->setRelation('items', $quotation->items);

        $this->assertNotSame($signature, $verification->signature($amountChanged));
        $this->assertNotSame($signature, $verification->signature($clientChanged));
    }

    public function test_duplicate_receives_new_verification_and_historical_snapshot_stays_stable(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $quotation = $this->createQuotation($user, $client, $bank, $service, 50000);
        $originalSignature = $quotation->verification_signature;

        $client->update(['company_name' => 'Changed Current Client Ltd.', 'address' => 'Changed current address']);

        $this->assertSame($originalSignature, app(QuotationVerificationService::class)->signature($quotation->fresh('items')));

        $this->actingAs($user)->post(route('quotations.duplicate', $quotation))->assertRedirect();
        $copy = Quotation::with('items')->latest('id')->firstOrFail();

        $this->assertNotSame($quotation->number, $copy->number);
        $this->assertNotSame($quotation->verification_id, $copy->verification_id);
        $this->assertNotSame($quotation->verification_signature, $copy->verification_signature);
    }

    public function test_existing_quotation_without_verification_data_and_pdf_view_qr_are_supported(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => 'OLD-QT-VERIFY-001',
            'date' => '2026-08-08',
            'client_snapshot' => $client->only(['company_name', 'contact_person', 'designation', 'email', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'subject' => 'Historical quotation',
            'intro_text' => 'Old intro.',
            'subtotal' => 1000,
            'adjustment' => 0,
            'vat_treatment' => 'exclusive',
            'vat_amount' => 0,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'service_id' => $service->id,
            'description' => 'Historical Environmental Service',
            'unit' => 'Job',
            'quantity' => 1,
            'unit_rate' => 1000,
            'amount' => 1000,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();

        $quotation = $quotation->fresh('items');
        $qr = app(QuotationVerificationService::class)->qrDataUri($quotation);
        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'settings' => $quotation->settings_snapshot,
            'client' => $quotation->client_snapshot,
            'bank' => $quotation->bank_snapshot,
            'amountInWords' => 'One Thousand Taka Only',
            'verificationQr' => $qr,
        ])->render();

        $this->assertNotEmpty($quotation->verification_id);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('verification-qr', $html);
    }

    private function createQuotation(User $user, Client $client, BankAccount $bank, Service $service, float $rate): Quotation
    {
        $this->actingAs($user)->post(route('quotations.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => $rate,
        ]]))->assertRedirect();

        return Quotation::with('items')->firstOrFail();
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
                'scope_items' => '',
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
            'quotation_include_acceptance' => true,
            ...$settings,
        ]);
        $client = Client::query()->create([
            'company_name' => 'Phase 1.6 Verification Client Ltd.',
            'contact_person' => 'Client Person',
            'designation' => 'Manager',
            'email' => 'client@example.com',
            'address' => 'Long Client Address, Dhaka, Bangladesh',
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Environmental Impact Assessment',
            'short_name' => 'Environmental Impact Assessment',
            'quotation_scope' => 'Environmental Impact Assessment',
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        return [$user, $client, $bank, $service];
    }

    private function packageService(): Service
    {
        $package = Service::query()->create([
            'name' => 'Environmental Parameter Assessment Package',
            'short_name' => 'Environmental Parameter Assessment Package',
            'service_type' => Service::TYPE_BUNDLE,
            'quotation_scope' => 'Environmental Parameter Assessment Package',
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        foreach (['Ambient Air Quality Assessment', 'Stack Emission Test', 'Noise Level Assessment'] as $index => $name) {
            $package->components()->create(['name' => $name, 'sort_order' => $index + 1, 'is_active' => true]);
        }

        return $package->refresh();
    }
}
