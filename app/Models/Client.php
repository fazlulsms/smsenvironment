<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Clients are a GLOBAL master shared across all business entities. The
 * business_entity_id column is retained (nullable) only to preserve the
 * original creating entity for reference; it is not used for scoping.
 */
class Client extends Model
{
    use RecordsHistory;

    protected $fillable = [
        'business_entity_id',
        'client_code',
        'company_name',
        'parent_company',
        'contact_person',
        'designation',
        'department',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'postal_code',
        'country',
    ];

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }
}
