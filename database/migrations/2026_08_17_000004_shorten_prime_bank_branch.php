<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the trailing country from the SMSEA Prime Bank branch so it fits one line
 * on the invoice ("… Uttara, Dhaka, Bangladesh" → "… Uttara, Dhaka"). Bank master
 * data only — the account number / beneficiary / SWIFT relationship is untouched,
 * and issued document snapshots keep their own copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bank_accounts')
            ->where('account_number', '2170316017001')
            ->where('branch', 'Garib E Newaj Avenue, Uttara, Dhaka, Bangladesh')
            ->update(['branch' => 'Garib E Newaj Avenue, Uttara, Dhaka', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('bank_accounts')
            ->where('account_number', '2170316017001')
            ->where('branch', 'Garib E Newaj Avenue, Uttara, Dhaka')
            ->update(['branch' => 'Garib E Newaj Avenue, Uttara, Dhaka, Bangladesh', 'updated_at' => now()]);
    }
};
