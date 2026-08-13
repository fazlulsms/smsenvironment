<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A global service family (e.g. "ISO Management System Certification"). Shared
 * across every entity; owns the selectable {@see Standard} records beneath it.
 */
class ServiceCategory extends Model
{
    protected $fillable = [
        'code', 'name', 'short_name', 'selection_label', 'description', 'active', 'display_order',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function standards(): HasMany
    {
        return $this->hasMany(Standard::class)->orderBy('display_order')->orderBy('name');
    }

    public function activeStandards(): HasMany
    {
        return $this->standards()->where('active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /** The context-sensitive picker label, e.g. "Select Standards / Schemes". */
    public function selectionLabel(): string
    {
        return $this->selection_label ?: 'Standards / Programs';
    }
}
