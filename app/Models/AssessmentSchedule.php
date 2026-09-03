<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class AssessmentSchedule extends Model
{
    use BelongsToBusinessEntity;
    use RecordsHistory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PLANNED => 'Planned',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'business_entity_id', 'client_id', 'proforma_invoice_id', 'client_name',
        'service_name', 'site_name', 'location', 'scheduled_from', 'scheduled_to',
        'assessment_days', 'status', 'note', 'completed_date', 'next_reassessment_date',
        'reminder_enabled', 'reminder_sent_at', 'reminder_sent_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_from' => 'date',
            'scheduled_to' => 'date',
            'completed_date' => 'date',
            'next_reassessment_date' => 'date',
            'assessment_days' => 'integer',
            'reminder_enabled' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class, 'proforma_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assessors(): BelongsToMany
    {
        return $this->belongsToMany(Assessor::class);
    }

    /** Total assessor-days = assessment days × number of assigned assessors (derived). */
    public function assessorDays(): int
    {
        return (int) $this->assessment_days * $this->assessors->count();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Working span in days from the date range (inclusive), min 1. */
    public static function daysBetween(?string $from, ?string $to): int
    {
        if (! $from || ! $to) {
            return 1;
        }

        return max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);
    }

    public function scopeDueReminders(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->where('reminder_enabled', true)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('next_reassessment_date');
    }
}
