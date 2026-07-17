<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $portfolio = GlAccount::byCode('1100')->id;
        $interest = GlAccount::byCode('4010')->id;
        $fees = GlAccount::byCode('4020')->id;
        $penalties = GlAccount::byCode('4030')->id;

        LoanProduct::firstOrCreate(['code' => 'BIZ'], [
            'name' => 'Business Loan',
            'interest_rate' => 24,
            'interest_method' => 'reducing_balance',
            'min_term_months' => 3,
            'max_term_months' => 24,
            'min_amount' => 100000,
            'max_amount' => 50000000,
            'grace_period_days' => 0,
            'application_fee' => 10000,
            'processing_fee_percent' => 1,
            'penalty_rate' => 2,
            'required_guarantors' => 1,
            'savings_multiple' => 3,
            'gl_portfolio_account_id' => $portfolio,
            'gl_interest_income_account_id' => $interest,
            'gl_fee_income_account_id' => $fees,
            'gl_penalty_income_account_id' => $penalties,
        ]);

        LoanProduct::firstOrCreate(['code' => 'EMG'], [
            'name' => 'Emergency Loan',
            'interest_rate' => 36,
            'interest_method' => 'flat',
            'min_term_months' => 1,
            'max_term_months' => 6,
            'min_amount' => 50000,
            'max_amount' => 2000000,
            'grace_period_days' => 0,
            'application_fee' => 5000,
            'processing_fee_percent' => 0,
            'penalty_rate' => 3,
            'required_guarantors' => 0,
            'savings_multiple' => 2,
            'gl_portfolio_account_id' => $portfolio,
            'gl_interest_income_account_id' => $interest,
            'gl_fee_income_account_id' => $fees,
            'gl_penalty_income_account_id' => $penalties,
        ]);
    }
}
