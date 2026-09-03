<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use App\Models\Concerns\HasCommercialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToBusinessEntity;
    use HasCommercialStatus;
    use SoftDeletes;

    protected $fillable = [
        'business_entity_id',
        'entity_code',
        'client_id',
        'service_category_id',
        'bank_account_id',
        'created_by',
        'number',
        'date',
        'client_snapshot',
        'bank_snapshot',
        'settings_snapshot',
        'standards_snapshot',
        'charge_presentation',
        'charge_title',
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
        'commercial_status',
        'lost_reason',
        'lost_note',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'client_snapshot' => 'array',
            'bank_snapshot' => 'array',
            'settings_snapshot' => 'array',
            'standards_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:2',
            'show_vat_separately' => 'boolean',
            'include_acceptance' => 'boolean',
            'total' => 'decimal:2',
            'status_updated_at' => 'datetime',
        ];
    }

    /** Proforma invoices created from this quotation (linked commercial engagement). */
    public function invoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }

    /** Quotations are single-currency (platform base, BDT). */
    public function payableCurrency(): string
    {
        return 'BDT';
    }

    public function baseTotal(): float
    {
        return (float) $this->total;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
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

    /**
     * A document that has been emailed to a recipient is treated as "issued": it
     * left the building and may be relied upon / QR-verified, so it is protected
     * from casual deletion. There is no separate status lifecycle by design.
     */
    public function wasEmailed(): bool
    {
        return $this->emailDeliveries()->exists();
    }
}
