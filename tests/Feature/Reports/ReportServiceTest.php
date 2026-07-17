<?php

namespace Tests\Feature\Reports;

use App\Models\GlAccount;
use App\Models\Member;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SavingsService;
use App\Services\TellerService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;
    private User $teller;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->reports = app(ReportService::class);
        $this->teller = User::factory()->create()->assignRole('teller');
        $this->manager = User::factory()->create()->assignRole('manager');
    }

    /** Post a representative day of business through the real services. */
    private function transactBusiness(): void
    {
        app(TellerService::class)->openSession($this->teller, '1000000.00', $this->manager);

        $product = SavingsProduct::create([
            'code' => 'ORD', 'name' => 'Ordinary Savings', 'interest_rate' => 5,
            'min_opening_deposit' => 0, 'min_balance' => 0, 'withdrawal_fee' => 500,
            'gl_liability_account_id' => GlAccount::byCode('2010')->id,
            'gl_interest_expense_account_id' => GlAccount::byCode('5010')->id,
        ]);

        $savings = app(SavingsService::class);
        $member = Member::factory()->active()->create();
        $account = $savings->openAccount($member, $product, '400000.00', $this->teller);
        $savings->withdraw($account, '100000.00', $this->teller); // 500 fee income

        // Operating expense via manual journal
        app(TransactionService::class)->post('Office rent', [
            ['account' => GlAccount::byCode('5040'), 'debit' => '50000.00'],
            ['account' => GlCodesTestHelper::vault(), 'credit' => '50000.00'],
        ]);
    }

    public function test_balance_sheet_balances_and_reconciles_with_gl(): void
    {
        $this->transactBusiness();

        $report = $this->reports->balanceSheet(today());

        $this->assertTrue($report['balanced'], 'assets != liabilities + equity + surplus');

        // Member savings liability equals the control account balance
        $memberSavings = collect($report['liabilities'])->firstWhere('code', '2010');
        $this->assertSame(GlAccount::byCode('2010')->balance(), $memberSavings['balance']);
    }

    public function test_income_statement_captures_fee_income_and_expenses(): void
    {
        $this->transactBusiness();

        $report = $this->reports->incomeStatement(today()->startOfYear(), today());

        $feeIncome = collect($report['income'])->firstWhere('code', '4020');
        $this->assertSame('500.00', $feeIncome['balance']);

        $rent = collect($report['expenses'])->firstWhere('code', '5040');
        $this->assertSame('50000.00', $rent['balance']);

        // surplus = income - expenses
        $this->assertSame(
            bcsub($report['totals']['income'], $report['totals']['expenses'], 2),
            $report['totals']['surplus'],
        );
    }

    public function test_income_statement_respects_date_range(): void
    {
        $this->transactBusiness();

        // A period in the past has no activity
        $report = $this->reports->incomeStatement(today()->subYear()->startOfYear(), today()->subYear()->endOfYear());

        $this->assertSame('0.00', $report['totals']['income']);
        $this->assertSame('0.00', $report['totals']['expenses']);
    }

    public function test_par_report_empty_portfolio(): void
    {
        $report = $this->reports->parReport();

        $this->assertSame('0.00', $report['totals']['gross_portfolio']);
        $this->assertSame('0.00', $report['totals']['par_ratio']);
        $this->assertCount(5, $report['buckets']);
    }

    public function test_savings_summary_totals_match_accounts(): void
    {
        $this->transactBusiness();

        $report = $this->reports->savingsSummary();

        // 400,000 opening − 100,000 withdrawal − 500 fee = 299,500
        $this->assertSame('299500.00', $report['totals']['balance']);
        $this->assertSame(1, $report['totals']['accounts']);
    }
}

/** Small helper so the test reads clearly. */
class GlCodesTestHelper
{
    public static function vault(): \App\Models\GlAccount
    {
        return \App\Models\GlAccount::where('code', '1010')->firstOrFail();
    }
}
