<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use App\Models\SavingsProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SavingsProductController extends Controller
{
    public function index(): Response
    {
        $products = SavingsProduct::query()
            ->withCount('accounts')
            ->orderBy('code')
            ->get()
            ->map(fn(SavingsProduct $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'interest_rate' => $product->interest_rate,
                'interest_basis' => $product->interest_basis,
                'interest_posting' => $product->interest_posting,
                'min_opening_deposit' => $product->min_opening_deposit,
                'min_balance' => $product->min_balance,
                'withdrawal_fee' => $product->withdrawal_fee,
                'max_withdrawals_per_month' => $product->max_withdrawals_per_month,
                'is_active' => $product->is_active,
                'accounts_count' => $product->accounts_count,
            ]);

        $glAccounts = [
            'liability' => GlAccount::where('type', 'liability')->whereNotNull('parent_id')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'expense' => GlAccount::where('type', 'expense')->whereNotNull('parent_id')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ];

        return Inertia::render('savings/Products', [
            'products' => $products,
            'glAccounts' => $glAccounts,
            'can' => ['manage' => request()->user()->can('admin.settings')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.settings'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:savings_products,code'],
            'name' => ['required', 'string', 'max:100'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_basis' => ['required', Rule::in(['daily_balance', 'monthly_min_balance'])],
            'interest_posting' => ['required', Rule::in(['monthly', 'quarterly', 'annually'])],
            'min_opening_deposit' => ['required', 'numeric', 'min:0'],
            'min_balance' => ['required', 'numeric', 'min:0'],
            'withdrawal_fee' => ['required', 'numeric', 'min:0'],
            'max_withdrawals_per_month' => ['nullable', 'integer', 'min:1'],
            'gl_liability_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'liability')],
            'gl_interest_expense_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'expense')],
        ]);

        $product = SavingsProduct::create($data);

        return back()->with('success', "Product {$product->name} created.");
    }

    public function toggle(Request $request, SavingsProduct $product): RedirectResponse
    {
        abort_unless($request->user()->can('admin.settings'), 403);

        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', "Product {$product->name} " . ($product->is_active ? 'activated' : 'deactivated') . '.');
    }
}
