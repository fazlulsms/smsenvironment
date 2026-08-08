<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentFilenameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFilenameServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_service_short_names_are_used_for_quotation_filename(): void
    {
        [$quotation] = $this->quotationFor(
            'Maria Attires',
            [$this->service('Environmental Impact Assessment Package', 'EIA Package')]
        );

        $this->assertSame(
            'Quotation - Maria Attires - EIA Package.pdf',
            app(DocumentFilenameService::class)->quotationFilename($quotation)
        );
    }

    public function test_emp_energy_and_parameter_short_names_are_supported(): void
    {
        [$emp] = $this->quotationFor('XYZ Industries', [$this->service('Environmental Management Plan', 'EMP')]);
        [$energy] = $this->quotationFor('ABC Textiles Ltd', [$this->service('Energy Audit', 'Energy Audit')]);
        [$parameter] = $this->quotationFor('Delta Garments', [$this->service('Environmental Parameter Assessment', 'Parameter Assessment')]);
        $filenames = app(DocumentFilenameService::class);

        $this->assertSame('Quotation - XYZ Industries - EMP.pdf', $filenames->quotationFilename($emp));
        $this->assertSame('Quotation - ABC Textiles Ltd - Energy Audit.pdf', $filenames->quotationFilename($energy));
        $this->assertSame('Quotation - Delta Garments - Parameter Assessment.pdf', $filenames->quotationFilename($parameter));
    }

    public function test_multiple_services_are_concise_or_use_fallback_when_too_long(): void
    {
        [$quotation] = $this->quotationFor('Appropriate Apparels Ltd', [
            $this->service('Environmental Impact Assessment', 'EIA'),
            $this->service('Environmental Management Plan', 'EMP'),
            $this->service('Environmental Parameter Assessment', 'Parameter Assessment'),
        ]);

        [$long] = $this->quotationFor('Appropriate Apparels Ltd', [
            $this->service('Very Long Service One', 'Very Long Environmental Service Name One'),
            $this->service('Very Long Service Two', 'Very Long Environmental Service Name Two'),
            $this->service('Very Long Service Three', 'Very Long Environmental Service Name Three'),
        ]);

        $filenames = app(DocumentFilenameService::class);

        $this->assertSame(
            'Quotation - Appropriate Apparels Ltd - EIA, EMP & Parameter Assessment.pdf',
            $filenames->quotationFilename($quotation)
        );
        $this->assertSame(
            'Quotation - Appropriate Apparels Ltd - Multiple Environmental Services.pdf',
            $filenames->quotationFilename($long)
        );
    }

    public function test_filename_sanitizes_invalid_client_characters_and_falls_back_without_service(): void
    {
        [$quotation] = $this->quotationFor('Maria/Attires: Ltd? <Unit>', []);
        $quotation->items()->create([
            'description' => 'This custom description is intentionally very long and not a reliable client-facing service filename segment.',
            'unit' => 'Job',
            'quantity' => 1,
            'unit_rate' => 1000,
            'amount' => 1000,
            'sort_order' => 1,
        ]);

        $this->assertSame(
            'Quotation - Maria Attires Ltd Unit.pdf',
            app(DocumentFilenameService::class)->quotationFilename($quotation)
        );
    }

    public function test_quotation_download_response_uses_client_friendly_filename_and_preserves_reference(): void
    {
        [$quotation, $user] = $this->quotationFor('Maria Attires', [
            $this->service('Environmental Impact Assessment Package', 'EIA Package'),
        ]);

        $response = $this->actingAs($user)->get(route('quotations.pdf', $quotation));

        $response->assertOk();
        $this->assertStringContainsString(
            'Quotation - Maria Attires - EIA Package.pdf',
            $response->headers->get('content-disposition')
        );
        $this->assertSame('SMSEA/QT/2026/0001', $quotation->fresh()->number);
    }

    public function test_duplicate_quotation_uses_current_duplicate_client_and_services_for_filename(): void
    {
        [$quotation, $user] = $this->quotationFor('Original Client Ltd', [
            $this->service('Environmental Impact Assessment', 'EIA'),
        ]);

        $this->actingAs($user)->post(route('quotations.duplicate', $quotation))->assertRedirect();
        $copy = Quotation::with('items.service')->latest('id')->firstOrFail();
        $copy->forceFill([
            'client_snapshot' => [
                ...$copy->client_snapshot,
                'company_name' => 'Copied Client Ltd',
            ],
        ])->save();

        $this->assertSame(
            'Quotation - Copied Client Ltd - EIA.pdf',
            app(DocumentFilenameService::class)->quotationFilename($copy)
        );
    }

    public function test_proforma_invoice_download_uses_same_client_friendly_pattern(): void
    {
        [$quotation, $user, $client, $bank, $service] = $this->quotationFor('Maria Attires', [
            $this->service('Environmental Impact Assessment Package', 'EIA Package'),
        ]);
        $invoice = ProformaInvoice::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => 'SMSEA/PI/2026/0001',
            'date' => '2026-08-08',
            'client_snapshot' => $quotation->client_snapshot,
            'bank_snapshot' => $quotation->bank_snapshot,
            'settings_snapshot' => Setting::current()->toArray(),
            'charge_for' => 'Invoice',
            'subtotal' => 50000,
            'adjustment' => 0,
            'total' => 50000,
        ]);
        $invoice->items()->create([
            'service_id' => $service->id,
            'description' => $service->name,
            'unit' => 'Job',
            'quantity' => 1,
            'unit_rate' => 50000,
            'amount' => 50000,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('proforma-invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString(
            'Proforma Invoice - Maria Attires - EIA Package.pdf',
            $response->headers->get('content-disposition')
        );
    }

    private function quotationFor(string $clientName, array $services): array
    {
        $user = User::factory()->create();
        $this->settings();
        $client = Client::query()->create(['company_name' => $clientName, 'address' => 'Dhaka, Bangladesh']);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'is_active' => true,
        ]);
        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'created_by' => $user->id,
            'number' => sprintf('SMSEA/QT/2026/%04d', Quotation::query()->withTrashed()->count() + 1),
            'date' => '2026-08-08',
            'client_snapshot' => $client->only(['company_name', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'subject' => 'Quotation',
            'intro_text' => 'Intro.',
            'subtotal' => count($services) * 1000,
            'adjustment' => 0,
            'vat_treatment' => 'exclusive',
            'vat_amount' => 0,
            'total' => count($services) * 1000,
        ]);

        foreach ($services as $index => $service) {
            $quotation->items()->create([
                'service_id' => $service->id,
                'description' => $service->name,
                'unit' => 'Job',
                'quantity' => 1,
                'unit_rate' => 1000,
                'amount' => 1000,
                'sort_order' => $index + 1,
            ]);
        }

        return [$quotation->load('items.service'), $user, $client, $bank, $services[0] ?? null];
    }

    private function service(string $name, ?string $shortName): Service
    {
        return Service::query()->create([
            'name' => $name,
            'short_name' => $shortName,
            'default_description' => $name,
            'default_unit' => 'Job',
            'default_rate' => 0,
            'is_active' => true,
        ]);
    }

    private function settings(): void
    {
        if (Setting::query()->exists()) {
            return;
        }

        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'default_payment_terms' => 'Payment terms.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);
    }
}
