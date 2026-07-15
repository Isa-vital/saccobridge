<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GlAccountController extends Controller
{
    /** Chart of Accounts tree. */
    public function index(): Response
    {
        $accounts = GlAccount::query()
            ->orderBy('code')
            ->get()
            ->map(fn(GlAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'parent_id' => $account->parent_id,
                'is_system' => $account->is_system,
                'is_active' => $account->is_active,
                'balance' => $account->balance(),
            ]);

        return Inertia::render('gl/Accounts', [
            'accounts' => $accounts,
            'can' => ['manage' => request()->user()->can('gl.manage_coa')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('gl.manage_coa'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:gl_accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id' => ['nullable', 'exists:gl_accounts,id'],
        ]);

        GlAccount::create([...$data, 'is_system' => false]);

        return back()->with('success', "Account {$data['code']} created.");
    }

    public function toggle(Request $request, GlAccount $account): RedirectResponse
    {
        abort_unless($request->user()->can('gl.manage_coa'), 403);

        if ($account->is_system) {
            return back()->with('error', 'System accounts cannot be deactivated.');
        }

        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', "Account {$account->code} " . ($account->is_active ? 'activated' : 'deactivated') . '.');
    }

    /** Single-account ledger with running balance. */
    public function ledger(Request $request, GlAccount $account): Response
    {
        $lines = $account->lines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select(
                'journal_lines.*',
                'journal_entries.reference',
                'journal_entries.entry_date',
                'journal_entries.description',
                'journal_entries.status'
            )
            ->get();

        $running = '0.00';
        $ledger = $lines->map(function ($line) use (&$running, $account) {
            $movement = $account->isDebitNormal()
                ? bcsub($line->debit, $line->credit, 2)
                : bcsub($line->credit, $line->debit, 2);
            $running = bcadd($running, $movement, 2);

            return [
                'id' => $line->id,
                'date' => $line->entry_date,
                'reference' => $line->reference,
                'description' => $line->description,
                'memo' => $line->memo,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'balance' => $running,
                'entry_status' => $line->status,
            ];
        });

        return Inertia::render('gl/Ledger', [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'ledger' => $ledger,
        ]);
    }

    /** Trial balance — proves Σ debits == Σ credits across the books. */
    public function trialBalance(): Response
    {
        $rows = GlAccount::query()
            ->leftJoin('journal_lines', 'journal_lines.gl_account_id', '=', 'gl_accounts.id')
            ->groupBy('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name', 'gl_accounts.type')
            ->orderBy('gl_accounts.code')
            ->selectRaw('gl_accounts.id, gl_accounts.code, gl_accounts.name, gl_accounts.type,
                COALESCE(SUM(journal_lines.debit), 0) as total_debit,
                COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->havingRaw('COALESCE(SUM(journal_lines.debit), 0) <> 0 OR COALESCE(SUM(journal_lines.credit), 0) <> 0')
            ->get()
            ->map(function ($row) {
                $net = bcsub((string) $row->total_debit, (string) $row->total_credit, 2);

                return [
                    'id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'debit' => bccomp($net, '0.00', 2) === 1 ? $net : '0.00',
                    'credit' => bccomp($net, '0.00', 2) === -1 ? bcmul($net, '-1', 2) : '0.00',
                ];
            });

        return Inertia::render('gl/TrialBalance', [
            'rows' => $rows,
            'totals' => [
                'debit' => $rows->reduce(fn($carry, $row) => bcadd($carry, $row['debit'], 2), '0.00'),
                'credit' => $rows->reduce(fn($carry, $row) => bcadd($carry, $row['credit'], 2), '0.00'),
            ],
        ]);
    }
}
