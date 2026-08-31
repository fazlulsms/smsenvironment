<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed / ensure the primary Super Admin account.
     *
     * Env-driven (no personal credentials in source). Idempotent: re-running never
     * creates duplicates and only ever raises privilege — it will not silently
     * downgrade an existing account. If the primary account already exists it is
     * ensured active and promoted to Super Admin.
     */
    public function run(): void
    {
        $email = env('SMSEA_ADMIN_EMAIL');
        $password = env('SMSEA_ADMIN_PASSWORD');

        if (! $email) {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            // New primary account requires a password to be provided once.
            if (! $password) {
                return;
            }
            $user->name = 'SMSEA Admin';
            $user->password = Hash::make($password);
        }

        $user->role = User::ROLE_SUPER_ADMIN;
        $user->is_active = true;
        $user->save();
    }
}
