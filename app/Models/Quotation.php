<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'bank_account_id',
        'created_by',
        'number',
        'date',
        'client_snapshot',
        'bank_snapshot',
        'settings_snapshot',
        'subject',
        'intro_text',
        'compliance_note',
        'scope_assessment',
        'methodology',
        'deliverables',
        'client_responsibilities',
        'closing_text',
        'validity_text',
        'payment_terms',
        'subtotal',
        'adjustment',
        'vat_treatment',
        'vat_rate',
        'vat_amount',
        'show_vat_separately',
        'vat_note',
        'ait_note',
        'terms_conditions',
        'include_acceptance',
        'acceptance_text',
        'verification_payload_version',
        'verification_id',
        'verification_signature',
        'total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'client_snapshot' => 'array',
            'bank_snapshot' => 'array',
            'settings_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:2',
            'show_vat_separately' => 'boolean',
            'include_acceptance' => 'boolean',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(DocumentEmailDelivery::class, 'document_id')
            ->where('document_type', 'quotation')
            ->latest('sent_at')
            ->latest('id');
    }
}
