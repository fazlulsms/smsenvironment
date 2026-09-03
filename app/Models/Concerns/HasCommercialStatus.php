<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared commercial (sales) status for a client-facing offer — used by both
 * Quotation and ProformaInvoice. A quotation OR a proforma invoice can each
 * represent the commercial offer; this trait keeps their status semantics
 * identical without duplicating logic.
 */
trait HasCommercialStatus
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const COMMERCIAL_STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Sent',
        self::STATUS_WON => 'Won',
        self::STATUS_LOST => 'Lost',
    ];

    public const LOST_REASONS = ['Price', 'Client Postponed', 'Client Cancelled', 'Competitor', 'No Response', 'Scope Changed', 'Other'];

    public function commercialStatusLabel(): string
    {
        return self::COMMERCIAL_STATUSES[$this->commercial_status] ?? 'Draft';
    }

    public function isWon(): bool
    {
        return $this->commercial_status === self::STATUS_WON;
    }

    public function isLost(): bool
    {
        return $this->commercial_status === self::STATUS_LOST;
    }

    /**
     * Reporting truth for "was this offer sent to the client?" — true when the
     * status was explicitly advanced past draft OR the document was actually
     * emailed through the app (covers historical records created before the
     * commercial_status field existed).
     */
    public function isSentForReporting(): bool
    {
        return $this->commercial_status !== self::STATUS_DRAFT || $this->wasEmailed();
    }

    /** Best "sent" date: explicit status change, else the last successful email, else the document date. */
    public function sentAt(): ?Carbon
    {
        if ($this->commercial_status !== self::STATUS_DRAFT && $this->status_updated_at) {
            return $this->status_updated_at;
        }

        $emailed = $this->emailDeliveries()->where('status', 'sent')->max('sent_at');

        return $emailed ? Carbon::parse($emailed) : ($this->isSentForReporting() ? $this->date : null);
    }
}
