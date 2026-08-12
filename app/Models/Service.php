<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use BelongsToBusinessEntity;

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
}
