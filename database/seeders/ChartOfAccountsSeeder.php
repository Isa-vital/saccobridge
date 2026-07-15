<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use Illuminate\Database\Seeder;

/**
 * UMRA Tier-4 aligned Chart of Accounts, seeded into every tenant DB.
 * Codes are stable — services reference them via GlAccount::byCode().
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            // ASSETS (1xxx)
            ['1000', 'Assets', 'asset', [
                ['1010', 'Cash in Vault', 'asset'],
                ['1020', 'Teller Cash', 'asset'],
                ['1030', 'Bank Accounts', 'asset'],
                ['1040', 'Mobile Money Float', 'asset'],
                ['1100', 'Loan Portfolio', 'asset'],
                ['1110', 'Interest Receivable on Loans', 'asset'],
                ['1190', 'Allowance for Loan Losses (contra)', 'asset'],
                ['1200', 'Fixed Assets', 'asset'],
                ['1300', 'Other Assets', 'asset'],
            ]],
            // LIABILITIES (2xxx)
            ['2000', 'Liabilities', 'liability', [
                ['2010', 'Member Savings Deposits', 'liability'],
                ['2020', 'Member Fixed Deposits', 'liability'],
                ['2030', 'Interest Payable on Savings', 'liability'],
                ['2040', 'Dividends Payable', 'liability'],
                ['2100', 'External Borrowings', 'liability'],
                ['2200', 'Other Liabilities', 'liability'],
            ]],
            // EQUITY (3xxx)
            ['3000', 'Equity', 'equity', [
                ['3010', 'Member Share Capital', 'equity'],
                ['3020', 'Institutional Capital / Reserves', 'equity'],
                ['3030', 'Retained Earnings', 'equity'],
            ]],
            // INCOME (4xxx)
            ['4000', 'Income', 'income', [
                ['4010', 'Interest Income on Loans', 'income'],
                ['4020', 'Loan Fees & Charges', 'income'],
                ['4030', 'Penalty Income', 'income'],
                ['4040', 'Membership Fees', 'income'],
                ['4050', 'Other Operating Income', 'income'],
            ]],
            // EXPENSES (5xxx)
            ['5000', 'Expenses', 'expense', [
                ['5010', 'Interest Expense on Savings', 'expense'],
                ['5020', 'Loan Loss Provision Expense', 'expense'],
                ['5030', 'Staff Costs', 'expense'],
                ['5040', 'Administrative Expenses', 'expense'],
                ['5050', 'Other Operating Expenses', 'expense'],
            ]],
        ];

        foreach ($tree as [$code, $name, $type, $children]) {
            $parent = GlAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'is_system' => true],
            );

            foreach ($children as [$childCode, $childName, $childType]) {
                GlAccount::firstOrCreate(
                    ['code' => $childCode],
                    ['name' => $childName, 'type' => $childType, 'parent_id' => $parent->id, 'is_system' => true],
                );
            }
        }
    }
}
