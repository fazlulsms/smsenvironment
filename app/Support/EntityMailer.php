<?php

namespace App\Support;

use App\Models\EmailAccount;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Resolves the outgoing mailer + from/reply identity for a business entity,
 * reusing the same per-entity EmailAccount conventions as document email. Falls
 * back to the application's default mailer when the entity has no usable account.
 */
class EntityMailer
{
    public static function account(?int $entityId): ?EmailAccount
    {
        if (blank($entityId)) {
            return null;
        }

        return EmailAccount::query()
            ->where('business_entity_id', $entityId)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->first(fn (EmailAccount $account) => $account->mailerConfig() !== null);
    }

    public static function mailer(?EmailAccount $account): Mailer
    {
        $config = $account?->mailerConfig();

        if ($config === null) {
            return Mail::mailer();
        }

        Config::set('mail.mailers.entity_runtime', $config);

        return Mail::mailer('entity_runtime');
    }
}
