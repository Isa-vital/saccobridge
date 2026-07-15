<?php

namespace Tests\Feature\Gl;

use App\Models\FinancialPeriod;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->service = app(TransactionService::class);
    }

    public function test_posts_a_balanced_entry(): void
    {
        $entry = $this->service->post('Test deposit', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 50000],
            ['account' => GlAccount::byCode('2010'), 'credit' => 50000],
        ]);

        $this->assertSame('JE-000001', $entry->reference);
        $this->assertSame('posted', $entry->status);
        $this->assertCount(2, $entry->lines);
        $this->assertEquals(50000, $entry->lines()->sum('debit'));
        $this->assertEquals(50000, $entry->lines()->sum('credit'));
    }

    public function test_rejects_unbalanced_entry(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/not balanced/');

        $this->service->post('Broken', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 100],
            ['account' => GlAccount::byCode('2010'), 'credit' => 99.99],
        ]);
    }

    public function test_rejects_line_with_both_debit_and_credit(): void
    {
        $this->expectException(DomainException::class);

        $this->service->post('Broken', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 100, 'credit' => 100],
            ['account' => GlAccount::byCode('2010'), 'credit' => 0],
        ]);
    }

    public function test_rejects_single_line_entry(): void
    {
        $this->expectException(DomainException::class);

        $this->service->post('Broken', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 100],
        ]);
    }

    public function test_rejects_posting_to_closed_period(): void
    {
        FinancialPeriod::create([
            'name' => today()->format('Y-m'),
            'starts_on' => today()->startOfMonth(),
            'ends_on' => today()->endOfMonth(),
            'status' => 'closed',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/closed/');

        $this->service->post('Late posting', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 100],
            ['account' => GlAccount::byCode('2010'), 'credit' => 100],
        ]);
    }

    public function test_reversal_mirrors_lines_and_marks_original(): void
    {
        $entry = $this->service->post('Original', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 1000],
            ['account' => GlAccount::byCode('4040'), 'credit' => 1000],
        ]);

        $reversal = $this->service->reverse($entry, 'posted in error');

        $this->assertSame('reversed', $entry->fresh()->status);
        $this->assertSame($entry->id, $reversal->reversal_of_id);

        $cash = $reversal->lines()->where('gl_account_id', GlAccount::byCode('1020')->id)->first();
        $this->assertSame('0.00', $cash->debit);
        $this->assertSame('1000.00', $cash->credit);

        // Account nets to zero after reversal
        $this->assertSame('0.00', GlAccount::byCode('1020')->balance());
    }

    public function test_cannot_reverse_twice(): void
    {
        $entry = $this->service->post('Original', [
            ['account' => GlAccount::byCode('1020'), 'debit' => 500],
            ['account' => GlAccount::byCode('2010'), 'credit' => 500],
        ]);

        $this->service->reverse($entry, 'first');

        $this->expectException(DomainException::class);
        $this->service->reverse($entry->fresh(), 'second');
    }

    public function test_trial_balance_nets_to_zero_after_many_postings(): void
    {
        $pairs = [
            ['1020', '2010', 250000],
            ['1020', '4040', 20000],
            ['5010', '2030', 12500.50],
            ['1100', '1020', 100000],
            ['1010', '1020', 75000.25],
        ];

        foreach ($pairs as [$debitCode, $creditCode, $amount]) {
            $this->service->post("Posting {$debitCode}/{$creditCode}", [
                ['account' => GlAccount::byCode($debitCode), 'debit' => $amount],
                ['account' => GlAccount::byCode($creditCode), 'credit' => $amount],
            ]);
        }

        $totals = \App\Models\JournalLine::selectRaw(
            'COALESCE(SUM(debit),0) as debits, COALESCE(SUM(credit),0) as credits'
        )->first();

        $this->assertSame($totals->debits, $totals->credits);
    }

    public function test_membership_fee_posts_to_gl_on_member_approval(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('membership_fee', '10000');

        $maker = User::factory()->create()->assignRole('loan-officer');
        $checker = User::factory()->create()->assignRole('manager');
        $member = Member::factory()->create(['created_by' => $maker->id]);

        app(MemberService::class)->approve($member, $checker);

        $entry = JournalEntry::where('source_type', $member->getMorphClass())
            ->where('source_id', $member->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('10000.00', GlAccount::byCode('1020')->balance()); // Dr Teller Cash
        $this->assertSame('10000.00', GlAccount::byCode('4040')->balance()); // Cr Membership Fees
    }
}
