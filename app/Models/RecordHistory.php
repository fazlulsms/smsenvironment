<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One immutable change event for an audited record. Rows are written only by
 * the RecordsHistory trait (and a few explicit controller calls); there is no
 * update/delete path through the Office UI, so the trail cannot be rewritten.
 */
class RecordHistory extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_RESTORED = 'restored';

    protected $fillable = [
        'business_entity_id', 'auditable_type', 'auditable_id', 'action',
        'changed_by', 'before_json', 'after_json', 'changed_fields_json', 'note',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'changed_fields_json' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_RESTORED => 'Restored',
            default => 'Edited',
        };
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'b-ok',
            self::ACTION_DELETED => 'b-danger',
            self::ACTION_RESTORED => 'b-info',
            default => 'b-neutral',
        };
    }

    /**
     * Human-readable field changes for the UI, e.g.
     * "Total: BDT 100,000 → BDT 120,000". Skips presentation of raw arrays.
     *
     * @return array<int, array{field:string, label:string, from:string, to:string}>
     */
    public function readableChanges(): array
    {
        $fields = $this->changed_fields_json ?: [];
        $before = $this->before_json ?: [];
        $after = $this->after_json ?: [];

        $out = [];
        foreach ($fields as $field) {
            $out[] = [
                'field' => $field,
                'label' => ucfirst(str_replace('_', ' ', $field)),
                'from' => $this->scalar($before[$field] ?? null),
                'to' => $this->scalar($after[$field] ?? null),
            ];
        }

        return $out;
    }

    private function scalar($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
