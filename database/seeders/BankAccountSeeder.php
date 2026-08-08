<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountNumber = env('SMSEA_BANK_ACCOUNT_NUMBER');

        if (! $accountNumber || ! env('SMSEA_BANK_NAME') || ! env('SMSEA_BANK_BENEFICIARY')) {
            return;
        }

        BankAccount::query()->firstOrCreate(
            ['account_number' => $accountNumber],
            [
                'beneficiary_name' => env('SMSEA_BANK_BENEFICIARY'),
                'bank_name' => env('SMSEA_BANK_NAME'),
                'branch' => env('SMSEA_BANK_BRANCH'),
                'routing_number' => env('SMSEA_BANK_ROUTING_NUMBER'),
                'swift_code' => env('SMSEA_BANK_SWIFT_CODE'),
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }
}
