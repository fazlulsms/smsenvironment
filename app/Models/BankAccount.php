<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusinessEntity;
use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use BelongsToBusinessEntity;
    use RecordsHistory;

    protected $fillable = [
        'business_entity_id',
        'beneficiary_name',
        'bank_name',
        'branch',
        'account_number',
        'routing_number',
        'swift_code',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
