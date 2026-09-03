<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Services are a GLOBAL master shared across entities. Per-entity availability
 * is expressed through the business_entity_service pivot, not by scoping.
 */
class Service extends Model
{
    use RecordsHistory;

    public const TYPE_STANDALONE = 'standalone';

    public const TYPE_BUNDLE = 'bundle';

    public const TYPE_CONSOLIDATED = 'consolidated';

    public const TYPES = [
        self::TYPE_STANDALONE => 'Standalone Service',
        self::TYPE_BUNDLE => 'Bundle / Package',
        self::TYPE_CONSOLIDATED => 'Consolidated Professional Service',
    ];

    protected $fillable = [
        'business_entity_id',
        'name',
        'short_name',
        'category',
        'service_type',
        'default_description',
        'default_unit',
        'default_rate',
        'quotation_subject_template',
        'quotation_scope',
        'compliance_note',
        'invoice_description',
        'default_charge_presentation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function components(): HasMany
    {
        return $this->hasMany(ServiceComponent::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeComponents(): HasMany
    {
        return $this->components()->where('is_active', true);
    }

    public function defaultPricingMode(): string
    {
        return $this->service_type === self::TYPE_STANDALONE ? 'separate' : 'consolidated';
    }

    public function businessEntities(): BelongsToMany
    {
        return $this->belongsToMany(BusinessEntity::class, 'business_entity_service')
            ->withPivot(['active', 'default_amount'])
            ->withTimestamps();
    }

    /**
     * Services available to an entity: those explicitly enabled for it, plus any
     * service with no availability configured at all (safe default = available).
     */
    public function scopeAvailableForEntity(Builder $query, ?int $entityId): Builder
    {
        if (! $entityId) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($entityId) {
            $query->whereHas('businessEntities', fn ($q) => $q
                ->where('business_entities.id', $entityId)
                ->where('business_entity_service.active', true))
                ->orWhereDoesntHave('businessEntities');
        });
    }

    /** Entity ids this service is actively enabled for. */
    public function enabledEntityIds(): array
    {
        return $this->businessEntities
            ->filter(fn ($entity) => (bool) $entity->pivot->active)
            ->pluck('id')
            ->all();
    }
}
