<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Services\ProformaInvoiceVerificationService;
use Database\Seeders\BankAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProformaInvoiceVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_qr_verification_uses_saved_snapshot_payload(): void
    {
        [$user, $client, $bank, $service] = $this->setupData([
            'quotation_vat_treatment' => 'add',
            'quotation_vat_rate' => 15,
        ]);

        $this->actingAs($user)->post(route('proforma-invoices.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => 10000,
        ]], [
            'vat_treatment' => 'add',
            'vat_rate' => 15,
        ]))->assertRedirect();

        $invoice = ProformaInvoice::with('items')->firstOrFail();
        $verification = app(ProformaInvoiceVerificationService::class);
        $payload = $verification->payloadText($invoice);

        $this->assertSame(ProformaInvoiceVerificationService::PAYLOAD_VERSION, $invoice->verification_payload_version);
        $this->assertNotEmpty($invoice->verification_id);
        $this->assertNotEmpty($invoice->verification_signature);
        $this->assertStringContainsString('PROFORMA INVOICE VERIFICATION', $payload);
        $this->assertStringContainsString('Document: SMS Environmental Alliance Proforma Invoice', $payload);
        $this->assertStringContainsString('Invoice Reference: '.$invoice->number, $payload);
        $this->assertStringContainsString('Invoice Date: 2026-08-09', $payload);
        $this->assertStringContainsString('Client: Maria Attires', $payload);
        $this->assertStringContainsString('Client Address: Chaira, Mushurikhola, Hemayetpur, Savar, Dhaka, Bangladesh', $payload);
        $this->assertStringContainsString('Environmental Impact Assessment Package', $payload);
        $this->assertStringContainsString('Net Amount: BDT 10,000.00', $payload);
        $this->assertStringContainsString('VAT @ 15%: BDT 1,500.00', $payload);
        $this->assertStringContainsString('Total Payable: BDT 11,500.00', $payload);
        $this->assertStringContainsString('Payload Version: '.ProformaInvoiceVerificationService::PAYLOAD_VERSION, $payload);
        $this->assertStringContainsString('Entity: SMSEA', $payload);
    }

    public function test_signature_is_deterministic_changes_with_amount_and_duplicate_gets_new_verification(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $invoice = $this->createInvoice($user, $client, $bank, $service, 10000);
        $verification = app(ProformaInvoiceVerificationService::class);
        $signature = $verification->signature($invoice);

        $this->assertSame($signature, $verification->signature($invoice->fresh('items')));

        $amountChanged = $invoice->replicate(['verification_payload_version', 'verification_id', 'verification_signature']);
        $amountChanged->subtotal = 12000;
        $amountChanged->total = 12000;
        $amountChanged->setRelation('items', $invoice->items);

        $this->assertNotSame($signature, $verification->signature($amountChanged));

        $this->actingAs($user)->post(route('proforma-invoices.duplicate', $invoice))->assertRedirect();
        $copy = ProformaInvoice::with('items')->latest('id')->firstOrFail();

        $this->assertNotSame($invoice->number, $copy->number);
        $this->assertNotSame($invoice->verification_id, $copy->verification_id);
        $this->assertNotSame($invoice->verification_signature, $copy->verification_signature);
    }

    public function test_historical_invoice_without_verification_backfills_from_saved_snapshot_and_renders_pdf(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $invoice = ProformaInvoice::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => 'OLD-PI-VERIFY-001',
            'date' => '2026-08-09',
            'client_snapshot' => $client->only(['company_name', 'contact_person', 'designation', 'email', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'branch', 'account_number', 'swift_code']),
            'settings_snapshot' => Setting::current()->toArray(),
            'charge_for' => 'Historical invoice',
            'payment_terms' => 'Payment by bank transfer.',
            'subtotal' => 1000,
            'adjustment' => 0,
            'total' => 1000,
        ]);
        $invoice->items()->create([
            'service_id' => $service->id,
            'description' => 'Historical Environmental Service',
            'unit' => 'Job',
            'quantity' => 1,
            'unit_rate' => 1000,
            'amount' => 1000,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();

        $invoice = $invoice->fresh('items');
        $qr = app(ProformaInvoiceVerificationService::class)->qrDataUri($invoice);
        $html = view('proforma_invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $invoice->settings_snapshot,
            'client' => $invoice->client_snapshot,
            'bank' => $invoice->bank_snapshot,
            'amountInWords' => 'One Thousand Taka Only',
            'verificationQr' => $qr,
        ])->render();

        $this->assertNotEmpty($invoice->verification_id);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('INVOICE VERIFICATION', $html);
        $this->assertStringContainsString('PROFORMA INVOICE', $html);
        $this->assertSame(1, substr_count($html, 'PROFORMA INVOICE'));
        $this->assertStringContainsString('class="invoice-financial-summary"', $html);
        // QR/verification now lives only in the fixed footer, not an in-body strip.
        $this->assertStringContainsString('class="invoice-footer"', $html);
        $this->assertStringNotContainsString('class="invoice-verification-strip"', $html);
        $this->assertStringNotContainsString('class="financial-verification-table"', $html);
        $this->assertStringContainsString('<ol class="invoice-terms-full">', $html);
        $this->assertStringContainsString('Please mention the Proforma Invoice reference', $html);
        $this->assertStringContainsString('prepared-section', $html);
        $this->assertStringContainsString('Authorized Signature', $html);
    }

    public function test_selected_bank_is_rendered_without_showing_other_accounts(): void
    {
        [$user, $client, $prime, $service] = $this->setupData();
        $mutual = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Mutual Trust Bank',
            'branch' => 'Shah Mokhdum Avenue Branch',
            'account_number' => '1301000014453',
            'swift_code' => 'MTBLBDDH',
            'is_active' => true,
        ]);
        $invoice = $this->createInvoice($user, $client, $mutual, $service, 10000);
        $qr = app(ProformaInvoiceVerificationService::class)->qrDataUri($invoice);
        $html = view('proforma_invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $invoice->settings_snapshot,
            'client' => $invoice->client_snapshot,
            'bank' => $invoice->bank_snapshot,
            'amountInWords' => 'Ten Thousand Taka Only',
            'verificationQr' => $qr,
        ])->render();

        $this->assertStringContainsString('Mutual Trust Bank', $html);
        $this->assertStringContainsString('1301000014453', $html);
        $this->assertStringNotContainsString('Prime Bank Ltd.', $html);
        $this->assertStringNotContainsString('2170316017001', $html);
        $this->assertNotSame($prime->id, $mutual->id);
    }

    public function test_development_bank_snapshot_is_replaced_by_selected_real_bank_for_pdf(): void
    {
        [$user, $client, $bank, $service] = $this->setupData();
        $invoice = $this->createInvoice($user, $client, $bank, $service, 10000);
        $invoice->forceFill([
            'bank_snapshot' => [
                'beneficiary_name' => 'Local Verification Bank',
                'bank_name' => 'Local Verification Bank',
                'branch' => 'Uttara',
                'account_number' => '1234567890',
                'swift_code' => null,
            ],
        ])->save();

        $pdfs = app(DocumentPdfService::class);
        $devSnapshot = $invoice->fresh('bankAccount')->bank_snapshot;

        // The real bank selected on the document is substituted for the dev snapshot.
        $bank = $pdfs->resolveBankSnapshot($devSnapshot, $invoice->fresh('bankAccount')->bankAccount);
        $this->assertSame('Prime Bank Ltd.', $bank['bank_name']);
        $this->assertSame('2170316017001', $bank['account_number']);

        // With no bank selected, the active default real bank is substituted.
        $fallback = $pdfs->resolveBankSnapshot($devSnapshot, null);
        $this->assertSame('Prime Bank Ltd.', $fallback['bank_name']);
        $this->assertSame('2170316017001', $fallback['account_number']);

        // A genuine bank snapshot passes through unchanged.
        $realSnapshot = $bank;
        $this->assertSame($realSnapshot, $pdfs->resolveBankSnapshot($realSnapshot, null));
    }

    public function test_bank_seeder_configures_real_smsea_accounts_idempotently(): void
    {
        $this->seed(BankAccountSeeder::class);
        $this->seed(BankAccountSeeder::class);

        $this->assertDatabaseHas('bank_accounts', [
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'branch' => 'Garib E Newaj Avenue, Uttara, Dhaka',
            'account_number' => '2170316017001',
            'swift_code' => 'PRBLBDDH',
        ]);
        $this->assertDatabaseHas('bank_accounts', [
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Mutual Trust Bank',
            'branch' => 'Shah Mokhdum Avenue Branch',
            'account_number' => '1301000014453',
            'swift_code' => 'MTBLBDDH',
        ]);
        $this->assertSame(1, BankAccount::query()->where('account_number', '2170316017001')->count());
        $this->assertSame(1, BankAccount::query()->where('account_number', '1301000014453')->count());
    }

    private function createInvoice(User $user, Client $client, BankAccount $bank, Service $service, float $rate): ProformaInvoice
    {
        $this->actingAs($user)->post(route('proforma-invoices.store'), $this->payload($client, $bank, [[
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_rate' => $rate,
        ]]))->assertRedirect();

        return ProformaInvoice::with('items')->latest('id')->firstOrFail();
    }

    private function payload(Client $client, BankAccount $bank, array $items, array $extra = []): array
    {
        return [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-09',
            'charge_for' => 'Environmental Impact Assessment Package',
            'adjustment' => 0,
            'items' => array_map(fn (array $item) => [
                'service_id' => $item['service_id'],
                'description' => '',
                'scope_items' => '',
                'pricing_mode' => 'consolidated',
                'unit' => 'Job',
                'quantity' => $item['quantity'],
                'unit_rate' => $item['unit_rate'],
            ], $items),
            ...$extra,
        ];
    }

    private function setupData(array $settings = []): array
    {
        $user = User::factory()->create();
        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'tagline' => 'Environmental testing, assessment and compliance support',
            'office_address' => 'Uttara, Dhaka, Bangladesh',
            'phone' => '+880 1234 567890',
            'email' => 'info@smsea.example',
            'website' => 'www.smsea.example',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Authorized Representative',
            'prepared_by_designation' => 'SMS Environmental Alliance',
            'pdf_note' => 'This is a computer-generated document and does not require a physical signature.',
            'invoice_payment_terms' => "Payment shall be made by bank transfer or account payee cheque.\nWhere applicable, work will commence following confirmation of payment.\nVAT/AIT or statutory deductions shall be treated according to the stated invoice tax treatment and applicable requirements.",
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            ...$settings,
        ]);
        $client = Client::query()->create([
            'company_name' => 'Maria Attires',
            'contact_person' => 'M. Mahbub Alam',
            'designation' => 'Sr. Manager',
            'email' => 'mahbub.libasstitch@gmail.com',
            'address' => 'Chaira, Mushurikhola, Hemayetpur, Savar, Dhaka, Bangladesh',
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'branch' => 'Garib E Newaj Avenue, Uttara, Dhaka',
            'account_number' => '2170316017001',
            'swift_code' => 'PRBLBDDH',
            'is_active' => true,
            'is_default' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Environmental Impact Assessment Package',
            'short_name' => 'Environmental Impact Assessment Package',
            'service_type' => Service::TYPE_BUNDLE,
            'invoice_description' => 'Environmental Impact Assessment Package',
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);

        foreach ([
            'Environmental Impact Assessment',
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
            'Light Level Assessment',
            'Temperature Assessment',
            'Humidity Assessment',
            'GHG Assessment / Inventory',
            'ODS Assessment / Inventory',
        ] as $index => $name) {
            $service->components()->create(['name' => $name, 'sort_order' => $index + 1, 'is_active' => true]);
        }

        return [$user, $client, $bank, $service->refresh()];
    }
}
