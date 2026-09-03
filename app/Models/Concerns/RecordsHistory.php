<?php

namespace App\Models\Concerns;

use App\Models\RecordHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records an immutable change-history event whenever a material business record
 * is created, updated, (soft-)deleted or restored. Only meaningful field
 * changes are stored — timestamp-only saves and unchanged saves produce no
 * noise. Secret fields (declared via historySecret()) are never written to the
 * trail; a safe metadata note is stored instead.
 *
 * A model may customise:
 *   - historyExcluded(): fields never diffed or stored (defaults cover timestamps).
 *   - historySecret():   sensitive fields — presence-only, values never stored.
 */
trait RecordsHistory
{
    public static function bootRecordsHistory(): void
    {
        static::created(fn ($model) => $model->writeHistory(RecordHistory::ACTION_CREATED));
        static::updated(fn ($model) => $model->writeHistory(RecordHistory::ACTION_UPDATED));
        static::deleted(function ($model) {
            // Force-deletes are rare and Super-Admin-only; still record the event.
            $model->writeHistory(RecordHistory::ACTION_DELETED);
        });

        // restored() only exists on SoftDeletes models.
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => $model->writeHistory(RecordHistory::ACTION_RESTORED));
        }
    }

    public function histories(): MorphMany
    {
        return $this->morphMany(RecordHistory::class, 'auditable')->latest('id');
    }

    /** Timestamp/soft-delete columns are always excluded, on top of these. */
    private const ALWAYS_EXCLUDED = ['created_at', 'updated_at', 'deleted_at'];

    /** Model-specific fields to never diff or store (default: none). */
    public function historyExcluded(): array
    {
        return [];
    }

    /** Sensitive fields — a change is noted, but old/new values are never stored. */
    public function historySecret(): array
    {
        return [];
    }

    /**
     * Append an explicit history event (for changes that are not plain attribute
     * writes, e.g. a pivot/relationship change). before/after are free-form.
     */
    public function recordHistoryEvent(string $action, array $before = [], array $after = [], array $changedFields = [], ?string $note = null): void
    {
        RecordHistory::query()->create([
            'business_entity_id' => $this->historyEntityId(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'changed_by' => Auth::id(),
            'before_json' => $before ?: null,
            'after_json' => $after ?: null,
            'changed_fields_json' => $changedFields ?: null,
            'note' => $note,
        ]);
    }

    protected function writeHistory(string $action): void
    {
        $excluded = array_merge(self::ALWAYS_EXCLUDED, $this->historyExcluded());
        $secret = $this->historySecret();

        if ($action === RecordHistory::ACTION_UPDATED) {
            $changes = $this->getChanges();                 // new values that changed

            $fields = array_values(array_diff(array_keys($changes), $excluded));

            // A secret changed → record presence only, no values.
            $secretChanged = array_intersect($fields, $secret);
            $fields = array_values(array_diff($fields, $secret));

            if (empty($fields) && empty($secretChanged)) {
                return; // timestamp-only / unchanged — no history noise
            }

            // Use cast values for both sides so formatting is consistent
            // (e.g. decimals as "100000.00", dates as ISO strings).
            $before = [];
            $after = [];
            foreach ($fields as $f) {
                $before[$f] = $this->getOriginal($f);
                $after[$f] = $this->getAttribute($f);
            }

            $note = ! empty($secretChanged)
                ? $this->secretChangeNote($secretChanged)
                : null;

            $this->recordHistoryEvent($action, $before, $after, $fields, $note);

            return;
        }

        // For created / deleted / restored we snapshot the record's own values so
        // the trail preserves what it was (a deleted payment keeps its amount).
        $snapshot = $this->historySnapshot($excluded, $secret);

        if ($action === RecordHistory::ACTION_CREATED) {
            $this->recordHistoryEvent($action, [], $snapshot, array_keys($snapshot));

            return;
        }

        if ($action === RecordHistory::ACTION_DELETED) {
            $this->recordHistoryEvent($action, $snapshot, [], array_keys($snapshot));

            return;
        }

        // restored — the record's current values are the "after".
        $this->recordHistoryEvent($action, [], $snapshot, array_keys($snapshot));
    }

    /** Filtered copy of the record's current attributes (cast; no noise, no secrets). */
    private function historySnapshot(array $excluded, array $secret): array
    {
        $out = [];
        foreach (array_keys($this->getAttributes()) as $key) {
            if (in_array($key, $excluded, true) || in_array($key, $secret, true)) {
                continue;
            }
            $out[$key] = $this->getAttribute($key);
        }

        return $out;
    }

    protected function secretChangeNote(array $secretFields): string
    {
        $labels = array_map(fn ($f) => str_replace('_', ' ', $f), $secretFields);

        return 'Credential/secret updated: '.implode(', ', $labels).' (values not stored).';
    }

    protected function historyEntityId(): ?int
    {
        if (array_key_exists('business_entity_id', $this->getAttributes())) {
            return $this->getAttribute('business_entity_id');
        }

        return null;
    }
}
