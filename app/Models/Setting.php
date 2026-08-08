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
        ]);
    }
}
