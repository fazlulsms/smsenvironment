<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEmailDelivery extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'to_email',
        'cc_emails',
        'subject',
        'body_snapshot',
        'sent_by',
        'sent_at',
        'status',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'cc_emails' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
