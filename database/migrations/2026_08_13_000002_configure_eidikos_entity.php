<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give Eidikos Cert its own document identity as saved entity configuration
 * (branding, contact, bank, terms) so its distinct invoice profile renders from
 * data, not hard-coded Blade. Idempotent; only touches the EIDIKOS entity.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $eidikosId = DB::table('business_entities')->where('entity_code', 'EIDIKOS')->value('id');

        if (! $eidikosId) {
            return;
        }

        DB::table('business_entities')->where('id', $eidikosId)->update([
            'name' => 'Eidikos Cert',
            'legal_name' => 'Eidikos Cert.',
            'tagline' => 'Audit · Certification · Inspection · Conformity Assessment',
            'address' => 'Level: 04, House: 04, Road: 07, Sector: 03, Rajlakshmi, Uttara, Dhaka-1230, Bangladesh',
            'phone' => '+8809696041938',
            'email' => 'info@eidikoscert.com',
            'website' => 'www.eidikoscert.com',
            'default_currency' => 'BDT',
            'primary_color' => '#1d4ed8',   // deep blue
            'secondary_color' => '#15803d', // green
            'accent_color' => '#64748b',    // soft grey
            'qr_verification_enabled' => false,
            'updated_at' => $now,
        ]);

        DB::table('settings')->updateOrInsert(
            ['business_entity_id' => $eidikosId],
            [
                'organization_name' => 'Eidikos Cert.',
                'tagline' => 'Audit · Certification · Inspection · Conformity Assessment',
                'office_address' => 'Level: 04, House: 04, Road: 07, Sector: 03, Rajlakshmi, Uttara, Dhaka-1230, Bangladesh',
                'phone' => '+8809696041938',
                'email' => 'info@eidikoscert.com',
                'website' => 'www.eidikoscert.com',
                'default_currency' => 'BDT',
                'currency_major_name' => 'Taka',
                'currency_minor_name' => 'Paisa',
                'prepared_by_name' => 'Abir Dey',
                'prepared_by_designation' => 'Head of Operation',
                'invoice_payment_terms' => implode("\n", [
                    'Full payment is required in advance.',
                    'Amount is exclusive of VAT and taxes unless otherwise stated.',
                    'Payment by account payee cheque or bank transfer only.',
                    'This invoice is valid for 30 days from the date of issue.',
                    'Where currency conversion applies, the stated conversion rate is used.',
                ]),
                'invoice_default_notes' => 'Further scheduling of the work plan will take place following confirmation of the applicable payment.',
                'pdf_note' => 'This is a computer-generated invoice and does not require an authorized signature.',
                'footer_text' => 'EIDIKOS CERT. | info@eidikoscert.com | www.eidikoscert.com | +8809696041938',
                'invoice_number_format' => 'EIDIKOS/PI/{YYYY}/{####}',
                'quotation_number_format' => 'EIDIKOS/QT/{YYYY}/{####}',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('bank_accounts')->updateOrInsert(
            ['business_entity_id' => $eidikosId, 'account_number' => '1401991721001'],
            [
                'beneficiary_name' => 'Eidikos Cert.',
                'bank_name' => 'The City Bank Limited',
                'branch' => 'Uttara Branch, Dhaka',
                'swift_code' => 'CIBLBDDH',
                'is_active' => true,
                'is_default' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        $eidikosId = DB::table('business_entities')->where('entity_code', 'EIDIKOS')->value('id');

        if (! $eidikosId) {
            return;
        }

        DB::table('bank_accounts')
            ->where('business_entity_id', $eidikosId)
            ->where('account_number', '1401991721001')
            ->delete();

        DB::table('business_entities')->where('id', $eidikosId)->update([
            'qr_verification_enabled' => true,
        ]);
    }
};
