<?php

namespace App\Models\Scopes;

use App\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query to the active business entity. Enforced at the query
 * layer so index pages, relations and route-model binding all isolate by entity
 * without each call site remembering to filter.
 */
class BusinessEntityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $entityId = app(CurrentEntity::class)->id();

        if ($entityId !== null) {
            $builder->where($model->getTable().'.business_entity_id', $entityId);
        }
    }
}
