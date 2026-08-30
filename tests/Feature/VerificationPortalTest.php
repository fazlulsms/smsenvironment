<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\User;
use App\Services\ProformaInvoiceVerificationService;
use App\Services\QuotationVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public verification portal: the QR now carries a short /verify/{id} URL and
 * this page shows the authoritative, integrity-checked details. No auth required.
 */
class VerificationPortalTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function makeInvoice(string $number = 'SMSEA/PI/2026/0099'): ProformaInvoice
    {
        $this->useSmsea();
        $user = User::factory()->create();
        $client = Client::query()->create(['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur']);
        $bank = BankAccount::query()->create(['beneficiary_name' => 'SMSEA', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '1', 'is_active' => true, 'is_default' => true]);

        $invoice = ProformaInvoice::query()->create([
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'created_by' => $user->id,
            'number' => $number, 'date' => '2026-08-17', 'charge_presentation' => 'component_breakdown',
            'client_snapshot' => ['company_name' => 'Green Textiles Ltd.', 'address' => 'Gazipur'],
            'settings_snapshot' => ['organization_name' => 'SMS Environmental Alliance', 'default_currency' => 'BDT'],
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 30000, 'adjustment' => 0, 'total' => 30000,
        ]);
        $invoice->items()->create([
            'description' => 'Environmental Parameter Testing',
            'scope_items' => ['Stack Emission Test', 'Noise Level Assessment'],
            'amount' => 30000, 'sort_order' => 1,
        ]);

        return app(ProformaInvoiceVerificationService::class)->apply($invoice->load('items'));
    }

    private function makeQuotation(string $number = 'SMSEA/QT/2026/0044'): Quotation
    {
        $this->useSmsea();
        $user = User::factory()->create();
        $client = Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);

        $quotation = Quotation::query()->create([
            'client_id' => $client->id, 'created_by' => $user->id,
            'number' => $number, 'date' => '2026-08-17',
            'client_snapshot' => ['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka'],
            'settings_snapshot' => ['organization_name' => 'SMS Environmental Alliance', 'default_currency' => 'BDT'],
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 50000, 'adjustment' => 0, 'total' => 50000,
        ]);
        $quotation->items()->create(['description' => 'Environmental Impact Assessment', 'amount' => 50000, 'sort_order' => 1]);

        return app(QuotationVerificationService::class)->apply($quotation->load('items'));
    }

    public function test_the_qr_now_encodes_a_short_verify_url(): void
    {
        $invoice = $this->makeInvoice();
        $url = app(ProformaInvoiceVerificationService::class)->verificationUrl($invoice);

        $this->assertStringContainsString('/verify/'.$invoice->verification_id, $url);
        // A short URL, not the whole invoice payload.
        $this->assertLessThan(120, strlen($url));
    }

    public function test_verify_page_is_public_and_shows_a_verified_invoice(): void
    {
        $invoice = $this->makeInvoice();

        // No acting-as: a guest can open the QR target.
        $this->get(route('verify.show', $invoice->verification_id))->assertOk()
            ->assertSee('Verified')
            ->assertSee('SMSEA/PI/2026/0099')
            ->assertSee('Green Textiles Ltd.')
            ->assertSee('Environmental Parameter Testing')
            ->assertSee('30,000.00');
    }

    public function test_verify_page_shows_a_verified_quotation(): void
    {
        $quotation = $this->makeQuotation();

        $this->get(route('verify.show', $quotation->verification_id))->assertOk()
            ->assertSee('Verified')
            ->assertSee('Quotation')
            ->assertSee('SMSEA/QT/2026/0044')
            ->assertSee('Environmental Impact Assessment');
    }

    public function test_lookup_by_document_number_redirects_to_the_verify_page(): void
    {
        $invoice = $this->makeInvoice();

        $this->get(route('verify.index', ['q' => 'SMSEA/PI/2026/0099']))
            ->assertRedirect(route('verify.show', $invoice->verification_id));
    }

    public function test_unknown_code_shows_a_not_found_page(): void
    {
        $this->get(route('verify.show', 'ABCD-1234-EF56-7890'))->assertOk()
            ->assertSee('not found', false);
    }

    public function test_unknown_number_shows_not_found_on_the_search_page(): void
    {
        $this->get(route('verify.index', ['q' => 'SMSEA/PI/2026/9999']))->assertOk()
            ->assertSee('No document found', false);
    }

    public function test_a_tampered_record_is_flagged_as_unconfirmed(): void
    {
        $invoice = $this->makeInvoice();
        // Simulate a record whose stored signature no longer matches its data.
        $invoice->forceFill(['total' => 999999])->save();

        $this->get(route('verify.show', $invoice->verification_id))->assertOk()
            ->assertSee('could not confirm', false)
            ->assertDontSee('authentic document', false);
    }

    public function test_the_index_page_renders_a_search_form(): void
    {
        $this->get(route('verify.index'))->assertOk()->assertSee('Verify Documents')->assertSee('Enter the document number');
    }
}
