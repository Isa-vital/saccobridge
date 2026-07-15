<?php

namespace App\Http\Controllers;

use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\TellerSession;
use App\Services\SavingsService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teller station: find an account, take deposits/withdrawals,
 * plus the manager approval queue for large withdrawals.
 */
class TellerController extends Controller
{
    public function __construct(private readonly SavingsService $savings)
    {
    }

    /** Teller station page. */
    public function station(Request $request): Response
    {
        $session = TellerSession::openFor($request->user());

        $account = null;
        if ($request->filled('account')) {
            $found = SavingsAccount::with(['member:id,member_no,first_name,last_name', 'product'])
                ->where('account_no', $request->string('account')->trim()->toString())
                ->first();

            if ($found) {
                $account = [
                    'id' => $found->id,
                    'account_no' => $found->account_no,
                    'member' => $found->member->member_no . ' — ' . $found->member->full_name,
                    'product' => $found->product->name,
                    'balance' => $found->balance,
                    'available' => $found->availableBalance(),
                    'status' => $found->status,
                    'withdrawal_fee' => $found->product->withdrawal_fee,
                ];
            }
        }

        return Inertia::render('savings/Teller', [
            'session' => $session ? [
                'id' => $session->id,
                'opening_float' => $session->opening_float,
                'expected_cash' => $session->expectedCash(),
                'opened_at' => $session->created_at->format('Y-m-d H:i'),
            ] : null,
            'account' => $account,
            'searched' => $request->filled('account'),
            'search' => $request->string('account')->toString(),
        ]);
    }

    public function deposit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('savings.deposit'), 403);

        $data = $request->validate([
            'account_id' => ['required', 'exists:savings_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = $this->savings->deposit(
                SavingsAccount::findOrFail($data['account_id']),
                number_format((float) $data['amount'], 2, '.', ''),
                $request->user(),
                $data['memo'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Deposit {$transaction->reference} completed. New balance: {$transaction->balance_after}");
    }

    public function withdraw(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('savings.withdraw'), 403);

        $data = $request->validate([
            'account_id' => ['required', 'exists:savings_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = $this->savings->withdraw(
                SavingsAccount::findOrFail($data['account_id']),
                number_format((float) $data['amount'], 2, '.', ''),
                $request->user(),
                $data['memo'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $transaction->status === 'pending_approval'
            ? "Withdrawal {$transaction->reference} requires manager approval."
            : "Withdrawal {$transaction->reference} completed. New balance: {$transaction->balance_after}");
    }

    /** Manager queue of withdrawals pending approval (FR-SAV-04). */
    public function approvals(Request $request): Response
    {
        $pending = SavingsTransaction::query()
            ->with(['account.member:id,member_no,first_name,last_name', 'performer:id,name'])
            ->where('status', 'pending_approval')
            ->orderBy('id')
            ->get()
            ->map(fn (SavingsTransaction $txn) => [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'account_no' => $txn->account->account_no,
                'member' => $txn->account->member->full_name,
                'amount' => $txn->amount,
                'memo' => $txn->memo,
                'performed_by' => $txn->performer?->name,
                'performed_by_id' => $txn->performed_by,
                'created_at' => $txn->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('savings/Approvals', [
            'pending' => $pending,
        ]);
    }

    public function approve(Request $request, SavingsTransaction $transaction): RedirectResponse
    {
        abort_unless($request->user()->can('savings.approve'), 403);

        try {
            $this->savings->approveWithdrawal($transaction, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Withdrawal {$transaction->reference} approved and executed.");
    }

    public function reject(Request $request, SavingsTransaction $transaction): RedirectResponse
    {
        abort_unless($request->user()->can('savings.approve'), 403);

        if ($transaction->status !== 'pending_approval') {
            return back()->with('error', 'This transaction is not awaiting approval.');
        }

        $transaction->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);

        return back()->with('success', "Withdrawal {$transaction->reference} rejected.");
    }
}
