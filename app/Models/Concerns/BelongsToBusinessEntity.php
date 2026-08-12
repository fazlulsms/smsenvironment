<?php

namespace App\Models\Concerns;

use App\Models\BusinessEntity;
use App\Models\Scopes\BusinessEntityScope;
use App\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gives a model entity ownership: a global scope isolating it to the active
 * entity, and an auto-stamp of business_entity_id on create.
 */
trait BelongsToBusinessEntity
{
    public static function bootBelongsToBusinessEntity(): void
    {
        static::addGlobalScope(new BusinessEntityScope);

        static::creating(function ($model) {
            if (empty($model->business_entity_id)) {
                $model->business_entity_id = app(CurrentEntity::class)->id();
            }
        });
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    /** Query a specific entity, bypassing the active-entity scope. */
    public function scopeForEntity(Builder $query, int $entityId): Builder
    {
        return $query->withoutGlobalScope(BusinessEntityScope::class)
            ->where($this->getTable().'.business_entity_id', $entityId);
    }

    /** Query across all entities (e.g. an all-entities overview). */
    public function scopeAcrossEntities(Builder $query): Builder
    {
        return $query->withoutGlobalScope(BusinessEntityScope::class);
    }
}
