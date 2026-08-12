<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoice extends Model
{
    use BelongsToBusinessEntity;
    use SoftDeletes;

    protected $fillable = [
        'business_entity_id',
        'entity_code',
        'client_id',
        'bank_account_id',
        'created_by',
        'number',
        'date',
        'client_snapshot',
        'bank_snapshot',
        'settings_snapshot',
        'charge_for',
        'payment_terms',
        'validity_text',
        'subtotal',
        'adjustment',
        'vat_treatment',
        'vat_rate',
        'vat_amount',
        'show_vat_separately',
        'total',
        'notes',
        'verification_payload_version',
        'verification_id',
        'verification_signature',
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
        return $this->hasMany(ProformaInvoiceItem::class)->orderBy('sort_order');
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(DocumentEmailDelivery::class, 'document_id')
            ->where('document_type', 'proforma_invoice')
            ->latest('sent_at')
            ->latest('id');
    }
}
