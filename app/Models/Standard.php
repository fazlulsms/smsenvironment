<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A selectable standard / scheme / program / service scope under a
 * {@see ServiceCategory}. Global master shared across entities; a document
 * snapshots the selected standards so history is immutable.
 */
class Standard extends Model
{
    protected $fillable = [
        'service_category_id', 'code', 'slug', 'name', 'short_name', 'type',
        'description', 'default_scope', 'active', 'is_public', 'display_order',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_public' => 'boolean'];
    }

    /** Catalogue items opted in to the public marketing website. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /** Search across full name, short name and code (ISO 9001 / 9001 / GOTS / BSCI). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('short_name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    /** Concise label for tight layouts: short name, else code, else full name. */
    public function shortLabel(): string
    {
        return $this->short_name ?: ($this->code ?: $this->name);
    }

    /** Default scope/components (package items) as an array, newline-separated. */
    public function defaultScope(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->default_scope))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /** The immutable record stored on a document. */
    public function toSnapshot(int $order): array
    {
        return [
            'standard_id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'order' => $order,
        ];
    }
}
