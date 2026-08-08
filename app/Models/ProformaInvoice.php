<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoice extends Model
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
        'charge_for',
        'payment_terms',
        'validity_text',
        'subtotal',
        'adjustment',
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
}
