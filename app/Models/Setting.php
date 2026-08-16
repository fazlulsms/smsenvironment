<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use App\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToBusinessEntity;

    protected $fillable = [
        'business_entity_id',
        'organization_name',
        'logo_path',
        'tagline',
        'office_address',
        'phone',
        'email',
        'website',
        'default_currency',
        'default_conversion_rate',
        'currency_major_name',
        'currency_minor_name',
        'prepared_by_name',
        'prepared_by_designation',
        'default_payment_terms',
        'quotation_subject_pattern',
        'quotation_email_subject_template',
        'quotation_email_body_template',
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
        'proforma_invoice_email_subject_template',
        'proforma_invoice_email_body_template',
        'default_email_cc',
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
        // Seed a new entity's settings from its BusinessEntity identity so it is
        // usable immediately (own name, currency and numbering) before manual
        // configuration. The global scope keeps this row bound to the entity.
        $entity = app(CurrentEntity::class)->get();
        $code = $entity?->entity_code ?: 'SMSEA';

        return self::query()->firstOrCreate([], [
            'organization_name' => $entity?->name ?: 'SMS Environmental Alliance',
            'tagline' => $entity?->tagline,
            'logo_path' => $entity?->logo_path,
            'office_address' => $entity?->address,
            'phone' => $entity?->phone,
            'email' => $entity?->email,
            'website' => $entity?->website,
            'default_currency' => $entity?->default_currency ?: 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'quotation_number_format' => $code.'/QT/{YYYY}/{####}',
            'invoice_number_format' => $code.'/PI/{YYYY}/{####}',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            'quotation_include_acceptance' => true,
        ]);
    }

    protected function casts(): array
    {
        return [
            'quotation_vat_rate' => 'decimal:3',
            'default_conversion_rate' => 'decimal:4',
            'quotation_show_vat_separately' => 'boolean',
            'quotation_include_acceptance' => 'boolean',
        ];
    }
}
