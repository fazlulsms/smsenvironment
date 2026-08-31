<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\ChargeParticular;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\ServiceCategory;
use App\Models\Standard;
use App\Services\ClientImportService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueClientSyncTest extends TestCase
{
    use RefreshDatabase;

    // ---- SERVICE CATALOGUE SYNC -------------------------------------------

    public function test_sync_service_catalogue_populates_and_is_idempotent(): void
    {
        $this->assertSame(0, Standard::query()->count(), 'fresh DB starts empty');

        $this->artisan('smsea:sync-service-catalogue')->assertSuccessful();

        $categories = ServiceCategory::query()->count();
        $standards = Standard::query()->count();
        $particulars = ChargeParticular::query()->count();

        $this->assertGreaterThanOrEqual(12, $categories);
        $this->assertGreaterThanOrEqual(160, $standards);
        $this->assertGreaterThanOrEqual(90, $particulars);

        // EIA + Environmental Parameter Testing present.
        $this->assertTrue(Standard::query()->where('name', 'like', '%Environmental Impact Assessment%')->exists());
        $this->assertTrue(Standard::query()->where('name', 'like', '%Environmental Parameter Testing%')->exists());

        // Public isolation: exactly the curated environmental items are flagged public.
        $this->assertSame(3, Standard::query()->where('is_public', true)->count());

        // Second run must not duplicate anything.
        $this->artisan('smsea:sync-service-catalogue')->assertSuccessful();
        $this->assertSame($categories, ServiceCategory::query()->count());
        $this->assertSame($standards, Standard::query()->count());
        $this->assertSame($particulars, ChargeParticular::query()->count());
    }

    public function test_sync_clients_command_imports_real_data_file_without_duplicates(): void
    {
        $file = database_path('data/clients.php');
        $this->assertFileExists($file);
        $source = require $file;

        $this->artisan('smsea:sync-clients')->assertSuccessful();
        $firstCount = Client::query()->count();
        $this->assertSame(count($source), $firstCount);

        // Idempotent: a second run creates nothing new.
        $this->artisan('smsea:sync-clients')->assertSuccessful();
        $this->assertSame($firstCount, Client::query()->count());
    }

    // ---- CLIENT IMPORT MATCHING -------------------------------------------

    private function records(): array
    {
        return [
            ['client_code' => 'SMS-BD-001', 'company_name' => 'Alpha Textiles Ltd.', 'email' => 'info@alpha.test', 'contact_person' => 'A Person', 'city' => 'Dhaka', 'country' => 'Bangladesh'],
            ['client_code' => 'SMS-BD-002', 'company_name' => 'Beta Garments Ltd.', 'email' => 'info@beta.test'],
        ];
    }

    public function test_import_creates_new_clients(): void
    {
        $result = app(ClientImportService::class)->import($this->records());

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('clients', ['company_name' => 'Alpha Textiles Ltd.', 'client_code' => 'SMS-BD-001']);
    }

    public function test_import_is_idempotent(): void
    {
        $service = app(ClientImportService::class);
        $service->import($this->records());
        $result = $service->import($this->records());

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, Client::query()->count());
    }

    public function test_import_matches_existing_client_by_name_and_preserves_id_and_relationships(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));

        // Existing production-style client with a linked invoice, no client_code yet.
        $client = Client::query()->create(['company_name' => 'P.A. Knit Composite Ltd.', 'address' => 'Bhaluka']);
        $invoice = ProformaInvoice::query()->create([
            'client_id' => $client->id, 'number' => 'SMSEA/PI/2026/9001', 'date' => now()->toDateString(),
            'charge_presentation' => 'consolidated', 'vat_treatment' => 'exclusive', 'vat_rate' => 0,
            'vat_amount' => 0, 'subtotal' => 100, 'adjustment' => 0, 'total' => 100,
        ]);

        // Import a record for the same company (different punctuation) with new details.
        $result = app(ClientImportService::class)->import([
            ['client_code' => 'SMS-BD-777', 'company_name' => 'P.A. Knit Composite Ltd', 'email' => 'pa@knit.test', 'contact_person' => 'Mr X'],
        ]);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Client::query()->count(), 'no duplicate client created');

        $client->refresh();
        $this->assertSame('SMS-BD-777', $client->client_code, 'blank client_code filled');
        $this->assertSame('pa@knit.test', $client->email, 'blank email filled');
        $this->assertSame($invoice->client_id, $client->id, 'invoice still linked to same client id');
    }

    public function test_import_does_not_overwrite_existing_nonblank_fields(): void
    {
        $client = Client::query()->create(['company_name' => 'Gamma Ltd.', 'email' => 'original@gamma.test', 'address' => 'HQ']);

        app(ClientImportService::class)->import([
            ['company_name' => 'Gamma Ltd.', 'email' => 'different@gamma.test'],
        ]);

        $this->assertSame('original@gamma.test', $client->fresh()->email, 'existing email preserved');
    }

    public function test_import_flags_ambiguous_matches_for_manual_review(): void
    {
        // Two existing client masters that normalize to the same name (a pre-existing
        // duplicate). An incoming record for that name resolves to both → ambiguous.
        Client::query()->create(['company_name' => 'Delta Ltd.', 'address' => 'A']);
        Client::query()->create(['company_name' => 'Delta Ltd', 'address' => 'B']);

        $result = app(ClientImportService::class)->import([
            ['company_name' => 'Delta Ltd.', 'email' => 'info@delta.test'],
        ]);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(1, $result['ambiguous']);
        $this->assertSame(2, Client::query()->count(), 'nothing created for ambiguous record');
    }

    public function test_import_does_not_merge_sister_companies_sharing_an_email(): void
    {
        // Real-data hazard: sister factories share a group email. They must remain
        // two distinct clients, not be merged.
        $result = app(ClientImportService::class)->import([
            ['company_name' => 'Flamingo Fashions Ltd', 'email' => 'group@dbl.test', 'address' => 'X'],
            ['company_name' => 'Jinnat Fashions Ltd', 'email' => 'group@dbl.test', 'address' => 'X'],
        ]);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, Client::query()->count());
    }
}
