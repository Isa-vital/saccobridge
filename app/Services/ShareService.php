<?php

namespace App\Services;

use App\Models\Dividend;
use App\Models\GlAccount;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\ShareTransaction;
use App\Models\TellerSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Shares & dividends (FR-SHR-01..05). Register + equity GL always in sync:
 * Σ share_accounts.value == GL 3010 (per product equity account).
 */
class ShareService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly SavingsService $savings,
    ) {
    }

    /** Share purchase by an active member via teller (FR-SHR-02). */
    public function purchase(Member $member, ShareProduct $product, int $shares, User $teller): ShareTransaction
    {
        if ($member->status !== 'active') {
            throw new DomainException('Only active members can purchase shares.');
        }

        if ($shares < 1) {
            throw new DomainException('Share purchase must be at least 1 share.');
        }

        if (! $product->is_active) {
            throw new DomainException('This share product is not active.');
        }

        $this->requireOpenSession($teller);

        return DB::transaction(function () use ($member, $product, $shares, $teller) {
            $account = ShareAccount::firstOrCreate(
                ['member_id' => $member->id, 'share_product_id' => $product->id],
                ['shares_count' => 0, 'value' => 0],
            );
            $account = ShareAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $newCount = $account->shares_count + $shares;

            if ($product->max_shares !== null && $newCount > $product->max_shares) {
                throw new DomainException("Purchase exceeds the maximum of {$product->max_shares} shares per member.");
            }

            $amount = bcmul((string) $shares, (string) $product->nominal_value, 2);
            $account->update([
                'shares_count' => $newCount,
                'value' => bcadd($account->value, $amount, 2),
            ]);

            $transaction = $this->record($account, 'purchase', $shares, $amount, $newCount, $teller);

            $entry = $this->transactions->post(
                description: "Share purchase {$transaction->reference} — {$member->member_no} ({$shares} × {$product->nominal_value})",
                lines: [
                    ['account' => GlCodes::tellerCash(), 'debit' => $amount],
                    ['account' => $product->gl_equity_account_id, 'credit' => $amount],
                ],
                source: $transaction,
                postedBy: $teller,
            );

            $transaction->update(['journal_entry_id' => $entry->id]);

            return $transaction;
        });
    }

    /** Member-to-member share transfer — register move, equity unchanged (FR-SHR-02). */
    public function transfer(ShareAccount $from, Member $toMember, int $shares, User $user): array
    {
        if ($shares < 1) {
            throw new DomainException('Transfer must be at least 1 share.');
        }

        if ($toMember->status !== 'active') {
            throw new DomainException('Shares can only be transferred to active members.');
        }

        return DB::transaction(function () use ($from, $toMember, $shares, $user) {
            $from = ShareAccount::whereKey($from->id)->lockForUpdate()->firstOrFail();
            $product = $from->product;

            if ($from->member_id === $toMember->id) {
                throw new DomainException('Cannot transfer shares to the same member.');
            }

            if ($shares > $from->shares_count) {
                throw new DomainException('Transfer exceeds shares held.');
            }

            $remaining = $from->shares_count - $shares;
            if ($remaining !== 0 && $remaining < $product->min_shares) {
                throw new DomainException("Transferor must retain at least {$product->min_shares} shares or transfer all.");
            }

            $to = ShareAccount::firstOrCreate(
                ['member_id' => $toMember->id, 'share_product_id' => $product->id],
                ['shares_count' => 0, 'value' => 0],
            );
            $to = ShareAccount::whereKey($to->id)->lockForUpdate()->firstOrFail();

            $newToCount = $to->shares_count + $shares;
            if ($product->max_shares !== null && $newToCount > $product->max_shares) {
                throw new DomainException("Transfer would exceed the maximum of {$product->max_shares} shares for the recipient.");
            }

            $amount = bcmul((string) $shares, (string) $product->nominal_value, 2);

            $from->update([
                'shares_count' => $remaining,
                'value' => bcsub($from->value, $amount, 2),
            ]);
            $to->update([
                'shares_count' => $newToCount,
                'value' => bcadd($to->value, $amount, 2),
            ]);

            $out = $this->record($from, 'transfer_out', $shares, $amount, $remaining, $user, $to->id);
            $in = $this->record($to, 'transfer_in', $shares, $amount, $newToCount, $user, $from->id);

            return [$out, $in];
        });
    }

    /** Share redemption (buy-back), typically on member exit (FR-SHR-02). */
    public function redeem(ShareAccount $account, int $shares, User $teller): ShareTransaction
    {
        if ($shares < 1) {
            throw new DomainException('Redemption must be at least 1 share.');
        }

        $this->requireOpenSession($teller);

        return DB::transaction(function () use ($account, $shares, $teller) {
            $account = ShareAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $product = $account->product;

            if ($shares > $account->shares_count) {
                throw new DomainException('Redemption exceeds shares held.');
            }

            $remaining = $account->shares_count - $shares;

            // Active members must keep the minimum; exiting members may redeem all
            if ($account->member->status === 'active' && $remaining < $product->min_shares) {
                throw new DomainException("Active members must retain at least {$product->min_shares} shares.");
            }

            $amount = bcmul((string) $shares, (string) $product->nominal_value, 2);
            $account->update([
                'shares_count' => $remaining,
                'value' => bcsub($account->value, $amount, 2),
                'status' => $remaining === 0 ? 'closed' : 'active',
            ]);

            $transaction = $this->record($account, 'redemption', $shares, $amount, $remaining, $teller);

            $entry = $this->transactions->post(
                description: "Share redemption {$transaction->reference} — {$account->member->member_no}",
                lines: [
                    ['account' => $product->gl_equity_account_id, 'debit' => $amount],
                    ['account' => GlCodes::tellerCash(), 'credit' => $amount],
                ],
                source: $transaction,
                postedBy: $teller,
            );

            $transaction->update(['journal_entry_id' => $entry->id]);

            return $transaction;
        });
    }

    /** Declare a dividend for a year (FR-SHR-04). Maker step. */
    public function declareDividend(string $year, ShareProduct $product, string $rate, User $declaredBy): Dividend
    {
        if (bccomp($rate, '0', 6) !== 1) {
            throw new DomainException('Dividend rate must be greater than zero.');
        }

        $totalShareValue = (string) ShareAccount::where('share_product_id', $product->id)
            ->where('status', 'active')
            ->sum('value');

        $total = bcmul($totalShareValue, bcdiv($rate, '100', 8), 2);

        if (bccomp($total, '0.00', 2) !== 1) {
            throw new DomainException('There is no share capital to pay a dividend on.');
        }

        return Dividend::create([
            'financial_year' => $year,
            'share_product_id' => $product->id,
            'rate' => $rate,
            'total_declared' => $total,
            'status' => 'declared',
            'declared_by' => $declaredBy->id,
        ]);
    }

    /** Approve a declared dividend (FR-SHR-04). Checker step. */
    public function approveDividend(Dividend $dividend, User $approver): Dividend
    {
        if ($dividend->status !== 'declared') {
            throw new DomainException('Only declared dividends can be approved.');
        }

        if ($dividend->declared_by === $approver->id) {
            throw new DomainException('A dividend cannot be approved by the user who declared it (maker-checker).');
        }

        $dividend->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $dividend;
    }

    /**
     * Distribute an approved dividend pro-rata (FR-SHR-05). Chunked and
     * idempotent (unique payout per account). Members with an active savings
     * account get credited there; others remain payable (GL 2040).
     */
    public function distributeDividend(Dividend $dividend, User $user): int
    {
        if ($dividend->status !== 'approved') {
            throw new DomainException('Only approved dividends can be distributed.');
        }

        $rateFraction = bcdiv((string) $dividend->rate, '100', 8);

        // One summary GL entry: retained earnings → dividends payable
        $this->transactions->post(
            description: "Dividend declaration FY{$dividend->financial_year} ({$dividend->rate}% on share capital)",
            lines: [
                ['account' => GlCodes::id('3030'), 'debit' => $dividend->total_declared],
                ['account' => GlCodes::id('2040'), 'credit' => $dividend->total_declared],
            ],
            source: $dividend,
            postedBy: $user,
        );

        $paid = 0;

        ShareAccount::where('share_product_id', $dividend->share_product_id)
            ->where('status', 'active')
            ->with('member:id,member_no,first_name,last_name,status')
            ->chunkById(200, function ($accounts) use ($dividend, $rateFraction, $user, &$paid) {
                foreach ($accounts as $account) {
                    $paid += DB::transaction(function () use ($dividend, $account, $rateFraction, $user) {
                        // Idempotency: skip if this account already has a payout
                        if ($dividend->payouts()->where('share_account_id', $account->id)->exists()) {
                            return 0;
                        }

                        $amount = bcmul($account->value, $rateFraction, 2);
                        if (bccomp($amount, '0.00', 2) !== 1) {
                            return 0;
                        }

                        $savingsAccount = SavingsAccount::where('member_id', $account->member_id)
                            ->where('status', 'active')
                            ->orderBy('id')
                            ->first();

                        $payout = $dividend->payouts()->create([
                            'share_account_id' => $account->id,
                            'shares_held' => $account->shares_count,
                            'amount' => $amount,
                            'method' => $savingsAccount ? 'savings_credit' : 'payable',
                            'status' => $savingsAccount ? 'paid' : 'pending',
                        ]);

                        if ($savingsAccount) {
                            $txn = $this->savings->creditDividend(
                                $savingsAccount,
                                $amount,
                                "Dividend FY{$dividend->financial_year} ({$account->shares_count} shares)",
                                $user,
                            );
                            $payout->update(['savings_transaction_id' => $txn->id]);
                        }

                        return 1;
                    });
                }
            });

        $dividend->update(['status' => 'distributed', 'distributed_at' => now()]);

        return $paid;
    }

    // ── internals ──────────────────────────────────────────────────────────

    protected function record(
        ShareAccount $account,
        string $type,
        int $shares,
        string $amount,
        int $sharesAfter,
        ?User $performer,
        ?int $counterpartyId = null,
    ): ShareTransaction {
        return ShareTransaction::create([
            'reference' => $this->nextReference(),
            'share_account_id' => $account->id,
            'type' => $type,
            'shares' => $shares,
            'amount' => $amount,
            'shares_after' => $sharesAfter,
            'counterparty_account_id' => $counterpartyId,
            'performed_by' => $performer?->id,
            'value_date' => today()->toDateString(),
        ]);
    }

    protected function requireOpenSession(User $teller): TellerSession
    {
        $session = TellerSession::openFor($teller);

        if ($session === null) {
            throw new DomainException('You need an open teller session before handling share cash.');
        }

        return $session;
    }

    protected function nextReference(): string
    {
        $row = DB::table('settings')->where('key', 'share_receipt_sequence')->lockForUpdate()->first();

        if ($row === null) {
            DB::table('settings')->insert([
                'key' => 'share_receipt_sequence',
                'value' => json_encode(1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = 1;
        } else {
            $sequence = (int) json_decode($row->value) + 1;
            DB::table('settings')->where('key', 'share_receipt_sequence')
                ->update(['value' => json_encode($sequence), 'updated_at' => now()]);
        }

        return sprintf('SHR-%06d', $sequence);
    }
}
