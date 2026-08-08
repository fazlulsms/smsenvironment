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
        $hasDefault = BankAccount::query()->where('is_default', true)->exists();

        foreach ($this->accounts() as $index => $account) {
            $values = [
                ...$account,
                'is_active' => true,
            ];

            if (! $hasDefault && $index === 0) {
                $values['is_default'] = true;
                $hasDefault = true;
            }

            BankAccount::query()->updateOrCreate(
                ['account_number' => $account['account_number']],
                $values
            );
        }
    }

    private function accounts(): array
    {
        $accounts = [[
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'branch' => 'Garib E Newaj Avenue, Uttara, Dhaka, Bangladesh',
            'account_number' => '2170316017001',
            'routing_number' => null,
            'swift_code' => 'PRBLBDDH',
        ], [
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Mutual Trust Bank',
            'branch' => 'Shah Mokhdum Avenue Branch',
            'account_number' => '1301000014453',
            'routing_number' => null,
            'swift_code' => 'MTBLBDDH',
        ]];

        $envAccountNumber = env('SMSEA_BANK_ACCOUNT_NUMBER');

        if ($envAccountNumber && env('SMSEA_BANK_NAME') && env('SMSEA_BANK_BENEFICIARY')) {
            $accounts[] = [
                'beneficiary_name' => env('SMSEA_BANK_BENEFICIARY'),
                'bank_name' => $this->normalizeBankName((string) env('SMSEA_BANK_NAME')),
                'branch' => env('SMSEA_BANK_BRANCH'),
                'routing_number' => env('SMSEA_BANK_ROUTING_NUMBER'),
                'swift_code' => env('SMSEA_BANK_SWIFT_CODE'),
                'account_number' => $envAccountNumber,
            ];
        }

        return collect($accounts)->unique('account_number')->values()->all();
    }

    private function normalizeBankName(string $name): string
    {
        return preg_replace('/\.+$/', '.', trim($name)) ?: trim($name);
    }
}
