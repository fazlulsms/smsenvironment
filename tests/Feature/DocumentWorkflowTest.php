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

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_and_service_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('clients.store'), [
            'company_name' => 'REEYAN KNIT WEAR LIMITED',
            'contact_person' => 'Sayduzzaman (Liton)',
            'designation' => 'Managing Director',
            'email' => 'client@example.com',
            'address' => 'Dhaka, Bangladesh',
            'country' => 'Bangladesh',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('services.store'), [
            'name' => 'Ambient Air Quality Test',
            'default_description' => 'Ambient Air Quality Test',
            'default_unit' => 'Point',
            'default_rate' => 2500,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('clients', ['company_name' => 'REEYAN KNIT WEAR LIMITED']);
        $this->assertDatabaseHas('services', ['name' => 'Ambient Air Quality Test']);
    }

    public function test_quotation_is_independent_and_generates_pdf(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'subject' => 'Financial proposal',
            'intro_text' => 'Please find our proposal.',
            'payment_terms' => 'Pay by bank transfer.',
            'adjustment' => 0,
            'after_save' => 'view',
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Ambient Air Quality Test',
                'unit' => 'Point',
                'quantity' => 2,
                'unit_rate' => 2500,
            ], [
                'service_id' => $service->id,
                'description' => 'Noise Level Assessment',
                'unit' => 'Point',
                'quantity' => 1,
                'unit_rate' => 1500,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('SMSEA/QT/2026/0001', $quotation->number);
        $this->assertSame('6500.00', $quotation->total);
        $this->assertSame('REEYAN KNIT WEAR LIMITED', $quotation->client_snapshot['company_name']);
        $this->assertSame('Test Bank', $quotation->bank_snapshot['bank_name']);

        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();
        $this->actingAs($user)->post(route('quotations.duplicate', $quotation))->assertRedirect();
        $this->assertSame(2, Quotation::query()->count());
        $this->assertSame('SMSEA/QT/2026/0002', Quotation::query()->latest('id')->first()->number);
    }

    public function test_proforma_invoice_is_independent_and_generates_pdf(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();

        $this->actingAs($user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'charge_for' => 'Environmental service',
            'payment_terms' => 'Pay by bank transfer.',
            'adjustment' => 100,
            'after_save' => 'view',
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Ambient Air Quality Test',
                'unit' => 'Point',
                'quantity' => 1,
                'unit_rate' => 2500,
            ]],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->firstOrFail();
        $this->assertSame('SMSEA/PI/2026/0001', $invoice->number);
        $this->assertSame('2600.00', $invoice->total);
        $this->assertSame('REEYAN KNIT WEAR LIMITED', $invoice->client_snapshot['company_name']);

        $this->actingAs($user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
        $this->actingAs($user)->post(route('proforma-invoices.duplicate', $invoice))->assertRedirect();
        $this->assertSame(2, ProformaInvoice::query()->count());
        $this->assertSame('SMSEA/PI/2026/0002', ProformaInvoice::query()->latest('id')->first()->number);
    }

    public function test_pdf_uses_saved_snapshot_after_master_data_changes(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'subject' => 'Snapshot proposal',
            'payment_terms' => 'Original terms.',
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Saved service wording',
                'unit' => 'Job',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $client->update(['company_name' => 'Changed Client Name']);
        $bank->update(['bank_name' => 'Changed Bank']);
        $service->update(['default_description' => 'Changed service wording', 'default_rate' => 99999]);
        Setting::current()->update(['organization_name' => 'Changed Organization']);
        $quotation->refresh();

        $this->assertSame('REEYAN KNIT WEAR LIMITED', $quotation->client_snapshot['company_name']);
        $this->assertSame('Test Bank', $quotation->bank_snapshot['bank_name']);
        $this->assertSame('SMS Environmental Alliance', $quotation->settings_snapshot['organization_name']);
        $this->assertSame('Saved service wording', $quotation->items()->first()->description);
        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();
    }

    public function test_quotation_auto_generates_default_content_from_services_and_settings(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();
        $service->update([
            'short_name' => 'Industrial Hygiene Assessment',
            'quotation_scope' => 'Industrial hygiene assessment scope wording.',
            'compliance_note' => 'Applicable compliance note for industrial hygiene.',
        ]);

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 23329.96,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('Quotation for Industrial Hygiene Assessment of REEYAN KNIT WEAR LIMITED', $quotation->subject);
        $this->assertSame('Please find our environmental proposal.', $quotation->intro_text);
        $this->assertSame('Applicable compliance note for industrial hygiene.', $quotation->compliance_note);
        $this->assertSame('Default quotation terms.', $quotation->payment_terms);
        $this->assertSame('Industrial hygiene assessment scope wording.', $quotation->items()->first()->description);
        $this->actingAs($user)->get(route('quotations.pdf', $quotation))->assertOk();
    }

    public function test_multiple_services_generate_concise_combined_content(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();
        $second = Service::query()->create([
            'name' => 'Noise Level Assessment',
            'short_name' => 'Noise Level Assessment',
            'default_description' => 'Noise level service wording.',
            'default_unit' => 'Point',
            'default_rate' => 1500,
            'compliance_note' => 'Noise compliance note.',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 2500,
            ], [
                'service_id' => $second->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 1500,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('Quotation for Ambient Air Quality Test and Noise Level Assessment of REEYAN KNIT WEAR LIMITED', $quotation->subject);
        $this->assertStringContainsString('Noise compliance note.', $quotation->compliance_note);
        $this->assertCount(2, $quotation->items);
    }

    public function test_invoice_auto_generates_charge_for_and_terms(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();
        $service->update(['invoice_description' => 'Industrial Hygiene Assessment and Noise Level Assessment']);

        $this->actingAs($user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 112220,
            ]],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->firstOrFail();
        $this->assertSame('Industrial Hygiene Assessment and Noise Level Assessment', $invoice->charge_for);
        $this->assertSame('Invoice-specific terms.', $invoice->payment_terms);
        $this->assertSame('Industrial Hygiene Assessment and Noise Level Assessment', $invoice->items()->first()->description);
        $this->actingAs($user)->get(route('proforma-invoices.pdf', $invoice))->assertOk();
    }

    public function test_document_level_overrides_are_preserved(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'subject' => 'Manual subject',
            'intro_text' => 'Manual intro.',
            'payment_terms' => 'Manual terms.',
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Manual line description',
                'unit' => 'Job',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame('Manual subject', $quotation->subject);
        $this->assertSame('Manual intro.', $quotation->intro_text);
        $this->assertSame('Manual terms.', $quotation->payment_terms);
        $this->assertSame('Manual line description', $quotation->items()->first()->description);
    }

    public function test_pdf_generation_requires_valid_bank_details(): void
    {
        $user = User::factory()->create();
        [$client, $service] = $this->setupDocumentData();
        BankAccount::query()->delete();

        $this->actingAs($user)->from(route('quotations.create'))->post(route('quotations.store'), [
            'client_id' => $client->id,
            'date' => '2026-08-08',
            'after_save' => 'pdf',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect(route('quotations.create'))
            ->assertSessionHasErrors('bank_account_id');
    }

    public function test_number_format_changes_apply_only_to_new_documents(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupDocumentData();

        $payload = [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'subject' => 'Numbering',
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Test',
                'unit' => 'Job',
                'quantity' => 1,
                'unit_rate' => 1,
            ]],
        ];

        $this->actingAs($user)->post(route('quotations.store'), $payload)->assertRedirect();
        $first = Quotation::query()->firstOrFail();
        Setting::current()->update(['quotation_number_format' => 'QT-{YYYY}-{###}']);
        $this->actingAs($user)->post(route('quotations.store'), $payload)->assertRedirect();

        $this->assertSame('SMSEA/QT/2026/0001', $first->fresh()->number);
        $this->assertSame('QT-2026-002', Quotation::query()->latest('id')->first()->number);
    }

    public function test_quotation_can_quick_create_client_without_leaving_form(): void
    {
        $user = User::factory()->create();
        [, $service, $bank] = $this->setupDocumentData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'subject' => 'Quick client quotation',
            'new_client' => [
                'company_name' => 'QUICK CLIENT LIMITED',
                'contact_person' => 'Quick Person',
                'designation' => 'Manager',
                'email' => 'quick@example.com',
                'address' => 'Dhaka, Bangladesh',
            ],
            'items' => [[
                'service_id' => $service->id,
                'description' => 'Ambient Air Quality Test',
                'unit' => 'Point',
                'quantity' => 1,
                'unit_rate' => 2500,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->latest('id')->firstOrFail();
        $this->assertSame('QUICK CLIENT LIMITED', $quotation->client_snapshot['company_name']);
        $this->assertDatabaseHas('clients', ['company_name' => 'QUICK CLIENT LIMITED']);
    }

    private function setupDocumentData(): array
    {
        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'tagline' => 'Environmental testing and assessment',
            'office_address' => 'Dhaka, Bangladesh',
            'phone' => '+880',
            'email' => 'info@smsea.com.bd',
            'website' => 'www.smsea.com.bd',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'default_payment_terms' => 'Default quotation terms.',
            'quotation_subject_pattern' => 'Quotation for {services} of {client}',
            'quotation_intro_text' => 'Please find our environmental proposal.',
            'invoice_payment_terms' => 'Invoice-specific terms.',
            'footer_text' => 'SMSEA footer',
            'pdf_note' => 'Computer-generated document.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);

        $client = Client::query()->create([
            'company_name' => 'REEYAN KNIT WEAR LIMITED',
            'address' => 'Dhaka, Bangladesh',
        ]);

        $service = Service::query()->create([
            'name' => 'Ambient Air Quality Test',
            'default_description' => 'Ambient Air Quality Test',
            'default_unit' => 'Point',
            'default_rate' => 2500,
            'is_active' => true,
        ]);

        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'is_active' => true,
        ]);

        return [$client, $service, $bank];
    }
}
