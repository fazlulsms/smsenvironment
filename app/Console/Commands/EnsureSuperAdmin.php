<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Guarantees a Super Admin exists — the precise production lever to make the
 * Super Admin structure appear on a live site whose primary account ended up as
 * Admin/Staff (e.g. migration ran when the role was already defaulted).
 *
 * Resolution order for the target account:
 *   1. the --email argument, if given
 *   2. the SMSEA_ADMIN_EMAIL environment value
 *   3. the earliest-created user (lowest id)
 *
 * Idempotent and non-destructive: it only ever promotes + activates; it never
 * creates a user and never downgrades anyone.
 */
class EnsureSuperAdmin extends Command
{
    protected $signature = 'smsea:ensure-super-admin {email? : Email of the account to promote}';

    protected $description = 'Promote the primary (or given) account to an active Super Admin';

    public function handle(): int
    {
        $email = $this->argument('email') ?: env('SMSEA_ADMIN_EMAIL');

        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error($email
                ? "No user found with email {$email}."
                : 'No users exist yet — create the primary account first (db:seed).');

            return self::FAILURE;
        }

        $wasSuper = $user->isSuperAdmin() && $user->is_active;
        $user->role = User::ROLE_SUPER_ADMIN;
        $user->is_active = true;
        $user->save();

        $this->info($wasSuper
            ? "{$user->email} is already an active Super Admin — no change needed."
            : "{$user->email} is now an active Super Admin.");

        $count = User::query()->where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count();
        $this->line("Active Super Admins: {$count}");

        return self::SUCCESS;
    }
}
