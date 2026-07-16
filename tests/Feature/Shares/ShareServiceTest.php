<?php

namespace Tests\Feature\Shares;

use App\Models\Dividend;
use App\Models\GlAccount;
use App\Models\Member;
use App\Models\SavingsProduct;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Services\SavingsService;
use App\Services\ShareService;
use App\Services\TellerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShareService $shares;
    private SavingsService $savings;
    private User $teller;
    private User $manager;
    private ShareProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->shares = app(ShareService::class);
        $this->savings = app(SavingsService::class);

        $this->teller = User::factory()->create()->assignRole('teller');
        $this->manager = User::factory()->create()->assignRole('manager');

        $this->product = ShareProduct::create([
            'code' => 'ORD',
            'name' => 'Ordinary Shares',
            'nominal_value' => 10000,
            'min_shares' => 5,
            'max_shares' => 1000,
            'gl_equity_account_id' => GlAccount::byCode('3010')->id,
        ]);

        app(TellerService::class)->openSession($this->teller, '1000000.00', $this->manager);
    }

    private function buy(int $shares, ?Member $member = null): ShareAccount
    {
        $member ??= Member::factory()->active()->create();
        $txn = $this->shares->purchase($member, $this->product, $shares, $this->teller);

        return $txn->account->fresh();
    }

    public function test_purchase_updates_register_and_gl(): void
    {
        $account = $this->buy(10);

        $this->assertSame(10, $account->shares_count);
        $this->assertSame('100000.00', (string) $account->value);
        // Equity control account matches the register
        $this->assertSame('100000.00', GlAccount::byCode('3010')->balance());
    }

    public function test_purchase_beyond_max_rejected(): void
    {
        $member = Member::factory()->active()->create();
        $this->shares->purchase($member, $this->product, 990, $this->teller);

        $this->expectException(DomainException::class);
        $this->shares->purchase($member, $this->product, 20, $this->teller);
    }

    public function test_pending_member_cannot_buy_shares(): void
    {
        $member = Member::factory()->create(); // pending

        $this->expectException(DomainException::class);
        $this->shares->purchase($member, $this->product, 10, $this->teller);
    }

    public function test_transfer_moves_register_without_touching_equity(): void
    {
        $from = $this->buy(20);
        $toMember = Member::factory()->active()->create();

        $equityBefore = GlAccount::byCode('3010')->balance();

        $this->shares->transfer($from, $toMember, 10, $this->manager);

        $this->assertSame(10, $from->fresh()->shares_count);
        $to = ShareAccount::where('member_id', $toMember->id)->first();
        $this->assertSame(10, $to->shares_count);
        // Equity unchanged
        $this->assertSame($equityBefore, GlAccount::byCode('3010')->balance());
    }

    public function test_transfer_cannot_leave_dust_below_minimum(): void
    {
        $from = $this->buy(10); // min_shares = 5

        $this->expectException(DomainException::class);
        $this->shares->transfer($from, Member::factory()->active()->create(), 7, $this->manager); // would leave 3
    }

    public function test_active_member_must_retain_minimum_on_redemption(): void
    {
        $account = $this->buy(10);

        $this->expectException(DomainException::class);
        $this->shares->redeem($account, 8, $this->teller); // would leave 2 < 5
    }

    public function test_redemption_reduces_equity(): void
    {
        $account = $this->buy(20);

        $this->shares->redeem($account, 10, $this->teller);

        $this->assertSame(10, $account->fresh()->shares_count);
        $this->assertSame('100000.00', GlAccount::byCode('3010')->balance()); // 200k - 100k
    }

    public function test_dividend_maker_checker_and_distribution(): void
    {
        // Two shareholders: 30 and 10 shares (value 300k / 100k)
        $memberA = Member::factory()->active()->create();
        $memberB = Member::factory()->active()->create();
        $this->buy(30, $memberA);
        $this->buy(10, $memberB);

        // Member A has a savings account; B does not
        $savingsProduct = SavingsProduct::create([
            'code' => 'ORD', 'name' => 'Ordinary Savings', 'interest_rate' => 5,
            'min_opening_deposit' => 0, 'min_balance' => 0, 'withdrawal_fee' => 0,
            'gl_liability_account_id' => GlAccount::byCode('2010')->id,
            'gl_interest_expense_account_id' => GlAccount::byCode('5010')->id,
        ]);
        $savingsA = $this->savings->openAccount($memberA, $savingsProduct, '0.00', $this->teller);

        // Declare 5% on 400k = 20k total
        $dividend = $this->shares->declareDividend('2026', $this->product, '5', $this->manager);
        $this->assertSame('20000.00', (string) $dividend->total_declared);

        // Maker cannot approve own declaration
        try {
            $this->shares->approveDividend($dividend, $this->manager);
            $this->fail('Maker-checker violation allowed');
        } catch (DomainException) {
        }

        $admin = User::factory()->create()->assignRole('sacco-admin');
        $this->shares->approveDividend($dividend, $admin);

        $paid = $this->shares->distributeDividend($dividend->fresh(), $admin);

        $this->assertSame(2, $paid);
        $dividend->refresh();
        $this->assertSame('distributed', $dividend->status);

        // A: 15k credited to savings; B: 5k stays payable
        $this->assertSame('15000.00', (string) $savingsA->fresh()->balance);
        $payoutB = $dividend->payouts()->where('method', 'payable')->first();
        $this->assertSame('5000.00', (string) $payoutB->amount);
        $this->assertSame('pending', $payoutB->status);

        // GL: payable balance = declared - credited = 5k
        $this->assertSame('5000.00', GlAccount::byCode('2040')->balance());

        // Re-distribution is idempotent
        try {
            $this->shares->distributeDividend($dividend, $admin);
            $this->fail('Re-distribution should be blocked');
        } catch (DomainException) {
        }
    }

    public function test_books_balanced_after_share_lifecycle(): void
    {
        $account = $this->buy(50);
        $this->shares->transfer($account, Member::factory()->active()->create(), 20, $this->manager);
        $this->shares->redeem($account->fresh(), 10, $this->teller);

        $totals = \App\Models\JournalLine::selectRaw(
            'COALESCE(SUM(debit),0) as debits, COALESCE(SUM(credit),0) as credits'
        )->first();

        $this->assertEquals($totals->debits, $totals->credits);
    }
}
