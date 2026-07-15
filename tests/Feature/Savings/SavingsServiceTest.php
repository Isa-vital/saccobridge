<?php

namespace Tests\Feature\Savings;

use App\Models\GlAccount;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\SavingsService;
use App\Services\TellerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SavingsService $savings;
    private TellerService $tellers;
    private User $teller;
    private User $manager;
    private SavingsProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->savings = app(SavingsService::class);
        $this->tellers = app(TellerService::class);

        $this->teller = User::factory()->create()->assignRole('teller');
        $this->manager = User::factory()->create()->assignRole('manager');

        $this->product = SavingsProduct::create([
            'code' => 'ORD',
            'name' => 'Ordinary Savings',
            'interest_rate' => 12, // 1% per month — easy math
            'min_opening_deposit' => 10000,
            'min_balance' => 5000,
            'withdrawal_fee' => 500,
            'gl_liability_account_id' => GlAccount::byCode('2010')->id,
            'gl_interest_expense_account_id' => GlAccount::byCode('5010')->id,
        ]);

        // Give the teller a session with 200,000 float
        $this->tellers->openSession($this->teller, '200000.00', $this->manager);
    }

    private function openAccount(string $deposit = '50000.00'): SavingsAccount
    {
        $member = Member::factory()->active()->create();

        return $this->savings->openAccount($member, $this->product, $deposit, $this->teller);
    }

    public function test_open_account_with_opening_deposit_posts_to_gl(): void
    {
        $account = $this->openAccount('50000.00');

        $this->assertSame('SAV-00001', $account->account_no);
        $this->assertSame('50000.00', (string) $account->balance);

        // GL: member savings liability grew by the deposit
        $this->assertSame('50000.00', GlAccount::byCode('2010')->balance());
    }

    public function test_cannot_open_account_for_pending_member(): void
    {
        $member = Member::factory()->create(); // pending

        $this->expectException(DomainException::class);
        $this->savings->openAccount($member, $this->product, '20000.00', $this->teller);
    }

    public function test_opening_deposit_below_product_minimum_rejected(): void
    {
        $member = Member::factory()->active()->create();

        $this->expectException(DomainException::class);
        $this->savings->openAccount($member, $this->product, '5000.00', $this->teller);
    }

    public function test_deposit_requires_open_teller_session(): void
    {
        $account = $this->openAccount();
        $rogue = User::factory()->create()->assignRole('teller');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/teller session/');
        $this->savings->deposit($account, '10000.00', $rogue);
    }

    public function test_withdrawal_respects_min_balance_and_fee(): void
    {
        $account = $this->openAccount('50000.00');

        // available = 50000 - 0 blocked - 5000 min balance = 45000
        // withdrawing 44600 + 500 fee = 45100 > 45000 → reject
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient available balance/');
        $this->savings->withdraw($account, '44600.00', $this->teller);
    }

    public function test_withdrawal_with_fee_posts_balanced_gl(): void
    {
        $account = $this->openAccount('50000.00');

        $txn = $this->savings->withdraw($account, '20000.00', $this->teller);

        $this->assertSame('completed', $txn->status);
        // balance: 50000 - 20000 - 500 fee = 29500
        $this->assertSame('29500.00', (string) $account->fresh()->balance);
        // fee income earned
        $this->assertSame('500.00', GlAccount::byCode('4020')->balance());
        // liability: 50000 - 20500 = 29500
        $this->assertSame('29500.00', GlAccount::byCode('2010')->balance());
    }

    public function test_large_withdrawal_requires_manager_approval(): void
    {
        Setting::set('withdrawal_approval_threshold', '100000');
        $account = $this->openAccount('500000.00');

        $txn = $this->savings->withdraw($account, '200000.00', $this->teller);

        $this->assertSame('pending_approval', $txn->status);
        // Cash has NOT moved yet
        $this->assertSame('500000.00', (string) $account->fresh()->balance);

        // Teller cannot approve their own withdrawal
        try {
            $this->savings->approveWithdrawal($txn, $this->teller);
            $this->fail('Maker-checker violation allowed');
        } catch (DomainException) {
            // expected
        }

        // Manager approves → executes
        $this->savings->approveWithdrawal($txn->fresh(), $this->manager);
        $this->assertSame('completed', $txn->fresh()->status);
        $this->assertSame('299500.00', (string) $account->fresh()->balance); // -200000 -500 fee
    }

    public function test_transfer_preserves_total_savings(): void
    {
        $from = $this->openAccount('100000.00');
        $to = $this->openAccount('20000.00');

        $this->savings->transfer($from, $to, '30000.00', $this->teller);

        $this->assertSame('70000.00', (string) $from->fresh()->balance);
        $this->assertSame('50000.00', (string) $to->fresh()->balance);
        // Total liability unchanged
        $this->assertSame('120000.00', GlAccount::byCode('2010')->balance());
    }

    public function test_interest_posting_is_correct_and_idempotent(): void
    {
        $account = $this->openAccount('100000.00');

        $first = $this->savings->postInterest($account);
        // 12% annual → 1% monthly on 100,000 = 1,000
        $this->assertSame('1000.00', (string) $first->amount);
        $this->assertSame('101000.00', (string) $account->fresh()->balance);

        // Second run in the same month posts nothing
        $second = $this->savings->postInterest($account->fresh());
        $this->assertNull($second);

        // GL: interest expense recognized
        $this->assertSame('1000.00', GlAccount::byCode('5010')->balance());
    }

    public function test_teller_session_close_computes_variance(): void
    {
        $account = $this->openAccount('50000.00'); // +50000 into drawer
        $this->savings->withdraw($account, '10000.00', $this->teller); // -10000 out

        $session = \App\Models\TellerSession::openFor($this->teller);
        // expected = 200000 float + 50000 - 10000 = 240000; teller declares 239000 → variance -1000
        $closed = $this->tellers->closeSession($session, '239000.00', $this->teller);

        $this->assertSame('closed', $closed->status);
        $this->assertSame('240000.00', (string) $closed->closing_system);
        $this->assertSame('-1000.00', (string) $closed->variance);

        // Teller cannot reconcile own session
        $this->expectException(DomainException::class);
        $this->tellers->reconcile($closed, $this->teller);
    }

    public function test_books_remain_balanced_after_full_day(): void
    {
        $account = $this->openAccount('80000.00');
        $this->savings->deposit($account, '20000.00', $this->teller);
        $this->savings->withdraw($account, '30000.00', $this->teller);
        $this->savings->postInterest($account);

        $totals = \App\Models\JournalLine::selectRaw(
            'COALESCE(SUM(debit),0) as debits, COALESCE(SUM(credit),0) as credits'
        )->first();

        $this->assertEquals($totals->debits, $totals->credits);
    }
}
