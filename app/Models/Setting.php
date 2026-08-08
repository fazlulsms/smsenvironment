<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'organization_name',
        'logo_path',
        'tagline',
        'office_address',
        'phone',
        'email',
        'website',
        'default_currency',
        'currency_major_name',
        'currency_minor_name',
        'prepared_by_name',
        'prepared_by_designation',
        'default_payment_terms',
        'quotation_subject_pattern',
        'quotation_intro_text',
        'quotation_compliance_note',
        'quotation_closing_text',
        'quotation_validity_text',
        'quotation_default_notes',
        'quotation_scope_assessment',
        'quotation_methodology',
        'quotation_deliverables',
        'quotation_client_responsibilities',
        'quotation_vat_treatment',
        'quotation_vat_rate',
        'quotation_show_vat_separately',
        'quotation_vat_note',
        'quotation_ait_note',
        'quotation_terms_conditions',
        'quotation_include_acceptance',
        'quotation_acceptance_text',
        'invoice_charge_for_pattern',
        'invoice_payment_terms',
        'invoice_validity_text',
        'invoice_default_notes',
        'footer_text',
        'pdf_note',
        'quotation_number_format',
        'invoice_number_format',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            'quotation_include_acceptance' => true,
        ]);
    }

    protected function casts(): array
    {
        return [
            'quotation_vat_rate' => 'decimal:3',
            'quotation_show_vat_separately' => 'boolean',
            'quotation_include_acceptance' => 'boolean',
        ];
    }
}
