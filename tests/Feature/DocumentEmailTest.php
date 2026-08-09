<?php

namespace Tests\Feature;

use App\Mail\DocumentMail;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DocumentEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_email_form_loads_with_prefilled_to_and_subject(): void
    {
        $user = User::factory()->create();
        $quotation = $this->quotation();

        $this->actingAs($user)
            ->get(route('quotations.email.create', $quotation))
            ->assertOk()
            ->assertSee('client@example.com')
            ->assertSee('Quotation for Ambient Air Quality Test - REEYAN KNIT WEAR LIMITED')
            ->assertSee('Quotation - REEYAN KNIT WEAR LIMITED - Ambient Air Quality Test.pdf');
    }

    public function test_quotation_email_sends_with_cc_pdf_filename_and_history(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Fazlul Haque']);
        $quotation = $this->quotation();

        $this->actingAs($user)->post(route('quotations.email.send', $quotation), [
            'to' => 'client@example.com',
            'cc' => 'finance@example.com, coordinator@example.com',
            'subject' => 'Custom quotation subject',
            'message' => 'Please see attached.',
        ])->assertRedirect(route('quotations.show', $quotation));

        Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) {
            return $mail->subjectLine === 'Custom quotation subject'
                && $mail->attachmentFilename === 'Quotation - REEYAN KNIT WEAR LIMITED - Ambient Air Quality Test.pdf'
                && $mail->bodyText === 'Please see attached.';
        });

        $this->assertDatabaseHas('document_email_deliveries', [
            'document_type' => 'quotation',
            'document_id' => $quotation->id,
            'to_email' => 'client@example.com',
            'subject' => 'Custom quotation subject',
            'sent_by' => $user->id,
            'status' => 'sent',
        ]);

        $this->assertSame(
            ['finance@example.com', 'coordinator@example.com'],
            DocumentEmailDelivery::query()->firstOrFail()->cc_emails
        );
    }

    public function test_invalid_to_and_cc_are_rejected_before_sending(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $quotation = $this->quotation();

        $this->actingAs($user)->post(route('quotations.email.send', $quotation), [
            'to' => 'not-an-email',
            'cc' => 'good@example.com, bad-address',
            'subject' => 'Subject',
            'message' => 'Body',
        ])->assertSessionHasErrors(['to', 'cc']);

        Mail::assertNothingSent();
        $this->assertSame(0, DocumentEmailDelivery::query()->count());
    }

    public function test_resending_creates_separate_history_records(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $quotation = $this->quotation();

        foreach (['First send', 'Second send'] as $subject) {
            $this->actingAs($user)->post(route('quotations.email.send', $quotation), [
                'to' => 'client@example.com',
                'subject' => $subject,
                'message' => 'Body',
            ])->assertRedirect(route('quotations.show', $quotation));
        }

        $this->assertSame(2, DocumentEmailDelivery::query()->where('document_type', 'quotation')->count());
    }

    public function test_proforma_email_uses_proforma_subject_and_attachment_filename(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invoice = $this->proformaInvoice();

        $this->actingAs($user)
            ->get(route('proforma-invoices.email.create', $invoice))
            ->assertOk()
            ->assertSee('Proforma Invoice for Ambient Air Quality Test - REEYAN KNIT WEAR LIMITED');

        $this->actingAs($user)->post(route('proforma-invoices.email.send', $invoice), [
            'to' => 'client@example.com',
            'subject' => 'Proforma subject',
            'message' => 'Invoice attached.',
        ])->assertRedirect(route('proforma-invoices.show', $invoice));

        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->attachmentFilename === 'Proforma Invoice - REEYAN KNIT WEAR LIMITED - Ambient Air Quality Test.pdf');
        $this->assertDatabaseHas('document_email_deliveries', [
            'document_type' => 'proforma_invoice',
            'document_id' => $invoice->id,
            'status' => 'sent',
        ]);
    }

    public function test_email_routes_require_authentication(): void
    {
        $quotation = $this->quotation();

        $this->get(route('quotations.email.create', $quotation))->assertRedirect(route('login'));
    }

    public function test_test_email_configuration_sends_simple_message(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.test-email'), [
            'test_email' => 'internal@example.com',
        ])->assertRedirect(route('settings.edit'));

        Mail::assertSentCount(1);
    }

    private function quotation(): Quotation
    {
        [$client, $service, $bank] = $this->baseData();

        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => User::factory()->create()->id,
            'number' => 'SMSEA/QT/2026/0001',
            'date' => '2026-08-09',
            'client_snapshot' => $client->only(['company_name', 'contact_person', 'email', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'subject' => 'Quotation for Ambient Air Quality Test of REEYAN KNIT WEAR LIMITED',
            'payment_terms' => 'Payment terms.',
            'subtotal' => 2500,
            'adjustment' => 0,
            'vat_amount' => 0,
            'total' => 2500,
        ]);

        $quotation->items()->create([
            'service_id' => $service->id,
            'description' => 'Ambient Air Quality Test',
            'unit' => 'Point',
            'quantity' => 1,
            'unit_rate' => 2500,
            'amount' => 2500,
            'sort_order' => 1,
        ]);

        return $quotation;
    }

    private function proformaInvoice(): ProformaInvoice
    {
        [$client, $service, $bank] = $this->baseData();

        $invoice = ProformaInvoice::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => User::factory()->create()->id,
            'number' => 'SMSEA/PI/2026/0001',
            'date' => '2026-08-09',
            'client_snapshot' => $client->only(['company_name', 'contact_person', 'email', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'charge_for' => 'Ambient Air Quality Test',
            'payment_terms' => 'Payment terms.',
            'subtotal' => 2500,
            'adjustment' => 0,
            'vat_amount' => 0,
            'total' => 2500,
        ]);

        $invoice->items()->create([
            'service_id' => $service->id,
            'description' => 'Ambient Air Quality Test',
            'unit' => 'Point',
            'quantity' => 1,
            'unit_rate' => 2500,
            'amount' => 2500,
            'sort_order' => 1,
        ]);

        return $invoice;
    }

    private function baseData(): array
    {
        Setting::current()->update([
            'organization_name' => 'SMS Environmental Alliance',
            'phone' => '+8801873035178',
            'email' => 'info@smsenvironment.com',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'quotation_email_subject_template' => 'Quotation for {{service_name}} - {{client_name}}',
            'proforma_invoice_email_subject_template' => 'Proforma Invoice for {{service_name}} - {{client_name}}',
        ]);

        $client = Client::query()->create([
            'company_name' => 'REEYAN KNIT WEAR LIMITED',
            'contact_person' => 'Mr. Client',
            'email' => 'client@example.com',
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
            'bank_name' => 'Production Bank',
            'account_number' => '123456',
            'is_active' => true,
        ]);

        return [$client, $service, $bank];
    }
}
