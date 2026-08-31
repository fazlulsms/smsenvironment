<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_logos_are_version_controlled(): void
    {
        // The approved brand logos must live in the committed public asset dir so
        // DOMPDF can resolve them in production (not in git-ignored storage).
        $this->assertFileExists(public_path('images/brand/smsea-logo.png'));
        $this->assertFileExists(public_path('images/brand/eidikos-logo.png'));
        $this->assertFileExists(public_path('images/brand/ecoveritas-logo.png'));
    }

    public function test_sync_sets_smsea_identity_logo_and_banks(): void
    {
        $this->artisan('smsea:sync-company-settings')->assertSuccessful();

        $smsea = BusinessEntity::query()->where('entity_code', 'SMSEA')->first();
        $this->assertNotNull($smsea);
        $this->assertSame('01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town, Dhaka-1230, Bangladesh', $smsea->address);
        $this->assertSame('+8801873035178', $smsea->phone);
        $this->assertSame('info@smsenvironment.com', $smsea->email);
        $this->assertSame('logos/smsea-logo.png', $smsea->logo_path);

        // The entity's Setting row (used by the invoice footer) carries the address.
        $setting = Setting::withoutGlobalScopes()->where('business_entity_id', $smsea->id)->first();
        $this->assertNotNull($setting);
        $this->assertStringContainsString('Sonargaon Janapath', (string) $setting->office_address);
        $this->assertStringContainsString('smsenvironment.com', (string) $setting->footer_text);

        // SMSEA banks present with correct SWIFT codes, scoped to SMSEA.
        $prime = BankAccount::withoutGlobalScopes()->where('account_number', '2170316017001')->first();
        $mtb = BankAccount::withoutGlobalScopes()->where('account_number', '1301000014453')->first();
        $this->assertNotNull($prime);
        $this->assertSame('PRBLBDDH', $prime->swift_code);
        $this->assertSame($smsea->id, $prime->business_entity_id);
        $this->assertNotNull($mtb);
        $this->assertSame('MTBLBDDH', $mtb->swift_code);

        // The synced logo file is copied into storage for DOMPDF.
        $this->assertFileExists(storage_path('app/public/logos/smsea-logo.png'));
    }

    public function test_sync_is_idempotent_and_creates_no_duplicate_banks(): void
    {
        $this->artisan('smsea:sync-company-settings')->assertSuccessful();
        $countA = BankAccount::withoutGlobalScopes()->count();

        $this->artisan('smsea:sync-company-settings')->assertSuccessful();
        $countB = BankAccount::withoutGlobalScopes()->count();

        $this->assertSame($countA, $countB);
        $this->assertSame(1, BankAccount::withoutGlobalScopes()->where('account_number', '2170316017001')->count());
    }

    public function test_sync_does_not_fabricate_missing_entity_contact(): void
    {
        $this->artisan('smsea:sync-company-settings')->assertSuccessful();

        // EcoVeritas has a name + logo but no real contact details — they must stay null.
        $eco = BusinessEntity::query()->where('entity_code', 'ECOVERITAS')->first();
        if ($eco) {
            $this->assertNull($eco->phone);
            $this->assertNull($eco->email);
            $this->assertNull($eco->address);
            $this->assertSame('logos/ecoveritas-logo.png', $eco->logo_path);
        } else {
            $this->markTestSkipped('EcoVeritas entity not present in this database.');
        }
    }
}
