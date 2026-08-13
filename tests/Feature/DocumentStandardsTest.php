<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Standard;
use App\Models\User;
use App\Services\DocumentPdfService;
use App\Support\CurrentEntity;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentStandardsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** ISO category + the three common management-system standards. */
    private function isoStandards(): array
    {
        $cat = ServiceCategory::query()->create([
            'code' => 'ISO_MGMT', 'name' => 'ISO Management System Certification',
            'selection_label' => 'Select Standards', 'active' => true, 'display_order' => 1,
        ]);
        $mk = fn (string $name, string $code, int $o) => $cat->standards()->create([
            'slug' => Str::slug($code), 'name' => $name, 'code' => $code,
            'short_name' => $code, 'type' => 'ISO Standard', 'active' => true, 'display_order' => $o,
        ]);

        return [$cat, [
            $mk('ISO 9001 — Quality Management Systems', 'ISO 9001', 1),
            $mk('ISO 14001 — Environmental Management Systems', 'ISO 14001', 2),
            $mk('ISO 45001 — Occupational Health and Safety Management Systems', 'ISO 45001', 3),
        ]];
    }

    private function useEntity(string $code): BusinessEntity
    {
        $entity = BusinessEntity::query()->where('entity_code', $code)->firstOrFail();
        app(CurrentEntity::class)->use($entity->id);
        Setting::current();

        return $entity;
    }

    private function makeClientAndBank(): array
    {
        return [
            Client::query()->create(['company_name' => 'ABC Industries Ltd.', 'address' => 'Dhaka']),
            BankAccount::query()->create(['beneficiary_name' => 'B', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '2170316017001', 'is_active' => true, 'is_default' => true]),
        ];
    }

    public function test_multiple_iso_standards_one_consolidated_invoice(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id,
            'standards' => [$standards[0]->id, $standards[1]->id, $standards[2]->id],
            'charge_presentation' => 'consolidated',
            'consolidated' => ['amount' => 3000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->firstOrFail();

        // Deterministic generated title + charge_for.
        $this->assertSame('ISO 9001, ISO 14001 & ISO 45001 Certification', $invoice->charge_title);
        $this->assertSame($invoice->charge_title, $invoice->charge_for);

        // Snapshot captures ids, full names, codes and order.
        $snap = $invoice->standards_snapshot;
        $this->assertSame('ISO_MGMT', $snap['category']['code']);
        $this->assertCount(3, $snap['items']);
        $this->assertSame('ISO 9001 — Quality Management Systems', $snap['items'][0]['name']);
        $this->assertSame('ISO 14001', $snap['items'][1]['code']);
        $this->assertSame($cat->id, $invoice->service_category_id);
        $this->assertEquals(3000, (float) $invoice->total);

        // Reporting pivot rows exist, tied to the issuing entity.
        $rows = DB::table('document_standards')->where('document_type', 'proforma_invoice')->where('document_id', $invoice->id)->get();
        $this->assertCount(3, $rows);
        $this->assertSame($invoice->business_entity_id, $rows->first()->business_entity_id);
    }

    public function test_snapshot_is_immutable_after_standard_master_rename(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$standards[0]->id],
            'charge_presentation' => 'consolidated', 'consolidated' => ['amount' => 1000],
        ])->assertRedirect();
        $invoice = ProformaInvoice::query()->latest('id')->firstOrFail();

        // Rename + deactivate the master standard.
        $standards[0]->update(['name' => 'ISO 9001:2015 RENAMED', 'active' => false]);

        $this->assertSame('ISO 9001 — Quality Management Systems', $invoice->fresh()->standards_snapshot['items'][0]['name']);
    }

    public function test_same_standard_used_by_two_entities_stays_entity_specific(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $isoId = $standards[0]->id;

        $eidikos = $this->useEntity('EIDIKOS');
        [$c1, $b1] = $this->makeClientAndBank();
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $c1->id, 'bank_account_id' => $b1->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$isoId],
            'charge_presentation' => 'consolidated', 'consolidated' => ['amount' => 1000],
        ])->assertRedirect();

        $icqms = $this->useEntity('ICQMS');
        [$c2, $b2] = $this->makeClientAndBank();
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $c2->id, 'bank_account_id' => $b2->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$isoId],
            'charge_presentation' => 'consolidated', 'consolidated' => ['amount' => 2000],
        ])->assertRedirect();

        $invoices = ProformaInvoice::query()->withoutGlobalScopes()->latest('id')->take(2)->get();
        // Two distinct documents, different owning entities, but the SAME global standard.
        $this->assertSame('ICQMS', $invoices[0]->entity_code);
        $this->assertSame('EIDIKOS', $invoices[1]->entity_code);
        $this->assertSame($isoId, $invoices[0]->standards_snapshot['items'][0]['standard_id']);
        $this->assertSame($isoId, $invoices[1]->standards_snapshot['items'][0]['standard_id']);
        $this->assertNotSame($invoices[0]->id, $invoices[1]->id);
    }

    public function test_breakdown_uses_standard_names_as_scope(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$standards[0]->id, $standards[1]->id, $standards[2]->id],
            'charge_presentation' => 'component_breakdown', 'breakdown' => ['amount' => 3000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $scope = $invoice->items->first()->scope_items;
        $this->assertContains('ISO 14001 — Environmental Management Systems', $scope);
        $this->assertCount(3, $scope);
        $this->assertEquals(3000, (float) $invoice->total);
    }

    public function test_charge_table_and_pdf_render_selected_standards(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-13', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$standards[0]->id, $standards[1]->id, $standards[2]->id],
            'charge_presentation' => 'consolidated', 'consolidated' => ['amount' => 3000],
        ])->assertRedirect();
        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();

        // The shared DESCRIPTION | AMOUNT table lists the standards (full names).
        $rows = $invoice->items->map(fn ($item) => [
            'title' => $invoice->charge_title, 'activities' => collect($item->scope_items ?: []), 'item' => $item,
        ]);
        $html = view('documents.invoice_charge_table', ['invoice' => $invoice, 'serviceRows' => $rows, 'currency' => 'USD'])->render();
        $this->assertStringContainsString('Select Standards', $html);
        $this->assertStringContainsString('ISO 9001 — Quality Management Systems', $html);
        $this->assertStringContainsString('ISO 45001 — Occupational Health and Safety Management Systems', $html);

        // The full PDF (Eidikos profile) renders without error and carries the standards.
        $pdf = app(DocumentPdfService::class)->proformaInvoicePdf($invoice)->output();
        $this->assertNotEmpty($pdf);
    }

    public function test_search_matches_name_short_name_and_code(): void
    {
        $this->isoStandards();

        $this->assertSame('ISO 9001 — Quality Management Systems', Standard::search('9001')->first()->name);
        $this->assertSame('ISO 45001 — Occupational Health and Safety Management Systems', Standard::search('ISO 45001')->first()->name);
        $this->assertTrue(Standard::search('Environmental')->get()->contains('code', 'ISO 14001'));
    }

    public function test_create_forms_expose_the_standards_picker(): void
    {
        $this->seed(StandardSeeder::class);
        $this->useEntity('EIDIKOS');

        foreach (['proforma-invoices.create', 'quotations.create'] as $route) {
            $response = $this->actingAs($this->user)->get(route($route))->assertOk();
            $response->assertSee('Service &amp; Standards', false);
            $response->assertSee('ISO Management System Certification');
            $response->assertSee('Textile and Product Certification');
        }

        // The invoice form exposes ONE commercial title and no duplicate service fields.
        $invoiceForm = $this->actingAs($this->user)->get(route('proforma-invoices.create'))->assertOk();
        $invoiceForm->assertSee('Service / Particular');
        $invoiceForm->assertDontSee('Service / Package Title');
        $invoiceForm->assertDontSee('name="consolidated[service_id]"', false);
        $invoiceForm->assertDontSee('name="breakdown[service_id]"', false);
    }

    public function test_environmental_parameter_testing_breakdown_uses_package_scope(): void
    {
        $this->seed(StandardSeeder::class);
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();
        $cat = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->firstOrFail();
        $ept = Standard::query()->where('name', 'Environmental Parameter Testing')->firstOrFail();

        // No components supplied — the package contributes its own parameter list.
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$ept->id],
            'charge_presentation' => 'component_breakdown', 'breakdown' => ['amount' => 25000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame('Environmental Parameter Testing', $invoice->charge_title);
        $scope = $invoice->items->first()->scope_items;
        $this->assertCount(7, $scope);
        $this->assertContains('Stack Emission Test', $scope);
        $this->assertContains('ODS Assessment / Inventory', $scope);
        $this->assertEquals(25000, (float) $invoice->total);
    }

    public function test_manual_component_edits_survive_save(): void
    {
        [$cat, $standards] = $this->isoStandards();
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        // User typed their own fee breakdown; it must not be replaced by standard names.
        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
            'service_category_id' => $cat->id, 'standards' => [$standards[0]->id, $standards[1]->id, $standards[2]->id],
            'charge_presentation' => 'component_breakdown',
            'breakdown' => ['components' => "Certification Audit Fee\nLicence Fee\nTransportation Cost", 'amount' => 1200],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->with('items')->firstOrFail();
        $this->assertSame(['Certification Audit Fee', 'Licence Fee', 'Transportation Cost'], $invoice->items->first()->scope_items);
        // The standards are still recorded in the snapshot for the PDF + reporting.
        $this->assertCount(3, $invoice->standards_snapshot['items']);
    }

    public function test_custom_manual_service_without_standards(): void
    {
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();

        $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
            'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
            'charge_presentation' => 'consolidated', 'charge_title' => 'Bespoke Advisory Service',
            'consolidated' => ['description' => 'Custom advisory assignment as agreed.', 'amount' => 5000],
        ])->assertRedirect();

        $invoice = ProformaInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame('Bespoke Advisory Service', $invoice->charge_title);
        $this->assertNull($invoice->standards_snapshot);
        $this->assertNull($invoice->service_category_id);
        $this->assertEquals(5000, (float) $invoice->total);
    }

    public function test_service_family_titles_use_full_names(): void
    {
        $this->seed(StandardSeeder::class);
        $this->useEntity('EIDIKOS');
        [$client, $bank] = $this->makeClientAndBank();
        $cat = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->firstOrFail();

        // EIA + Higg FEM are selectable from the same picker and title by full name.
        foreach ([['Environmental Impact Assessment', 'Environmental Impact Assessment'], ['Higg FEM Verification', 'Higg FEM Verification']] as [$name, $expectedTitle]) {
            $std = Standard::query()->where('name', $name)->firstOrFail();
            $this->actingAs($this->user)->post(route('proforma-invoices.store'), [
                'client_id' => $client->id, 'bank_account_id' => $bank->id, 'date' => '2026-08-14', 'vat_treatment' => 'exclusive',
                'service_category_id' => $cat->id, 'standards' => [$std->id],
                'charge_presentation' => 'consolidated', 'consolidated' => ['amount' => 40000],
            ])->assertRedirect();
            $this->assertSame($expectedTitle, ProformaInvoice::query()->latest('id')->firstOrFail()->charge_title);
        }
    }

    public function test_standard_seeder_is_idempotent(): void
    {
        $this->seed(StandardSeeder::class);
        $categories = ServiceCategory::query()->count();
        $standards = Standard::query()->count();

        $this->seed(StandardSeeder::class);

        $this->assertSame($categories, ServiceCategory::query()->count());
        $this->assertSame($standards, Standard::query()->count());
        $this->assertSame(12, $categories);
    }
}
