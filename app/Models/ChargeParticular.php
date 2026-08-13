<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable commercial charge wording, shared globally across entities. A pure
 * wording template: selecting one copies its text into the document snapshot; it
 * carries no price and never binds historical documents to live master wording.
 */
class ChargeParticular extends Model
{
    protected $fillable = [
        'name', 'category', 'search_keywords', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('category')->orderBy('sort_order')->orderBy('name');
    }

    /** Forgiving search across name, aliases/keywords and category. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('search_keywords', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }
}
