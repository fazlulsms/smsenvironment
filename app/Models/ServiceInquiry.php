<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceInquiry extends Model
{
    public const STATUSES = ['new', 'reviewed', 'converted', 'closed'];

    protected $fillable = [
        'business_entity_id', 'name', 'company', 'email', 'phone', 'service', 'message', 'source', 'status',
    ];

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status ?: 'new');
    }
}
