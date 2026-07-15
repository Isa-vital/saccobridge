<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use App\Models\SavingsProduct;
use Illuminate\Database\Seeder;

/**
 * Default savings products for every new tenant (can be edited/deactivated).
 */
class SavingsProductSeeder extends Seeder
{
    public function run(): void
    {
        $liability = GlAccount::byCode('2010')->id;
        $fixedLiability = GlAccount::byCode('2020')->id;
        $expense = GlAccount::byCode('5010')->id;

        SavingsProduct::firstOrCreate(['code' => 'ORD'], [
            'name' => 'Ordinary Savings',
            'interest_rate' => 5,
            'interest_basis' => 'daily_balance',
            'interest_posting' => 'monthly',
            'min_opening_deposit' => 10000,
            'min_balance' => 5000,
            'withdrawal_fee' => 500,
            'max_withdrawals_per_month' => null,
            'gl_liability_account_id' => $liability,
            'gl_interest_expense_account_id' => $expense,
        ]);

        SavingsProduct::firstOrCreate(['code' => 'FXD'], [
            'name' => 'Fixed Deposit',
            'interest_rate' => 10,
            'interest_basis' => 'monthly_min_balance',
            'interest_posting' => 'annually',
            'min_opening_deposit' => 500000,
            'min_balance' => 500000,
            'withdrawal_fee' => 0,
            'max_withdrawals_per_month' => 1,
            'gl_liability_account_id' => $fixedLiability,
            'gl_interest_expense_account_id' => $expense,
        ]);
    }
}
