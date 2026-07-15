<?php

namespace Database\Factories;

use App\Models\GlAccount;
use App\Models\Member;
use App\Models\SavingsProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SavingsAccount>
 */
class SavingsAccountFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'account_no' => sprintf('SAV-%05d', $sequence),
            'member_id' => Member::factory()->active(),
            'savings_product_id' => SavingsProduct::query()->value('id')
                ?? SavingsProduct::create([
                    'code' => 'ORD',
                    'name' => 'Ordinary Savings',
                    'interest_rate' => 5,
                    'min_opening_deposit' => 0,
                    'min_balance' => 0,
                    'withdrawal_fee' => 0,
                    'gl_liability_account_id' => GlAccount::byCode('2010')->id,
                    'gl_interest_expense_account_id' => GlAccount::byCode('5010')->id,
                ])->id,
            'balance' => 0,
            'status' => 'active',
            'opened_at' => today(),
        ];
    }
}
