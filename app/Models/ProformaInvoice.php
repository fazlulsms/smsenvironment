<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use App\Models\Concerns\HasCommercialStatus;
use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoice extends Model
{
    use BelongsToBusinessEntity;
    use HasCommercialStatus;
    use RecordsHistory;
    use SoftDeletes;

    public const PRESENTATION_CONSOLIDATED = 'consolidated';

    public const PRESENTATION_BREAKDOWN = 'component_breakdown';

    public const PRESENTATION_ITEMIZED = 'itemized';

    public const PRESENTATIONS = [
        self::PRESENTATION_CONSOLIDATED => 'Consolidated Fee',
        self::PRESENTATION_BREAKDOWN => 'Fee Breakdown — One Total',
        self::PRESENTATION_ITEMIZED => 'Itemized Charges',
    ];

    protected $fillable = [
        'business_entity_id',
        'entity_code',
        'client_id',
        'quotation_id',
        'service_category_id',
        'bank_account_id',
        'created_by',
        'number',
        'reference_no',
        'date',
        'currency',
        'conversion_rate',
        'client_snapshot',
        'bank_snapshot',
        'settings_snapshot',
        'standards_snapshot',
        'charge_presentation',
        'charge_title',
        'charge_for',
        'site_name',
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
        'commercial_status',
        'lost_reason',
        'lost_note',
        'status_updated_at',
    ];

    /** Derived snapshot/verification columns are regenerated on save — not history. */
    public function historyExcluded(): array
    {
        return [
            'client_snapshot', 'bank_snapshot', 'settings_snapshot', 'standards_snapshot',
            'verification_payload_version', 'verification_id', 'verification_signature',
            'entity_code', 'created_by',
        ];
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'conversion_rate' => 'decimal:4',
            'client_snapshot' => 'array',
            'bank_snapshot' => 'array',
            'settings_snapshot' => 'array',
            'standards_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:2',
            'show_vat_separately' => 'boolean',
            'total' => 'decimal:2',
            'status_updated_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest('received_date')->latest('id');
    }

    /** Sum of recorded payments (in the invoice payable currency). */
    public function receivedAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function dueAmount(): float
    {
        return round((float) $this->total - $this->receivedAmount(), 2);
    }

    /** unpaid | partial | paid — derived from payments, never stored redundantly. */
    public function paymentStatus(): string
    {
        $received = $this->receivedAmount();
        if ($received <= 0) {
            return 'unpaid';
        }

        return $received + 0.001 >= (float) $this->total ? 'paid' : 'partial';
    }

    public function payableCurrency(): string
    {
        return strtoupper((string) ($this->currency ?: 'BDT')) ?: 'BDT';
    }

    /** Total expressed in the platform base currency (BDT) using the saved rate. */
    public function baseTotal(): float
    {
        if ($this->payableCurrency() === 'BDT') {
            return (float) $this->total;
        }

        return $this->conversion_rate > 0 ? round((float) $this->total * (float) $this->conversion_rate, 2) : (float) $this->total;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The accepted quotation this invoice was created from, if any. */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
        return $this->hasMany(ProformaInvoiceItem::class)->orderBy('sort_order');
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(DocumentEmailDelivery::class, 'document_id')
            ->where('document_type', 'proforma_invoice')
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
