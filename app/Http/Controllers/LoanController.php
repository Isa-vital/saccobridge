<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Services\LoanService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans)
    {
    }

    /** Loan pipeline / portfolio list. */
    public function index(Request $request): Response
    {
        $loans = Loan::query()
            ->with(['member:id,member_no,first_name,last_name', 'product:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($qq) => $qq
                ->where('loan_no', 'like', '%' . $request->string('search') . '%')
                ->orWhereHas('member', fn ($m) => $m->search($request->string('search')->toString()))))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Loan $loan) => [
                'id' => $loan->id,
                'loan_no' => $loan->loan_no,
                'member' => $loan->member->member_no . ' — ' . $loan->member->full_name,
                'product' => $loan->product->code,
                'applied_amount' => $loan->applied_amount,
                'approved_amount' => $loan->approved_amount,
                'principal_outstanding' => $loan->principal_outstanding,
                'status' => $loan->status,
                'classification' => $loan->classification,
                'days_in_arrears' => $loan->days_in_arrears,
            ]);

        $stats = [
            'portfolio' => (string) Loan::whereIn('status', ['active'])->sum('principal_outstanding'),
            'pending' => Loan::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => Loan::where('status', 'approved')->count(),
            'in_arrears' => Loan::where('status', 'active')->where('days_in_arrears', '>', 0)->count(),
        ];

        return Inertia::render('loans/Index', [
            'loans' => $loans,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
            'can' => [
                'create' => $request->user()->can('loans.create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $costPreview = null;
        if ($request->filled(['loan_product_id', 'amount', 'term_months'])) {
            try {
                $costPreview = $this->loans->costOfCredit(
                    LoanProduct::findOrFail($request->integer('loan_product_id')),
                    number_format((float) $request->input('amount'), 2, '.', ''),
                    $request->integer('term_months'),
                );
            } catch (DomainException) {
                // ignore invalid previews
            }
        }

        return Inertia::render('loans/Create', [
            'products' => LoanProduct::where('is_active', true)->get(),
            'costPreview' => $costPreview,
            'previewInput' => $request->only(['loan_product_id', 'amount', 'term_months']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('loans.create'), 403);

        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'term_months' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'guarantors' => ['array'],
            'guarantors.*.member_id' => ['required', 'exists:members,id'],
            'guarantors.*.savings_account_id' => ['required', 'exists:savings_accounts,id'],
            'guarantors.*.guaranteed_amount' => ['required', 'numeric', 'min:1'],
            'collateral' => ['array'],
            'collateral.*.description' => ['required', 'string', 'max:255'],
            'collateral.*.estimated_value' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $loan = $this->loans->apply(
                Member::findOrFail($data['member_id']),
                LoanProduct::findOrFail($data['loan_product_id']),
                number_format((float) $data['amount'], 2, '.', ''),
                (int) $data['term_months'],
                $data['purpose'] ?? null,
                collect($data['guarantors'] ?? [])->map(fn ($g) => [
                    'member_id' => (int) $g['member_id'],
                    'savings_account_id' => (int) $g['savings_account_id'],
                    'guaranteed_amount' => number_format((float) $g['guaranteed_amount'], 2, '.', ''),
                ])->all(),
                $data['collateral'] ?? [],
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', "Application {$loan->loan_no} submitted for approval.");
    }

    public function show(Loan $loan): Response
    {
        $loan->load(['member:id,member_no,first_name,last_name', 'product', 'schedules', 'repayments.performer:id,name',
            'guarantors.member:id,member_no,first_name,last_name', 'collateral', 'creator:id,name', 'approver:id,name']);

        $user = request()->user();

        return Inertia::render('loans/Show', [
            'loan' => [
                'id' => $loan->id,
                'loan_no' => $loan->loan_no,
                'member' => $loan->member->member_no . ' — ' . $loan->member->full_name,
                'product' => $loan->product->name,
                'interest_method' => $loan->product->interest_method,
                'interest_rate' => $loan->product->interest_rate,
                'applied_amount' => $loan->applied_amount,
                'applied_term_months' => $loan->applied_term_months,
                'approved_amount' => $loan->approved_amount,
                'approved_term_months' => $loan->approved_term_months,
                'purpose' => $loan->purpose,
                'status' => $loan->status,
                'classification' => $loan->classification,
                'days_in_arrears' => $loan->days_in_arrears,
                'provision_amount' => $loan->provision_amount,
                'principal_outstanding' => $loan->principal_outstanding,
                'interest_outstanding' => $loan->interest_outstanding,
                'fees_outstanding' => $loan->fees_outstanding,
                'penalties_outstanding' => $loan->penalties_outstanding,
                'total_outstanding' => $loan->totalOutstanding(),
                'disbursed_at' => $loan->disbursed_at?->format('Y-m-d'),
                'created_by' => $loan->created_by,
                'creator' => $loan->creator?->name,
                'approver' => $loan->approver?->name,
                'rejection_reason' => $loan->rejection_reason,
                'schedules' => $loan->schedules->map(fn ($s) => [
                    'installment_no' => $s->installment_no,
                    'due_date' => $s->due_date->format('Y-m-d'),
                    'principal_due' => $s->principal_due,
                    'interest_due' => $s->interest_due,
                    'penalties_due' => $s->penalties_due,
                    'total_paid' => bcadd(bcadd($s->principal_paid, $s->interest_paid, 2), bcadd($s->fees_paid, $s->penalties_paid, 2), 2),
                    'balance_after' => $s->balance_after,
                    'is_settled' => $s->is_settled,
                ]),
                'repayments' => $loan->repayments->map(fn ($r) => [
                    'reference' => $r->reference,
                    'value_date' => $r->value_date->format('Y-m-d'),
                    'amount' => $r->amount,
                    'principal' => $r->principal_portion,
                    'interest' => $r->interest_portion,
                    'penalties' => $r->penalties_portion,
                    'source' => $r->source,
                    'by' => $r->performer?->name,
                ]),
                'guarantors' => $loan->guarantors->map(fn ($g) => [
                    'member' => $g->member->member_no . ' — ' . $g->member->full_name,
                    'amount' => $g->guaranteed_amount,
                    'status' => $g->status,
                ]),
                'collateral' => $loan->collateral,
            ],
            'costOfCredit' => in_array($loan->status, ['submitted', 'under_review', 'approved'], true)
                ? $this->loans->costOfCredit($loan->product, (string) ($loan->approved_amount ?? $loan->applied_amount), $loan->approved_term_months ?? $loan->applied_term_months)
                : null,
            'payoffQuote' => $loan->status === 'active' ? $this->loans->payoffQuote($loan) : null,
            'can' => [
                'approve' => $user->can('loans.approve') && $loan->created_by !== $user->id,
                'disburse' => $user->can('loans.disburse') && $loan->created_by !== $user->id,
                'repay' => $user->can('loans.repay'),
            ],
        ]);
    }

    public function approve(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->can('loans.approve'), 403);

        $data = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:1'],
            'approved_term_months' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $this->loans->approve(
                $loan,
                $request->user(),
                isset($data['approved_amount']) ? number_format((float) $data['approved_amount'], 2, '.', '') : null,
                $data['approved_term_months'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Loan {$loan->loan_no} approved.");
    }

    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->can('loans.approve'), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->loans->reject($loan, $request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Loan {$loan->loan_no} rejected.");
    }

    public function disburse(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->can('loans.disburse'), 403);

        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'savings'])],
        ]);

        try {
            $this->loans->disburse($loan, $request->user(), $data['method']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Loan {$loan->loan_no} disbursed. Schedule generated.");
    }

    public function repay(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless($request->user()->can('loans.repay'), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', Rule::in(['cash', 'savings'])],
        ]);

        try {
            $repayment = $this->loans->repay(
                $loan,
                number_format((float) $data['amount'], 2, '.', ''),
                $request->user(),
                $data['source'],
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Repayment {$repayment->reference} posted.");
    }

    /** Loan products management. */
    public function products(): Response
    {
        return Inertia::render('loans/Products', [
            'products' => LoanProduct::withCount('loans')->orderBy('code')->get(),
            'glAccounts' => [
                'asset' => GlAccount::where('type', 'asset')->whereNotNull('parent_id')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
                'income' => GlAccount::where('type', 'income')->whereNotNull('parent_id')->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            ],
            'can' => ['manage' => request()->user()->can('admin.settings')],
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.settings'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:loan_products,code'],
            'name' => ['required', 'string', 'max:100'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:120'],
            'interest_method' => ['required', Rule::in(['flat', 'reducing_balance'])],
            'min_term_months' => ['required', 'integer', 'min:1'],
            'max_term_months' => ['required', 'integer', 'gte:min_term_months'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'grace_period_days' => ['required', 'integer', 'min:0'],
            'application_fee' => ['required', 'numeric', 'min:0'],
            'processing_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'penalty_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'required_guarantors' => ['required', 'integer', 'min:0'],
            'savings_multiple' => ['nullable', 'numeric', 'min:0.1'],
            'gl_portfolio_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'asset')],
            'gl_interest_income_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'income')],
            'gl_fee_income_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'income')],
            'gl_penalty_income_account_id' => ['required', Rule::exists('gl_accounts', 'id')->where('type', 'income')],
        ]);

        $product = LoanProduct::create($data);

        return back()->with('success', "Loan product {$product->name} created.");
    }
}
