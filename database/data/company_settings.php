<?php

/**
 * Baseline company / business-entity configuration — the source of truth for the
 * production-safe `smsea:sync-company-settings` command. Values are the approved,
 * real identities as developed locally; nothing is invented. Fields that are
 * genuinely unknown for an entity are left null and are never written over
 * existing data.
 *
 * Logos reference committed brand assets under public/images/brand/. The sync
 * command copies them into storage/app/public/logos/ so DOMPDF (which reads the
 * file by absolute path) resolves them reliably in production without depending
 * on git-ignored storage uploads.
 *
 * No secrets here — SMTP/mailbox/API credentials stay in the environment.
 */

return [
    'entities' => [
        'SMSEA' => [
            'name' => 'SMS Environmental Alliance',
            'legal_name' => 'SMS Environmental Alliance',
            'short_name' => 'SMSEA',
            'tagline' => 'Environmental testing, assessment and compliance support',
            'address' => '01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town, Dhaka-1230, Bangladesh',
            'phone' => '+8801873035178',
            'email' => 'info@smsenvironment.com',
            'website' => 'www.smsenvironment.com',
            'default_currency' => 'BDT',
            'logo' => 'smsea-logo.png',
            'footer_text' => 'SMS Environmental Alliance | 01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town, Dhaka-1230, Bangladesh | +8801873035178 | info@smsenvironment.com | www.smsenvironment.com',
        ],

        'EIDIKOS' => [
            'name' => 'Eidikos Cert',
            'legal_name' => 'Eidikos Cert.',
            'short_name' => 'Eidikos',
            'tagline' => 'Audit · Certification · Inspection · Conformity Assessment',
            'address' => 'Level: 04, House: 04, Road: 07, Sector: 03, Rajlakshmi, Uttara, Dhaka-1230, Bangladesh',
            'phone' => '+8809696041938',
            'email' => 'info@eidikoscert.com',
            'website' => 'www.eidikoscert.com',
            'default_currency' => 'BDT',
            'logo' => 'eidikos-logo.png',
            'footer_text' => null,
        ],

        // Name + logo only — no contact details exist for these yet (not invented).
        'ECOVERITAS' => [
            'name' => 'EcoVeritas International',
            'short_name' => 'EcoVeritas',
            'default_currency' => 'BDT',
            'logo' => 'ecoveritas-logo.png',
        ],

        // Name only — no logo or contact details exist yet (not invented).
        'MAXINT' => [
            'name' => 'Max International',
            'short_name' => 'Max Intl',
            'default_currency' => 'BDT',
            'logo' => null,
        ],

        'ICQMS' => [
            'name' => 'ICQMS',
            'short_name' => 'ICQMS',
            'default_currency' => 'BDT',
            'logo' => null,
        ],
    ],

    'banks' => [
        [
            'entity' => 'SMSEA',
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'branch' => 'Garib E Newaj Avenue, Uttara, Dhaka',
            'account_number' => '2170316017001',
            'routing_number' => null,
            'swift_code' => 'PRBLBDDH',
            'is_default' => true,
        ],
        [
            'entity' => 'SMSEA',
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Mutual Trust Bank',
            'branch' => 'Shah Mokhdum Avenue Branch',
            'account_number' => '1301000014453',
            'routing_number' => null,
            'swift_code' => 'MTBLBDDH',
            'is_default' => false,
        ],
        [
            'entity' => 'EIDIKOS',
            'beneficiary_name' => 'Eidikos Cert.',
            'bank_name' => 'The City Bank Limited',
            'branch' => 'Uttara Branch, Dhaka',
            'account_number' => '1401991721001',
            'routing_number' => null,
            'swift_code' => 'CIBLBDDH',
            'is_default' => true,
        ],
    ],
];
