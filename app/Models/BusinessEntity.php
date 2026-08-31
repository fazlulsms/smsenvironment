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
        'primary_color', 'secondary_color', 'accent_color',
    ];

    /**
     * Web URL for the entity logo shown in the Office UI. Prefers the committed
     * brand asset (public/images/brand/{code}-logo.png) so it renders WITHOUT the
     * public/storage symlink — which is unreliable on shared hosting (Hostinger).
     * Falls back to the storage upload URL, then null (UI shows initials).
     */
    public function logoUrl(): ?string
    {
        if ($this->entity_code) {
            $brand = 'images/brand/'.strtolower($this->entity_code).'-logo.png';
            if (is_file(public_path($brand))) {
                return asset($brand);
            }
        }

        if ($this->logo_path && file_exists(storage_path('app/public/'.$this->logo_path))) {
            return asset('storage/'.$this->logo_path);
        }

        return null;
    }

    /** Theme tokens for the app UI, with a safe SMSEA-green fallback. */
    public function theme(): array
    {
        return [
            'primary' => $this->primary_color ?: '#1f6f4a',
            'secondary' => $this->secondary_color ?: '#2da46f',
            'accent' => $this->accent_color ?: '#46c98a',
        ];
    }

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
