<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use App\Models\Member;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Services\ShareService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShareController extends Controller
{
    public function __construct(private readonly ShareService $shares)
    {
    }

    /** Statutory share register (FR-SHR-03). */
    public function register(Request $request): Response
    {
        $accounts = ShareAccount::query()
            ->with(['member:id,member_no,first_name,last_name', 'product:id,code,name,nominal_value'])
            ->where('shares_count', '>', 0)
            ->when($request->filled('search'), fn ($q) => $q->whereHas('member', fn ($qq) => $qq->search($request->string('search')->toString())))
            ->orderByDesc('value')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ShareAccount $account) => [
                'id' => $account->id,
                'member' => $account->member->member_no . ' — ' . $account->member->full_name,
                'product' => $account->product->code,
                'shares_count' => $account->shares_count,
                'nominal_value' => $account->product->nominal_value,
                'value' => $account->value,
                'status' => $account->status,
            ]);

        $totals = ShareAccount::selectRaw('COALESCE(SUM(shares_count),0) as shares, COALESCE(SUM(value),0) as value')
            ->where('status', 'active')->first();

        return Inertia::render('shares/Register', [
            'accounts' => $accounts,
            'filters' => $request->only(['search']),
            'totals' => ['shares' => (int) $totals->shares, 'value' => (string) $totals->value],
            'products' => ShareProduct::where('is_active', true)->get(['id', 'code', 'name', 'nominal_value', 'min_shares', 'max_shares']),
            'can' => ['post' => $request->user()->can('shares.post')],
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('shares.post'), 403);

        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'share_product_id' => ['required', 'exists:share_products,id'],
            'shares' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $txn = $this->shares->purchase(
                Member::findOrFail($data['member_id']),
                ShareProduct::findOrFail($data['share_product_id']),
                (int) $data['shares'],
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Share purchase {$txn->reference} completed ({$txn->shares} shares, {$txn->amount}).");
    }

    public function transfer(Request $request, ShareAccount $account): RedirectResponse
    {
        abort_unless($request->user()->can('shares.post'), 403);

        $data = $request->validate([
            'to_member_id' => ['required', 'exists:members,id'],
            'shares' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->shares->transfer($account, Member::findOrFail($data['to_member_id']), (int) $data['shares'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$data['shares']} share(s) transferred.");
    }

    public function redeem(Request $request, ShareAccount $account): RedirectResponse
    {
        abort_unless($request->user()->can('shares.post'), 403);

        $data = $request->validate([
            'shares' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $txn = $this->shares->redeem($account, (int) $data['shares'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Redemption {$txn->reference} completed ({$txn->amount} paid out).");
    }

    /** Share products management. */
    public function products(): Response
    {
        return Inertia::render('shares/Products', [
            'products' => ShareProduct::withCount('accounts')->orderBy('code')->get(),
            'equityAccounts' => GlAccount::where('type', 'equity')->whereNotNull('parent_id')
                ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'can' => ['manage' => request()->user()->can('admin.settings')],
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.settings'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:share_products,code'],
            'name' => ['required', 'string', 'max:100'],
            'nominal_value' => ['required', 'numeric', 'min:1'],
            'min_shares' => ['required', 'integer', 'min:1'],
            'max_shares' => ['nullable', 'integer', 'gt:min_shares'],
            'gl_equity_account_id' => ['required', \Illuminate\Validation\Rule::exists('gl_accounts', 'id')->where('type', 'equity')],
        ]);

        $product = ShareProduct::create($data);

        return back()->with('success', "Share product {$product->name} created.");
    }
}
