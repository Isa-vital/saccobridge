<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Services\SavingsService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsAccountController extends Controller
{
    public function __construct(private readonly SavingsService $savings) {}

    public function index(Request $request): Response
    {
        $accounts = SavingsAccount::query()
            ->with(['member:id,member_no,first_name,last_name', 'product:id,name,code'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where('account_no', 'like', "%{$term}%")
                    ->orWhereHas('member', fn($q) => $q->search($term));
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn(SavingsAccount $account) => [
                'id' => $account->id,
                'account_no' => $account->account_no,
                'member' => $account->member->member_no . ' — ' . $account->member->full_name,
                'product' => $account->product->name,
                'balance' => $account->balance,
                'blocked_amount' => $account->blocked_amount,
                'status' => $account->status,
                'opened_at' => $account->opened_at->format('Y-m-d'),
            ]);

        return Inertia::render('savings/Accounts', [
            'accounts' => $accounts,
            'filters' => $request->only(['search']),
            'products' => SavingsProduct::where('is_active', true)->get(['id', 'code', 'name', 'min_opening_deposit']),
            'can' => ['open' => $request->user()->can('savings.open')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('savings.open'), 403);

        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'savings_product_id' => ['required', 'exists:savings_products,id'],
            'opening_deposit' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $account = $this->savings->openAccount(
                Member::findOrFail($data['member_id']),
                SavingsProduct::findOrFail($data['savings_product_id']),
                number_format((float) $data['opening_deposit'], 2, '.', ''),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('savings.accounts.show', $account)
            ->with('success', "Account {$account->account_no} opened.");
    }

    /** Account statement with date range (FR-SAV-08). */
    public function show(Request $request, SavingsAccount $account): Response
    {
        $account->load(['member:id,member_no,first_name,last_name', 'product']);

        $from = $request->date('from') ?? now()->subMonths(3)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $transactions = $account->transactions()
            ->with('performer:id,name')
            ->whereBetween('value_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn($txn) => [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'value_date' => $txn->value_date->format('Y-m-d'),
                'type' => $txn->type,
                'amount' => $txn->amount,
                'balance_after' => $txn->balance_after,
                'status' => $txn->status,
                'memo' => $txn->memo,
                'performed_by' => $txn->performer?->name,
            ]);

        return Inertia::render('savings/AccountShow', [
            'account' => [
                'id' => $account->id,
                'account_no' => $account->account_no,
                'member' => $account->member->member_no . ' — ' . $account->member->full_name,
                'member_id' => $account->member_id,
                'product' => $account->product->name,
                'balance' => $account->balance,
                'blocked_amount' => $account->blocked_amount,
                'available' => $account->availableBalance(),
                'status' => $account->status,
                'opened_at' => $account->opened_at->format('Y-m-d'),
            ],
            'transactions' => $transactions,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
