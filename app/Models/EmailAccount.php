<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAccount extends Model
{
    protected $fillable = [
        'business_entity_id', 'label', 'mailer_type', 'host', 'port', 'username',
        'password', 'encryption', 'from_name', 'from_address', 'reply_to', 'active', 'is_default',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    /**
     * Build the Laravel mailer config array for this account. Returns null when
     * the account is not sufficiently configured to send mail.
     */
    public function mailerConfig(): ?array
    {
        if (blank($this->host) || blank($this->from_address)) {
            return null;
        }

        return [
            'transport' => $this->mailer_type ?: 'smtp',
            'host' => $this->host,
            'port' => $this->port ?: 587,
            'encryption' => $this->encryption ?: null,
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'timeout' => null,
        ];
    }
}
