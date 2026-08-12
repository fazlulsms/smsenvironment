<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\QuotationVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiEntityTest extends TestCase
{
    use RefreshDatabase;

    private function entity(string $code): BusinessEntity
    {
        return BusinessEntity::query()->where('entity_code', $code)->firstOrFail();
    }

    private function useEntity(string $code): int
    {
        $id = $this->entity($code)->id;
        app(CurrentEntity::class)->use($id);

        return $id;
    }

    /** Minimal quotation created inside the active entity. */
    private function makeQuotation(float $total = 50000, ?Client $client = null): Quotation
    {
        Setting::current();
        $client ??= Client::query()->create(['company_name' => 'ABC Textiles Ltd.', 'address' => 'Dhaka']);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'Beneficiary', 'bank_name' => 'Test Bank Ltd.',
            'account_number' => '999000111', 'is_active' => true, 'is_default' => true,
        ]);

        $quotation = Quotation::query()->create([
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'number' => app(DocumentNumberService::class)->quotation(),
            'date' => '2026-08-12',
            'client_snapshot' => $client->only(['company_name', 'address']),
            'bank_snapshot' => $bank->only(['beneficiary_name', 'bank_name', 'account_number']),
            'settings_snapshot' => Setting::current()->toArray(),
            'subtotal' => $total, 'adjustment' => 0, 'vat_treatment' => 'exclusive',
            'vat_amount' => 0, 'total' => $total,
        ]);
        $quotation->items()->create([
            'description' => 'Environmental Impact Assessment', 'unit' => 'Job',
            'quantity' => 1, 'unit_rate' => $total, 'amount' => $total, 'sort_order' => 1,
        ]);

        return app(QuotationVerificationService::class)->apply($quotation->load('items'));
    }

    public function test_default_and_placeholder_entities_exist(): void
    {
        $this->assertSame('SMSEA', $this->entity('SMSEA')->entity_code);
        $this->assertTrue($this->entity('SMSEA')->is_default);
        foreach (['EIDIKOS', 'ECOVERITAS', 'MAXINT', 'ICQMS'] as $code) {
            $this->assertTrue(BusinessEntity::query()->where('entity_code', $code)->exists());
        }
    }

    public function test_clients_services_and_banks_are_isolated_by_entity(): void
    {
        $this->useEntity('SMSEA');
        Client::query()->create(['company_name' => 'ABC Textiles Ltd.', 'address' => 'Dhaka']);
        Service::query()->create(['name' => 'EIA', 'service_type' => 'bundle', 'is_active' => true]);
        BankAccount::query()->create(['beneficiary_name' => 'S', 'bank_name' => 'Prime', 'account_number' => '1', 'is_active' => true, 'is_default' => true]);

        $this->assertSame(1, Client::query()->count());

        $this->useEntity('ECOVERITAS');
        $this->assertSame(0, Client::query()->count());
        $this->assertSame(0, Service::query()->count());
        $this->assertSame(0, BankAccount::query()->count());

        // Same company name may exist independently in a second entity.
        Client::query()->create(['company_name' => 'ABC Textiles Ltd.', 'address' => 'Chattogram']);
        $this->assertSame(1, Client::query()->count());
        $this->assertSame(2, Client::query()->acrossEntities()->where('company_name', 'ABC Textiles Ltd.')->count());
    }

    public function test_quotation_numbering_is_isolated_per_entity(): void
    {
        $this->useEntity('SMSEA');
        $this->makeQuotation();
        $this->makeQuotation();
        $this->assertSame(2, Quotation::query()->count());

        $this->useEntity('ECOVERITAS');
        // A fresh entity restarts its own sequence at 0001.
        $number = app(DocumentNumberService::class)->quotation();
        $this->assertStringEndsWith('0001', $number);
        $this->assertSame(0, Quotation::query()->count());
    }

    public function test_cannot_view_another_entitys_quotation_by_url(): void
    {
        $user = User::factory()->create();
        $this->useEntity('SMSEA');
        $quotation = $this->makeQuotation();

        // Same entity: visible.
        $this->actingAs($user)->get(route('quotations.show', $quotation))->assertOk();

        // Switch entity: the quotation must no longer be reachable by id.
        app(CurrentEntity::class)->use($this->entity('ECOVERITAS')->id);
        $this->actingAs($user)->get(route('quotations.show', $quotation->id))->assertNotFound();
    }

    public function test_qr_signature_differs_across_entities_for_identical_data(): void
    {
        $this->useEntity('SMSEA');
        $smseaQuote = $this->makeQuotation(50000);

        $this->useEntity('ECOVERITAS');
        $ecoQuote = $this->makeQuotation(50000);

        $service = app(QuotationVerificationService::class);

        $this->assertSame('SMSEA', $smseaQuote->entity_code);
        $this->assertSame('ECOVERITAS', $ecoQuote->entity_code);
        $this->assertNotSame($smseaQuote->verification_signature, $ecoQuote->verification_signature);
        // Each still verifies against itself.
        $this->assertSame($smseaQuote->verification_signature, $service->signature($smseaQuote->fresh('items')));
        $this->assertSame($ecoQuote->verification_signature, $service->signature($ecoQuote->fresh('items')));
    }

    public function test_legacy_v1_quotation_remains_verifiable_and_is_not_re_signed(): void
    {
        $this->useEntity('SMSEA');
        $quotation = $this->makeQuotation();
        $service = app(QuotationVerificationService::class);

        // Simulate a historical V1 document.
        $quotation->forceFill(['verification_payload_version' => QuotationVerificationService::LEGACY_PAYLOAD_VERSION])->save();
        $legacySignature = $service->signature($quotation->fresh('items'));
        $quotation->forceFill([
            'verification_signature' => $legacySignature,
            'verification_id' => $service->verificationId($legacySignature),
        ])->save();

        // Legacy canonical must not contain the entity code.
        $this->assertArrayNotHasKey('entity_code', $service->canonicalData($quotation->fresh('items')));

        // ensure() keeps it V1 and the signature reproduces exactly.
        $service->ensure($quotation->fresh('items'));
        $this->assertSame(QuotationVerificationService::LEGACY_PAYLOAD_VERSION, $quotation->fresh()->verification_payload_version);
        $this->assertSame($legacySignature, $service->signature($quotation->fresh('items')));
    }

    public function test_document_snapshot_is_unaffected_by_later_entity_setting_change(): void
    {
        $this->useEntity('SMSEA');
        $setting = Setting::current();
        $setting->update(['organization_name' => 'SMS Environmental Alliance']);
        $quotation = $this->makeQuotation();

        $this->assertSame('SMS Environmental Alliance', $quotation->settings_snapshot['organization_name']);

        // Rename the entity settings after the fact.
        $setting->update(['organization_name' => 'Renamed Org']);

        $this->assertSame('SMS Environmental Alliance', $quotation->fresh()->settings_snapshot['organization_name']);
    }

    public function test_entity_switch_endpoint_updates_context(): void
    {
        $user = User::factory()->create();
        $eco = $this->entity('ECOVERITAS');

        $this->actingAs($user)
            ->post(route('entities.switch'), ['entity_id' => $eco->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($eco->id, session(CurrentEntity::SESSION_KEY));
    }
}
