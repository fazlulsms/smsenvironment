<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'category',
        'default_description',
        'default_unit',
        'default_rate',
        'quotation_subject_template',
        'quotation_scope',
        'compliance_note',
        'invoice_description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
