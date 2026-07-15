<?php

namespace App\Services;

use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\Setting;
use App\Models\TellerSession;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Savings core (FR-SAV-02..06, 09). Every monetary operation:
 *  - locks the account row (lockForUpdate) — safe under concurrent tellers
 *  - posts a balanced GL entry via TransactionService
 *  - writes a SavingsTransaction with a balance_after snapshot
 */
class SavingsService
{
    public function __construct(private readonly TransactionService $transactions)
    {
    }

    /** Open an account for an ACTIVE member with the product's min opening deposit (FR-SAV-02). */
    public function openAccount(Member $member, SavingsProduct $product, string $openingDeposit, User $teller): SavingsAccount
    {
        if ($member->status !== 'active') {
            throw new DomainException('Savings accounts can only be opened for active members.');
        }

        if (! $product->is_active) {
            throw new DomainException('This savings product is not active.');
        }

        if (bccomp($openingDeposit, (string) $product->min_opening_deposit, 2) === -1) {
            throw new DomainException("Opening deposit is below the product minimum ({$product->min_opening_deposit}).");
        }

        return DB::transaction(function () use ($member, $product, $openingDeposit, $teller) {
            $account = SavingsAccount::create([
                'account_no' => $this->nextAccountNo(),
                'member_id' => $member->id,
                'savings_product_id' => $product->id,
                'balance' => 0,
                'status' => 'active',
                'opened_at' => today(),
            ]);

            if (bccomp($openingDeposit, '0.00', 2) === 1) {
                $this->deposit($account, $openingDeposit, $teller, memo: 'Opening deposit');
            }

            return $account->refresh();
        });
    }

    /** Cash deposit via teller (FR-SAV-03). GL: Dr 1020 Teller Cash / Cr product liability. */
    public function deposit(SavingsAccount $account, string $amount, User $teller, ?string $memo = null, ?CarbonInterface $valueDate = null): SavingsTransaction
    {
        $this->assertPositive($amount);
        $session = $this->requireOpenSession($teller);

        return DB::transaction(function () use ($account, $amount, $teller, $memo, $valueDate, $session) {
            $account = SavingsAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->assertAccountActive($account);

            $newBalance = bcadd($account->balance, $amount, 2);
            $account->update(['balance' => $newBalance]);

            $transaction = $this->record($account, 'deposit', $amount, $newBalance, $teller, $session, $memo, $valueDate);

            $entry = $this->transactions->post(
                description: "Deposit {$transaction->reference} — {$account->account_no}",
                lines: [
                    ['account' => GlCodes::tellerCash(), 'debit' => $amount],
                    ['account' => $account->product->gl_liability_account_id, 'credit' => $amount],
                ],
                date: $valueDate,
                source: $transaction,
                postedBy: $teller,
            );

            $transaction->update(['journal_entry_id' => $entry->id]);

            return $transaction;
        });
    }

    /**
     * Cash withdrawal (FR-SAV-03/04/09). Enforces available balance (blocks +
     * min balance), withdrawal fee, monthly withdrawal cap, and routes large
     * amounts to manager approval (maker-checker).
     */
    public function withdraw(SavingsAccount $account, string $amount, User $teller, ?string $memo = null): SavingsTransaction
    {
        $this->assertPositive($amount);
        $session = $this->requireOpenSession($teller);

        return DB::transaction(function () use ($account, $amount, $teller, $memo, $session) {
            $account = SavingsAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->assertAccountActive($account);

            $product = $account->product;
            $fee = (string) $product->withdrawal_fee;
            $totalDebit = bcadd($amount, $fee, 2);

            if (bccomp($totalDebit, $account->availableBalance(), 2) === 1) {
                throw new DomainException(
                    "Insufficient available balance. Available: {$account->availableBalance()} (after blocks, min balance" .
                    (bccomp($fee, '0.00', 2) === 1 ? " and fee {$fee}" : '') . ').'
                );
            }

            if ($product->max_withdrawals_per_month !== null) {
                $count = $account->transactions()
                    ->where('type', 'withdrawal')
                    ->where('status', 'completed')
                    ->whereBetween('value_date', [today()->startOfMonth(), today()->endOfMonth()])
                    ->count();

                if ($count >= $product->max_withdrawals_per_month) {
                    throw new DomainException("Monthly withdrawal limit ({$product->max_withdrawals_per_month}) reached for this account.");
                }
            }

            // Large withdrawals require manager approval before cash leaves (FR-SAV-04)
            $threshold = (string) Setting::get('withdrawal_approval_threshold', '0');
            if (bccomp($threshold, '0.00', 2) === 1 && bccomp($amount, $threshold, 2) === 1) {
                return $this->record($account, 'withdrawal', $amount, $account->balance, $teller, $session, $memo, status: 'pending_approval');
            }

            return $this->executeWithdrawal($account, $amount, $teller, $session, $memo);
        });
    }

    /** Manager approval of a pending withdrawal (FR-SAV-04). */
    public function approveWithdrawal(SavingsTransaction $transaction, User $approver): SavingsTransaction
    {
        if ($transaction->status !== 'pending_approval') {
            throw new DomainException('This transaction is not awaiting approval.');
        }

        if ($transaction->performed_by === $approver->id) {
            throw new DomainException('A withdrawal cannot be approved by the teller who initiated it (maker-checker).');
        }

        return DB::transaction(function () use ($transaction, $approver) {
            $account = SavingsAccount::whereKey($transaction->savings_account_id)->lockForUpdate()->firstOrFail();

            // Re-validate liquidity at approval time
            $totalDebit = bcadd($transaction->amount, (string) $account->product->withdrawal_fee, 2);
            if (bccomp($totalDebit, $account->availableBalance(), 2) === 1) {
                $transaction->update(['status' => 'rejected', 'approved_by' => $approver->id]);
                throw new DomainException('Available balance no longer covers this withdrawal — transaction rejected.');
            }

            $session = TellerSession::find($transaction->teller_session_id);
            $executed = $this->executeWithdrawal(
                $account,
                (string) $transaction->amount,
                $transaction->performer,
                $session,
                $transaction->memo,
                existing: $transaction,
            );
            $executed->update(['approved_by' => $approver->id]);

            return $executed;
        });
    }

    /** Internal account-to-account transfer (FR-SAV-05). */
    public function transfer(SavingsAccount $from, SavingsAccount $to, string $amount, User $teller, ?string $memo = null): array
    {
        $this->assertPositive($amount);

        if ($from->id === $to->id) {
            throw new DomainException('Cannot transfer to the same account.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $teller, $memo) {
            // Lock in a stable order to avoid deadlocks between concurrent transfers
            $ids = [$from->id, $to->id];
            sort($ids);
            $locked = SavingsAccount::whereIn('id', $ids)->lockForUpdate()->orderBy('id')->get()->keyBy('id');
            $from = $locked[$from->id];
            $to = $locked[$to->id];

            $this->assertAccountActive($from);
            $this->assertAccountActive($to);

            if (bccomp($amount, $from->availableBalance(), 2) === 1) {
                throw new DomainException("Insufficient available balance for transfer. Available: {$from->availableBalance()}.");
            }

            $fromBalance = bcsub($from->balance, $amount, 2);
            $toBalance = bcadd($to->balance, $amount, 2);
            $from->update(['balance' => $fromBalance]);
            $to->update(['balance' => $toBalance]);

            $out = $this->record($from, 'transfer_out', $amount, $fromBalance, $teller, null, $memo ?? "Transfer to {$to->account_no}");
            $in = $this->record($to, 'transfer_in', $amount, $toBalance, $teller, null, $memo ?? "Transfer from {$from->account_no}");

            // GL: move between product liability accounts (may be the same account)
            $entry = $this->transactions->post(
                description: "Transfer {$out->reference} — {$from->account_no} → {$to->account_no}",
                lines: [
                    ['account' => $from->product->gl_liability_account_id, 'debit' => $amount],
                    ['account' => $to->product->gl_liability_account_id, 'credit' => $amount],
                ],
                source: $out,
                postedBy: $teller,
            );

            $out->update(['journal_entry_id' => $entry->id]);
            $in->update(['journal_entry_id' => $entry->id]);

            return [$out, $in];
        });
    }

    /**
     * Accrue and post monthly interest for one account (FR-SAV-06).
     * Simple monthly accrual: annual_rate / 12 applied to current balance.
     * GL: Dr product interest expense / Cr product liability.
     */
    public function postInterest(SavingsAccount $account, ?CarbonInterface $asOf = null): ?SavingsTransaction
    {
        $asOf ??= today();

        return DB::transaction(function () use ($account, $asOf) {
            $account = SavingsAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($account->status !== 'active') {
                return null;
            }

            $product = $account->product;
            $monthlyRate = bcdiv((string) $product->interest_rate, '1200', 10); // % annual → monthly fraction
            $interest = bcmul($account->balance, $monthlyRate, 2);

            if (bccomp($interest, '0.00', 2) !== 1) {
                return null;
            }

            // Idempotency: one interest posting per account per month
            $alreadyPosted = $account->transactions()
                ->where('type', 'interest')
                ->whereBetween('value_date', [$asOf->copy()->startOfMonth(), $asOf->copy()->endOfMonth()])
                ->exists();

            if ($alreadyPosted) {
                return null;
            }

            $newBalance = bcadd($account->balance, $interest, 2);
            $account->update(['balance' => $newBalance]);

            $transaction = $this->record($account, 'interest', $interest, $newBalance, null, null, "Interest for {$asOf->format('Y-m')}", $asOf);

            $entry = $this->transactions->post(
                description: "Interest {$transaction->reference} — {$account->account_no} ({$asOf->format('Y-m')})",
                lines: [
                    ['account' => $product->gl_interest_expense_account_id, 'debit' => $interest],
                    ['account' => $product->gl_liability_account_id, 'credit' => $interest],
                ],
                date: $asOf,
                source: $transaction,
            );

            $transaction->update(['journal_entry_id' => $entry->id]);

            return $transaction;
        });
    }

    // ── internals ──────────────────────────────────────────────────────────

    /** Execute the cash movement of an (approved or small) withdrawal. */
    protected function executeWithdrawal(
        SavingsAccount $account,
        string $amount,
        User $teller,
        ?TellerSession $session,
        ?string $memo,
        ?SavingsTransaction $existing = null,
    ): SavingsTransaction {
        $product = $account->product;
        $fee = (string) $product->withdrawal_fee;

        $newBalance = bcsub($account->balance, bcadd($amount, $fee, 2), 2);
        $account->update(['balance' => $newBalance]);

        if ($existing) {
            $existing->update(['status' => 'completed', 'balance_after' => bcadd($newBalance, $fee, 2)]);
            $transaction = $existing->refresh();
        } else {
            $transaction = $this->record($account, 'withdrawal', $amount, bcadd($newBalance, $fee, 2), $teller, $session, $memo);
        }

        $lines = [
            ['account' => $product->gl_liability_account_id, 'debit' => bcadd($amount, $fee, 2)],
            ['account' => GlCodes::tellerCash(), 'credit' => $amount],
        ];
        if (bccomp($fee, '0.00', 2) === 1) {
            $lines[] = ['account' => GlCodes::feeIncome(), 'credit' => $fee];
        }

        $entry = $this->transactions->post(
            description: "Withdrawal {$transaction->reference} — {$account->account_no}",
            lines: $lines,
            source: $transaction,
            postedBy: $teller,
        );

        $transaction->update(['journal_entry_id' => $entry->id]);

        if (bccomp($fee, '0.00', 2) === 1) {
            $this->record($account, 'fee', $fee, $newBalance, $teller, $session, 'Withdrawal fee', journalEntryId: $entry->id);
        }

        return $transaction->refresh();
    }

    protected function record(
        SavingsAccount $account,
        string $type,
        string $amount,
        string $balanceAfter,
        ?User $performer,
        ?TellerSession $session,
        ?string $memo = null,
        ?CarbonInterface $valueDate = null,
        string $status = 'completed',
        ?int $journalEntryId = null,
    ): SavingsTransaction {
        return SavingsTransaction::create([
            'reference' => $this->nextReceiptNo(),
            'savings_account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'status' => $status,
            'journal_entry_id' => $journalEntryId,
            'teller_session_id' => $session?->id,
            'performed_by' => $performer?->id,
            'value_date' => ($valueDate ?? today())->toDateString(),
            'memo' => $memo,
        ]);
    }

    protected function requireOpenSession(User $teller): TellerSession
    {
        $session = TellerSession::openFor($teller);

        if ($session === null) {
            throw new DomainException('You need an open teller session (float issued) before handling cash.');
        }

        return $session;
    }

    protected function assertAccountActive(SavingsAccount $account): void
    {
        if ($account->status !== 'active') {
            throw new DomainException("Account {$account->account_no} is {$account->status}.");
        }
    }

    protected function assertPositive(string $amount): void
    {
        if (bccomp($amount, '0.00', 2) !== 1) {
            throw new DomainException('Amount must be greater than zero.');
        }
    }

    /** Sequential account number SAV-00001 via locked settings counter. */
    protected function nextAccountNo(): string
    {
        return $this->nextSequence('savings_account_sequence', 'SAV', 5);
    }

    /** Sequential receipt number RCT-000001 via locked settings counter. */
    protected function nextReceiptNo(): string
    {
        return $this->nextSequence('savings_receipt_sequence', 'RCT', 6);
    }

    protected function nextSequence(string $key, string $prefix, int $pad): string
    {
        $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

        if ($row === null) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => json_encode(1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = 1;
        } else {
            $sequence = (int) json_decode($row->value) + 1;
            DB::table('settings')->where('key', $key)
                ->update(['value' => json_encode($sequence), 'updated_at' => now()]);
        }

        return sprintf('%s-%0' . $pad . 'd', $prefix, $sequence);
    }
}
