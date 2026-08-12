<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\AmountInWords;
use App\Services\DocumentPdfService;
use App\Support\CurrentEntity;
use App\Support\DocumentProfile;
use App\Support\InvoiceMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EidikosInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BusinessEntity $eidikos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->eidikos = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->firstOrFail();
        app(CurrentEntity::class)->use($this->eidikos->id);
        Setting::current();
    }

    private function store(array $payload): ProformaInvoice
    {
        $client = Client::query()->firstOrCreate(
            ['company_name' => $payload['company_name'] ?? 'Sadma Fashion Wear Ltd.'],
            ['address' => 'Kewa, Sreepur, Gazipur', 'contact_person' => 'Rakib Hasan', 'designation' => 'Compliance Manager', 'email' => 'compliance@sadma.com']
        );

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge([
            'client_id' => $client->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
        ], collect($payload)->except('company_name')->all()))->assertRedirect();

        return ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
    }

    /** Faithfully reproduces DocumentPdfService's view data so we can assert on HTML. */
    private function renderHtml(ProformaInvoice $invoice): string
    {
        $invoice->loadMissing('items.service', 'bankAccount', 'client');
        $settings = $invoice->settings_snapshot ?: Setting::current()->toArray();
        $profile = DocumentProfile::forInvoice($invoice);
        $money = InvoiceMoney::context($invoice, $settings);
        $entity = BusinessEntity::query()->where('entity_code', $invoice->entity_code)->first();
        $words = app(AmountInWords::class)->convert(
            $money['words_amount'], $money['words_currency'],
            $settings['currency_major_name'] ?? 'Taka', $settings['currency_minor_name'] ?? 'Paisa'
        );

        return view($profile['pdf_view'], [
            'invoice' => $invoice, 'settings' => $settings, 'entity' => $entity, 'profile' => $profile,
            'money' => $money, 'client' => $invoice->client_snapshot ?: [], 'bank' => $invoice->bank_snapshot ?: [],
            'verificationQr' => $profile['show_verification'] ? 'data:image/svg+xml;base64,ZZZ' : null,
            'amountInWords' => $words,
        ])->render();
    }

    public function test_eidikos_invoice_belongs_to_its_own_profile_without_verification(): void
    {
        $profile = DocumentProfile::forEntityCode('EIDIKOS');
        $this->assertSame('proforma_invoices.eidikos_pdf', $profile['pdf_view']);
        $this->assertFalse($profile['show_verification']);
        $this->assertFalse((bool) $this->eidikos->qr_verification_enabled);

        // The brand logo is configured (entity + settings) and the asset ships.
        $this->assertSame('logos/eidikos-logo.svg', $this->eidikos->logo_path);
        $this->assertSame('logos/eidikos-logo.svg', Setting::current()->logo_path);
        $this->assertFileExists(storage_path('app/public/'.$this->eidikos->logo_path));
    }

    /** Acceptance case: Amfori BSCI, USD 2,980 -> BDT 372,500 @ 125. */
    public function test_bsci_usd_to_bdt_acceptance_case(): void
    {
        $invoice = $this->store([
            'company_name' => 'Sadma Fashion Wear Ltd.',
            'charge_presentation' => 'consolidated',
            'charge_title' => 'Amfori BSCI Monitoring Audit Fee',
            'reference_no' => 'EIDIKOS/REF/2026/018',
            'currency' => 'USD', 'conversion_rate' => 125,
            'consolidated' => ['description' => 'Fee covers audit and consultancy costs.', 'amount' => 2980],
        ]);

        $this->assertSame('EIDIKOS', $invoice->entity_code);
        $this->assertSame('USD', $invoice->currency);
        $this->assertEquals(125, (float) $invoice->conversion_rate);
        $this->assertEquals(2980, (float) $invoice->total);

        $html = $this->renderHtml($invoice);

        // Eidikos identity + brand logo (not the monogram fallback) + no verification.
        $this->assertStringContainsString('eidikos-document', $html);
        $this->assertStringContainsString('eidikos-logo.svg', $html); // configured logo is used
        $this->assertStringNotContainsString('class="eh-mark"', $html); // not the EC monogram fallback
        $this->assertStringContainsString('EIDIKOS', $html);
        $this->assertStringContainsString('Amfori BSCI Monitoring Audit Fee', $html);
        $this->assertStringContainsString('EIDIKOS/REF/2026/018', $html); // Reference No.
        $this->assertStringContainsString($invoice->number, $html);        // Invoice Ref. Number

        // Dual currency USD -> BDT.
        $this->assertStringContainsString('USD 2,980.00', $html);
        $this->assertStringContainsString('BDT 372,500.00', $html);
        $this->assertStringContainsString('Conversion Rate: 1 USD = BDT 125.00', $html);
        $this->assertStringContainsString('Three Lakh Seventy-Two Thousand Five Hundred Taka Only', $html);

        // Payment details + bank + contact.
        $this->assertStringContainsString('Payment Details', $html);
        $this->assertStringContainsString('The City Bank Limited', $html);
        $this->assertStringContainsString('CIBLBDDH', $html);
        $this->assertStringContainsString('Contact', $html);

        // Mandatory: no QR / verification / Unit-Qty-Rate.
        $this->assertStringNotContainsString('data:image/svg+xml', $html);
        $this->assertStringNotContainsString('Verification', $html);
        $this->assertStringNotContainsString('Scan to compare', $html);
        $this->assertStringNotContainsString('Qty', $html);
        $this->assertStringNotContainsString('Unit Rate', $html);

        // PDF actually renders.
        $this->assertNotEmpty(app(DocumentPdfService::class)->proformaInvoicePdf($invoice)->output());
    }

    public function test_bdt_only_invoice_has_no_conversion(): void
    {
        $invoice = $this->store([
            'company_name' => 'Green Paper Mills Ltd.',
            'charge_presentation' => 'consolidated',
            'charge_title' => 'PEFC Chain of Custody Certification Fee',
            'currency' => 'BDT',
            'consolidated' => ['description' => 'Certification Fee, including the audit and licence fees.', 'amount' => 85000],
        ]);

        $html = $this->renderHtml($invoice);
        $this->assertStringContainsString('Amount (BDT)', $html); // table header in transaction currency
        $this->assertStringContainsString('BDT 85,000.00', $html);
        $this->assertStringNotContainsString('Conversion Rate', $html);
        $this->assertStringNotContainsString('data:image/svg+xml', $html);
    }

    public function test_breakdown_lists_components_with_single_amount(): void
    {
        $invoice = $this->store([
            'company_name' => 'Unity Apparels Ltd.',
            'charge_presentation' => 'component_breakdown',
            'charge_title' => 'SLCP Verification Services',
            'currency' => 'USD', 'conversion_rate' => 122,
            'breakdown' => ['components' => "SLCP Verification Fee (Step-03)\nAdministration Fee\nTravel & Operational Cost", 'amount' => 1140],
        ]);

        $html = $this->renderHtml($invoice);
        $this->assertStringContainsString('Including:', $html);
        $this->assertStringContainsString('Administration Fee', $html);
        // Components are listed with no per-component pricing; the package amount is
        // the only figure in the commercial table.
        $this->assertSame(1, substr_count($html, 'class="e-c-amt">USD 1,140.00'));
        $this->assertStringContainsString('Conversion Rate: 1 USD = BDT 122.00', $html);
    }

    public function test_itemized_shows_priced_rows_without_unit_columns(): void
    {
        $invoice = $this->store([
            'company_name' => 'GRS Client Ltd.',
            'charge_presentation' => 'itemized',
            'currency' => 'USD', 'conversion_rate' => 124,
            'items' => [
                ['description' => 'GRS & RCS Audit, Certification, License Fee & Travel Cost', 'amount' => 2400],
                ['description' => 'Required Documentation Support for GRS and RCS', 'amount' => 400],
            ],
        ]);

        $html = $this->renderHtml($invoice);
        $this->assertStringContainsString('USD 2,400.00', $html);
        $this->assertStringContainsString('USD 400.00', $html);
        $this->assertStringContainsString('Description of Particular', $html);
        $this->assertStringNotContainsString('Qty', $html);
    }

    /** Regression: SMSEA keeps its own verified profile — Eidikos changes do not leak. */
    public function test_smsea_invoice_keeps_verification_profile(): void
    {
        $smsea = BusinessEntity::query()->where('entity_code', 'SMSEA')->firstOrFail();
        app(CurrentEntity::class)->use($smsea->id);
        Setting::current();
        $client = Client::query()->create(['company_name' => 'SMSEA Client', 'address' => 'Dhaka']);
        BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '2170316017001', 'is_active' => true, 'is_default' => true]);

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'charge_presentation' => 'consolidated', 'charge_title' => 'Environmental Management Plan',
            'consolidated' => ['description' => 'EMP services', 'amount' => 50000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('SMSEA', $invoice->entity_code);
        $profile = DocumentProfile::forInvoice($invoice);
        $this->assertSame('proforma_invoices.pdf', $profile['pdf_view']);
        $this->assertTrue($profile['show_verification']);

        $html = $this->renderHtml($invoice);
        $this->assertStringContainsString('invoice-footer', $html);      // SMSEA fixed footer
        $this->assertStringContainsString('data:image/svg+xml', $html);  // QR present
        $this->assertStringNotContainsString('eidikos-document', $html);
    }
}
