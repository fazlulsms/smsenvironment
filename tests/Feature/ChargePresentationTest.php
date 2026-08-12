<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Services\ProformaInvoiceVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargePresentationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Setting::current()->update(['default_currency' => 'USD']);
        $this->client = Client::query()->create(['company_name' => 'Client Ltd.', 'address' => 'Dhaka']);
        $this->bank = BankAccount::query()->create([
            'beneficiary_name' => 'Ben', 'bank_name' => 'Prime Bank Ltd.',
            'account_number' => '2170316017001', 'is_active' => true, 'is_default' => true,
        ]);
    }

    private function store(array $payload): ProformaInvoice
    {
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), array_merge([
            'client_id' => $this->client->id,
            'bank_account_id' => $this->bank->id,
            'date' => '2026-08-12',
            'vat_treatment' => 'exclusive',
        ], $payload))->assertRedirect();

        return ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
    }

    private function chargeTableHtml(ProformaInvoice $invoice): string
    {
        $rows = $invoice->items->map(fn ($item) => [
            'title' => $item->service?->short_name ?: ($item->description ?: 'Service'),
            'activities' => collect($item->scope_items ?: []),
            'item' => $item,
        ]);

        return view('documents.invoice_charge_table', [
            'invoice' => $invoice, 'serviceRows' => $rows, 'currency' => 'USD',
        ])->render();
    }

    public function test_consolidated_single_fee(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'PEFC Chain of Custody Certification',
            'consolidated' => [
                'description' => 'Certification Fee, including the audit, certification, licence fees, and transportation costs.',
                'unit' => 'Job', 'quantity' => 1, 'unit_rate' => 1900,
            ],
        ]);

        $this->assertSame('consolidated', $invoice->charge_presentation);
        $this->assertSame('PEFC Chain of Custody Certification', $invoice->charge_title);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(1900, (float) $invoice->subtotal);
        $this->assertEquals(1900, (float) $invoice->total);

        // The partial renders the CHARGE FOR block (SERVICE lives in the PDF header).
        $html = $this->chargeTableHtml($invoice);
        $this->assertStringContainsString('Charge For', $html);
        $this->assertStringContainsString('Certification Fee', $html); // CHARGE FOR = description
        $this->assertStringNotContainsString('>Rate<', $html); // no rate column in consolidated

        $this->assertNotEmpty(app(DocumentPdfService::class)->proformaInvoicePdf($invoice)->output());
    }

    public function test_component_breakdown_one_total(): void
    {
        $components = ['SLCP Verification Fee (Step-03)', 'Verification Initiation & Upload Fee', 'Administration Fee', 'Travel & Operational Cost'];
        $invoice = $this->store([
            'charge_presentation' => 'component_breakdown',
            'charge_title' => 'SLCP Verification Services',
            'breakdown' => ['components' => implode("\n", $components), 'amount' => 139130],
        ]);

        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertCount(1, $invoice->items);
        $this->assertCount(4, $invoice->items->first()->scope_items);
        $this->assertEquals(139130, (float) $invoice->subtotal);
        $this->assertEquals(139130, (float) $invoice->total);

        // The partial renders the CHARGE FOR block (SERVICE lives in the PDF header).
        $html = $this->chargeTableHtml($invoice);
        $this->assertStringContainsString('Including:', $html);
        foreach ($components as $component) {
            $this->assertStringContainsString(e($component), $html);
        }
        // Components are listed with no per-component price; the single package
        // total appears exactly once, in the AMOUNT column spanning the block.
        $this->assertSame(1, substr_count($html, '139,130.00'));

        $this->assertNotEmpty(app(DocumentPdfService::class)->proformaInvoicePdf($invoice)->output());
    }

    public function test_itemized_multiple_priced_rows(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'itemized',
            'items' => [
                ['description' => 'GRS and RCS Audit, Certification, License Fee & Travel Cost', 'unit' => 'Job', 'quantity' => 1, 'unit_rate' => 2400],
                ['description' => 'Required Documentation Support for GRS and RCS', 'unit' => 'Job', 'quantity' => 1, 'unit_rate' => 400],
            ],
        ]);

        $this->assertSame('itemized', $invoice->charge_presentation);
        $this->assertCount(2, $invoice->items);
        $this->assertEquals(2800, (float) $invoice->subtotal);
        $this->assertEquals(2800, (float) $invoice->total);

        $html = $this->chargeTableHtml($invoice);
        $this->assertStringContainsString('Description', $html);
        $this->assertStringNotContainsString('Unit', $html);   // no unit/qty/rate columns
        $this->assertStringContainsString('2,400.00', $html);
        $this->assertStringContainsString('400.00', $html);
    }

    public function test_vat_applies_uniformly_across_modes(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'PEFC Certification',
            'consolidated' => ['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 2000],
            'vat_treatment' => 'add', 'vat_rate' => 15, 'show_vat_separately' => 1,
        ]);

        $this->assertEquals(2000, (float) $invoice->subtotal);
        $this->assertEquals(300, (float) $invoice->vat_amount);
        $this->assertEquals(2300, (float) $invoice->total);
    }

    public function test_qr_v3_signs_presentation_and_verifies(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'component_breakdown',
            'charge_title' => 'SLCP Verification Services',
            'breakdown' => ['components' => "A\nB", 'amount' => 5000],
        ]);
        $service = app(ProformaInvoiceVerificationService::class);

        $this->assertSame(ProformaInvoiceVerificationService::PAYLOAD_VERSION, $invoice->verification_payload_version);
        $canonical = $service->canonicalData($invoice->fresh('items'));
        $this->assertSame('component_breakdown', $canonical['presentation']);
        $this->assertSame('DOC-PI-V3', $canonical['version']);
        $this->assertSame($invoice->verification_signature, $service->signature($invoice->fresh('items')));
    }

    public function test_duplicate_preserves_presentation_with_new_identity(): void
    {
        $invoice = $this->store([
            'charge_presentation' => 'consolidated',
            'charge_title' => 'PEFC Certification',
            'consolidated' => ['description' => 'Fee', 'quantity' => 1, 'unit_rate' => 1900],
        ]);

        $this->actingAs($this->user)->post(route('proforma-invoices.duplicate', $invoice))->assertRedirect();
        $copy = ProformaInvoice::query()->where('id', '!=', $invoice->id)->latest('id')->with('items')->firstOrFail();

        $this->assertSame('consolidated', $copy->charge_presentation);
        $this->assertSame('PEFC Certification', $copy->charge_title);
        $this->assertCount(1, $copy->items);
        $this->assertEquals(1900, (float) $copy->total);
        $this->assertNotSame($invoice->number, $copy->number);
        $this->assertNotSame($invoice->verification_signature, $copy->verification_signature);
    }

    public function test_charge_presentation_works_for_a_secondary_entity(): void
    {
        $eco = BusinessEntity::query()->where('entity_code', 'ECOVERITAS')->firstOrFail();
        app(CurrentEntity::class)->use($eco->id);
        Setting::current();
        $client = Client::query()->create(['company_name' => 'Eco Client', 'address' => 'Somewhere']);
        $bank = BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Bank', 'account_number' => '55', 'is_active' => true, 'is_default' => true]);

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-12', 'vat_treatment' => 'exclusive',
            'charge_presentation' => 'component_breakdown', 'charge_title' => 'EcoVeritas Package',
            'breakdown' => ['components' => "Audit\nReport", 'amount' => 700],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('ECOVERITAS', $invoice->entity_code);
        $this->assertSame('component_breakdown', $invoice->charge_presentation);
        $this->assertEquals(700, (float) $invoice->total);
        $this->assertNotEmpty(app(DocumentPdfService::class)->proformaInvoicePdf($invoice)->output());
    }

    public function test_snapshot_is_immutable_after_service_changes(): void
    {
        $service = Service::query()->create(['name' => 'Original', 'service_type' => 'bundle', 'is_active' => true]);
        $invoice = $this->store([
            'charge_presentation' => 'itemized',
            'items' => [['service_id' => $service->id, 'description' => 'Original charge', 'unit' => 'Job', 'quantity' => 1, 'unit_rate' => 1000]],
        ]);

        $service->update(['name' => 'Renamed', 'invoice_description' => 'Different']);

        $this->assertSame('Original charge', $invoice->fresh('items')->items->first()->description);
    }
}
