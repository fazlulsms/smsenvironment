<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $backfillTables = [
        'clients',
        'services',
        'quotations',
        'proforma_invoices',
        'bank_accounts',
        'document_email_deliveries',
        'settings',
    ];

    public function up(): void
    {
        $now = now();

        // Pull SMSEA identity from the existing (global) settings row so the
        // switcher and dashboard show real information immediately.
        $settings = DB::table('settings')->orderBy('id')->first();

        $entities = [
            [
                'entity_code' => 'SMSEA',
                'name' => $settings?->organization_name ?? 'SMS Environmental Alliance',
                'short_name' => 'SMSEA',
                'legal_name' => $settings?->organization_name ?? 'SMS Environmental Alliance',
                'tagline' => $settings?->tagline,
                'logo_path' => $settings?->logo_path,
                'address' => $settings?->office_address,
                'phone' => $settings?->phone,
                'email' => $settings?->email,
                'website' => $settings?->website,
                'default_currency' => $settings?->default_currency ?? 'BDT',
                'is_default' => true,
            ],
            ['entity_code' => 'EIDIKOS', 'name' => 'Eidikos Cert', 'short_name' => 'Eidikos'],
            ['entity_code' => 'ECOVERITAS', 'name' => 'EcoVeritas International', 'short_name' => 'EcoVeritas'],
            ['entity_code' => 'MAXINT', 'name' => 'Max International', 'short_name' => 'Max Intl'],
            ['entity_code' => 'ICQMS', 'name' => 'ICQMS', 'short_name' => 'ICQMS'],
        ];

        foreach ($entities as $entity) {
            DB::table('business_entities')->updateOrInsert(
                ['entity_code' => $entity['entity_code']],
                array_merge([
                    'active' => true,
                    'is_default' => false,
                    'default_currency' => 'BDT',
                    'quotation_enabled' => true,
                    'proforma_invoice_enabled' => true,
                    'email_enabled' => true,
                    'qr_verification_enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ], $entity)
            );
        }

        $smseaId = DB::table('business_entities')->where('entity_code', 'SMSEA')->value('id');

        if ($smseaId) {
            foreach ($this->backfillTables as $table) {
                DB::table($table)->whereNull('business_entity_id')->update(['business_entity_id' => $smseaId]);
            }

            DB::table('quotations')->whereNull('entity_code')->update(['entity_code' => 'SMSEA']);
            DB::table('proforma_invoices')->whereNull('entity_code')->update(['entity_code' => 'SMSEA']);
        }
    }

    public function down(): void
    {
        foreach ($this->backfillTables as $table) {
            DB::table($table)->update(['business_entity_id' => null]);
        }

        DB::table('quotations')->update(['entity_code' => null]);
        DB::table('proforma_invoices')->update(['entity_code' => null]);
        DB::table('business_entities')->whereIn('entity_code', ['SMSEA', 'EIDIKOS', 'ECOVERITAS', 'MAXINT', 'ICQMS'])->delete();
    }
};
