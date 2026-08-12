<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessEntity extends Model
{
    protected $fillable = [
        'name', 'short_name', 'legal_name', 'entity_code', 'tagline', 'logo_path',
        'address', 'city', 'postal_code', 'country', 'phone', 'secondary_phone',
        'email', 'finance_email', 'website', 'default_currency', 'active', 'is_default',
        'quotation_enabled', 'proforma_invoice_enabled', 'email_enabled', 'qr_verification_enabled',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_default' => 'boolean',
            'quotation_enabled' => 'boolean',
            'proforma_invoice_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'qr_verification_enabled' => 'boolean',
        ];
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }
}
