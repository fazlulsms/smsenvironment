<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SMSEA_ADMIN_EMAIL');
        $password = env('SMSEA_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'SMSEA Admin',
                'password' => Hash::make($password),
            ]
        );
    }
}
