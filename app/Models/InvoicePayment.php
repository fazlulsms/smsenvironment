<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    public const METHODS = ['Bank Transfer', 'Cash', 'Cheque', 'Mobile Financial Service', 'Card', 'Other'];

    protected $fillable = [
        'proforma_invoice_id', 'business_entity_id', 'amount', 'currency',
        'received_date', 'method', 'reference', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class, 'proforma_invoice_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
