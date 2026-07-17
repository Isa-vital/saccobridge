<?php

namespace Tests\Feature\Loans;

use App\Models\GlAccount;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\LoanService;
use App\Services\SavingsService;
use App\Services\TellerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loans;
    private SavingsService $savings;
    private User $officer;
    private User $manager;
    private User $teller;
    private LoanProduct $product;
    private SavingsProduct $savingsProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->loans = app(LoanService::class);
        $this->savings = app(SavingsService::class);

        $this->officer = User::factory()->create()->assignRole('loan-officer');
        $this->manager = User::factory()->create()->assignRole('manager');
        $this->teller = User::factory()->create()->assignRole('teller');

        $this->product = LoanProduct::create([
            'code' => 'BIZ',
            'name' => 'Business Loan',
            'interest_rate' => 24,
            'interest_method' => 'reducing_balance',
            'min_term_months' => 1,
            'max_term_months' => 36,
            'min_amount' => 100000,
            'max_amount' => 50000000,
            'application_fee' => 10000,
            'processing_fee_percent' => 1, // 1%
            'penalty_rate' => 2, // 2% per month on overdue
            'required_guarantors' => 0,
            'savings_multiple' => 3,
            'gl_portfolio_account_id' => GlAccount::byCode('1100')->id,
            'gl_interest_income_account_id' => GlAccount::byCode('4010')->id,
            'gl_fee_income_account_id' => GlAccount::byCode('4020')->id,
            'gl_penalty_income_account_id' => GlAccount::byCode('4030')->id,
        ]);

        $this->savingsProduct = SavingsProduct::create([
            'code' => 'ORD', 'name' => 'Ordinary Savings', 'interest_rate' => 5,
            'min_opening_deposit' => 0, 'min_balance' => 0, 'withdrawal_fee' => 0,
            'gl_liability_account_id' => GlAccount::byCode('2010')->id,
            'gl_interest_expense_account_id' => GlAccount::byCode('5010')->id,
        ]);

        app(TellerService::class)->openSession($this->teller, '5000000.00', $this->manager);
    }

    /** Member with savings so the 3× rule allows a 1,500,000 loan. */
    private function memberWithSavings(string $savings = '500000.00'): Member
    {
        $member = Member::factory()->active()->create();
        $this->savings->openAccount($member, $this->savingsProduct, $savings, $this->teller);

        return $member;
    }

    private function activeLoan(string $amount = '1000000.00', int $term = 12): Loan
    {
        $member = $this->memberWithSavings();
        $loan = $this->loans->apply($member, $this->product, $amount, $term, 'Stock', [], [], $this->officer);
        $this->loans->approve($loan, $this->manager);

        return $this->loans->disburse($loan->fresh(), $this->teller);
    }

    public function test_savings_multiple_rule_enforced(): void
    {
        $member = $this->memberWithSavings('100000.00'); // max loan 300,000

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds .*savings/');
        $this->loans->apply($member, $this->product, '400000.00', 12, null, [], [], $this->officer);
    }

    public function test_officer_cannot_approve_own_application(): void
    {
        $member = $this->memberWithSavings();
        $loan = $this->loans->apply($member, $this->product, '900000.00', 12, null, [], [], $this->officer);

        // Give officer approval permission scenario: even then, maker-checker blocks
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/maker-checker/');
        $this->loans->approve($loan, $this->officer);
    }

    public function test_guarantor_savings_are_blocked_and_released(): void
    {
        $member = $this->memberWithSavings();
        $guarantorMember = Member::factory()->active()->create();
        $guarantorAccount = $this->savings->openAccount($guarantorMember, $this->savingsProduct, '300000.00', $this->teller);

        $loan = $this->loans->apply($member, $this->product, '600000.00', 6, null, [
            ['member_id' => $guarantorMember->id, 'savings_account_id' => $guarantorAccount->id, 'guaranteed_amount' => '200000.00'],
        ], [], $this->officer);

        $this->assertSame('200000.00', (string) $guarantorAccount->fresh()->blocked_amount);

        // Guarantor cannot withdraw blocked funds
        try {
            $this->savings->withdraw($guarantorAccount->fresh(), '250000.00', $this->teller);
            $this->fail('Blocked savings were withdrawable');
        } catch (DomainException) {
        }

        // Rejection releases the block
        $this->loans->reject($loan, $this->manager, 'insufficient capacity');
        $this->assertSame('0.00', (string) $guarantorAccount->fresh()->blocked_amount);
    }

    public function test_disbursement_generates_schedule_and_posts_gl(): void
    {
        $loan = $this->activeLoan('1000000.00', 12);

        $this->assertSame('active', $loan->status);
        $this->assertCount(12, $loan->schedules);
        $this->assertSame('1000000.00', (string) $loan->principal_outstanding);

        // Portfolio grew by principal
        $this->assertSame('1000000.00', GlAccount::byCode('1100')->balance());
        // Fees: 10,000 application + 1% × 1,000,000 = 20,000 total fee income
        $this->assertSame('20000.00', GlAccount::byCode('4020')->balance());

        // Books balanced
        $totals = \App\Models\JournalLine::selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
        $this->assertEquals($totals->d, $totals->c);
    }

    public function test_repayment_waterfall_allocates_interest_then_principal(): void
    {
        $loan = $this->activeLoan('1000000.00', 12);
        $first = $loan->schedules()->first();

        // Pay exactly the first installment (interest 20,000 + principal 74,559.59)
        $installmentTotal = bcadd($first->due('principal'), $first->due('interest'), 2);
        $repayment = $this->loans->repay($loan, $installmentTotal, $this->teller);

        $this->assertSame('20000.00', (string) $repayment->interest_portion);
        $this->assertSame('74559.59', (string) $repayment->principal_portion);
        $this->assertTrue($first->fresh()->is_settled);

        // Interest income earned
        $this->assertSame('20000.00', GlAccount::byCode('4010')->balance());
    }

    public function test_full_early_settlement_closes_loan_and_releases_guarantees(): void
    {
        $member = $this->memberWithSavings();
        $guarantorMember = Member::factory()->active()->create();
        $guarantorAccount = $this->savings->openAccount($guarantorMember, $this->savingsProduct, '500000.00', $this->teller);

        $loan = $this->loans->apply($member, $this->product, '600000.00', 6, null, [
            ['member_id' => $guarantorMember->id, 'savings_account_id' => $guarantorAccount->id, 'guaranteed_amount' => '200000.00'],
        ], [], $this->officer);
        $this->loans->approve($loan, $this->manager);
        $loan = $this->loans->disburse($loan->fresh(), $this->teller);

        // Pay the entire outstanding (principal + all scheduled interest)
        $this->loans->repay($loan, $loan->totalOutstanding(), $this->teller);

        $loan->refresh();
        $this->assertSame('closed', $loan->status);
        $this->assertSame('0.00', $loan->totalOutstanding());
        $this->assertSame('0.00', (string) $guarantorAccount->fresh()->blocked_amount);
        // Portfolio back to zero for this loan
        $this->assertSame('0.00', GlAccount::byCode('1100')->balance());
    }

    public function test_penalties_applied_on_overdue_installments(): void
    {
        $loan = $this->activeLoan('1000000.00', 12);

        // Time-travel: first installment 40 days overdue; the second installment
        // (equal annuity 94,559.59) is also a few days overdue by then.
        $asOf = today()->addMonthNoOverflow()->addDays(40);
        $penalty = $this->loans->applyPenalty($loan, $asOf);

        // 2% × (94,559.59 × 2 overdue installments) = 3,782.38
        $this->assertSame('3782.38', $penalty);
        $this->assertSame('3782.38', (string) $loan->fresh()->penalties_outstanding);

        // Idempotent within the same month
        $this->assertSame('0.00', $this->loans->applyPenalty($loan->fresh(), $asOf));
    }

    public function test_umra_classification_and_provisioning(): void
    {
        $loan = $this->activeLoan('1000000.00', 12);

        // 40 days overdue → substandard (31–60), provision 25%
        $asOf = today()->addMonthNoOverflow()->addDays(40);
        $this->loans->classify($loan, $asOf);

        $loan->refresh();
        $this->assertSame('substandard', $loan->classification);
        $this->assertSame(40, $loan->days_in_arrears);
        $this->assertSame('250000.00', (string) $loan->provision_amount);

        // GL: provision expense and allowance
        $this->assertSame('250000.00', GlAccount::byCode('5020')->balance());
        $this->assertSame('-250000.00', GlAccount::byCode('1190')->balance()); // contra-asset

        // 95 days → loss, provision 100%; only the DELTA is posted
        $this->loans->classify($loan->fresh(), today()->addMonthNoOverflow()->addDays(95));
        $loan->refresh();
        $this->assertSame('loss', $loan->classification);
        $this->assertSame('1000000.00', (string) $loan->provision_amount);
        $this->assertSame('1000000.00', GlAccount::byCode('5020')->balance());
    }

    public function test_cost_of_credit_disclosure(): void
    {
        $cost = $this->loans->costOfCredit($this->product, '1000000.00', 12);

        $this->assertSame('134715.11', $cost['total_interest']); // reducing balance
        $this->assertSame('20000.00', $cost['total_fees']);
        $this->assertSame('154715.11', $cost['total_cost']);
        $this->assertSame('980000.00', $cost['net_disbursed']);
    }

    public function test_payoff_quote_waives_future_interest(): void
    {
        $loan = $this->activeLoan('1000000.00', 12);

        $quote = $this->loans->payoffQuote($loan); // nothing due yet

        $this->assertSame('1000000.00', $quote['principal']);
        $this->assertSame('0.00', $quote['interest_due']);
        $this->assertSame('134715.11', $quote['waived_future_interest']);
        $this->assertSame('1000000.00', $quote['total']);
    }

    public function test_second_active_loan_is_blocked(): void
    {
        $loan = $this->activeLoan();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/active loan/');
        $this->loans->apply($loan->member, $this->product, '200000.00', 6, null, [], [], $this->officer);
    }
}
