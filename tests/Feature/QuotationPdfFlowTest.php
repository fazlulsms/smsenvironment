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

class QuotationPdfFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_and_acceptance_render_once_from_legacy_repeated_terms(): void
    {
        [$user, $quotation] = $this->quotationWithLegacyTerms();

        $html = view('quotations.pdf', [
            'quotation' => $quotation,
            'settings' => $quotation->settings_snapshot,
            'client' => $quotation->client_snapshot,
            'bank' => $quotation->bank_snapshot,
            'amountInWords' => 'Fifty Thousand Taka Only',
            'verificationQr' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->render();

        $this->assertSame(1, substr_count($html, 'Scope of Service'));
        $this->assertSame(1, substr_count($html, 'Client Responsibilities'));
        $this->assertSame(1, substr_count($html, 'Scheduling and Site Readiness'));
        $this->assertSame(1, substr_count($html, 'Reporting</strong>'));
        $this->assertSame(1, substr_count($html, 'Confidentiality'));
        $this->assertSame(1, substr_count($html, 'Fees and Payment'));
        $this->assertSame(1, substr_count($html, 'VAT, AIT and Statutory Treatment'));
        $this->assertSame(1, substr_count($html, 'Proposal Validity'));
        $this->assertSame(1, substr_count($html, 'Cancellation / Rescheduling'));
        $this->assertSame(1, substr_count($html, 'Acceptance of Quotation'));
        $this->assertSame(1, substr_count($html, 'Client Company:'));
        $this->assertSame(1, substr_count($html, 'Company Seal:'));
        $this->assertStringContainsString('verification-qr', $html);

        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();
    }

    public function test_quotation_pdf_css_allows_natural_flow_after_letter_page(): void
    {
        $css = view('documents.pdf_styles')->render();

        $this->assertStringContainsString('.quotation-proposal .proposal-page { page-break-after: auto; }', $css);
        $this->assertStringContainsString('.quotation-proposal .letter-page { page-break-after: always; }', $css);
        $this->assertStringContainsString('.quotation-proposal .acceptance-block { margin-top: 8px; border: 1px solid #cfe0d7; padding: 7px 10px; page-break-inside: avoid; }', $css);
        $this->assertStringNotContainsString('.quotation-proposal .proposal-page { page-break-after: always; }', $css);
    }

    private function quotationWithLegacyTerms(): array
    {
        $user = User::factory()->create();
        $settings = Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
            'quotation_show_vat_separately' => true,
            'quotation_include_acceptance' => true,
        ]);
        $client = Client::query()->create([
            'company_name' => 'Phase 1.6 Flow Client Ltd.',
            'address' => 'Dhaka, Bangladesh',
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456789',
            'is_active' => true,
        ]);
        $eia = Service::query()->create([
            'name' => 'Environmental Impact Assessment Package',
            'short_name' => 'EIA Package',
            'service_type' => Service::TYPE_BUNDLE,
            'quotation_scope' => 'Environmental Impact Assessment Package',
            'default_unit' => 'Job',
            'is_active' => true,
        ]);
        $parameter = Service::query()->create([
            'name' => 'Environmental Parameter Assessment',
            'short_name' => 'Parameter Assessment',
            'service_type' => Service::TYPE_BUNDLE,
            'quotation_scope' => 'Environmental Parameter Assessment Package',
            'default_unit' => 'Job',
            'is_active' => true,
        ]);
        $legacyTerms = "Scope of Service: Services will be performed according to the scope described in this quotation.\n\nClient Cooperation and Scheduling: The client will provide access and schedules will be mutually agreed.\n\nReporting and Confidentiality: Reports will be prepared from available information and information will be treated confidentially.\n\nPayment and Validity: Payment shall follow the commercial terms and the quotation remains valid.\n\nScope of Service: Duplicate old scope wording.";
        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => 'SMSEA/QT/2026/0001',
            'date' => '2026-08-08',
            'client_snapshot' => $client->only(['company_name', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => $settings->toArray(),
            'subject' => 'Quotation for EIA Package and Parameter Assessment',
            'intro_text' => 'Please find our proposal.',
            'methodology' => 'The assignment will combine document/data review, onsite assessment or inspection, relevant information collection, analysis of findings and preparation of the applicable report and recommendations.',
            'deliverables' => "Environmental assessment / technical report.\nFindings against applicable requirements.\nIdentified risks, impacts or compliance gaps where applicable.\nRecommended corrective, mitigation or improvement measures.\nTest and monitoring results where applicable.",
            'client_responsibilities' => 'Provide reasonable access to relevant facility areas, personnel, documents, records, operational information, utilities and resources required for the assignment.',
            'terms_conditions' => $legacyTerms,
            'include_acceptance' => true,
            'acceptance_text' => 'We hereby confirm acceptance of the scope of services, commercial terms and conditions stated in this quotation and authorize SMS Environmental Alliance to proceed accordingly.',
            'subtotal' => 50000,
            'adjustment' => 0,
            'vat_treatment' => 'exclusive',
            'vat_amount' => 0,
            'total' => 50000,
        ]);

        foreach ([$eia, $parameter] as $index => $service) {
            $quotation->items()->create([
                'service_id' => $service->id,
                'description' => $service->quotation_scope,
                'scope_items' => ['Environmental Impact Assessment', 'Ambient Air Quality Assessment', 'Noise Level Assessment'],
                'unit' => 'Job',
                'quantity' => 1,
                'unit_rate' => 25000,
                'amount' => 25000,
                'sort_order' => $index + 1,
            ]);
        }

        return [$user, $quotation->load('items.service')];
    }
}
