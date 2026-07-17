<?php

namespace App\Services;

use App\Models\GlAccount;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\ShareAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * UMRA regulatory reports (FR-RPT). All financial figures derive from the
 * general ledger so they always reconcile with the trial balance.
 */
class ReportService
{
    /**
     * Balance Sheet (Statement of Financial Position) as of a date.
     * assets == liabilities + equity + net surplus (to date).
     */
    public function balanceSheet(CarbonInterface $asOf): array
    {
        $balances = $this->accountBalances(to: $asOf);

        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $totals = ['asset' => '0.00', 'liability' => '0.00', 'equity' => '0.00'];
        $income = '0.00';
        $expense = '0.00';

        foreach ($balances as $row) {
            if (in_array($row->type, ['asset', 'liability', 'equity'], true)) {
                if (bccomp($row->balance, '0.00', 2) !== 0) {
                    $sections[$row->type][] = [
                        'code' => $row->code,
                        'name' => $row->name,
                        'balance' => $row->balance,
                    ];
                    $totals[$row->type] = bcadd($totals[$row->type], $row->balance, 2);
                }
            } elseif ($row->type === 'income') {
                $income = bcadd($income, $row->balance, 2);
            } else {
                $expense = bcadd($expense, $row->balance, 2);
            }
        }

        $surplus = bcsub($income, $expense, 2);
        $liabilitiesAndEquity = bcadd(bcadd($totals['liability'], $totals['equity'], 2), $surplus, 2);

        return [
            'as_of' => $asOf->toDateString(),
            'assets' => $sections['asset'],
            'liabilities' => $sections['liability'],
            'equity' => $sections['equity'],
            'totals' => [
                'assets' => $totals['asset'],
                'liabilities' => $totals['liability'],
                'equity' => $totals['equity'],
                'surplus' => $surplus,
                'liabilities_and_equity' => $liabilitiesAndEquity,
            ],
            'balanced' => bccomp($totals['asset'], $liabilitiesAndEquity, 2) === 0,
        ];
    }

    /** Income Statement for a period. */
    public function incomeStatement(CarbonInterface $from, CarbonInterface $to): array
    {
        $balances = $this->accountBalances(from: $from, to: $to);

        $income = [];
        $expenses = [];
        $totalIncome = '0.00';
        $totalExpenses = '0.00';

        foreach ($balances as $row) {
            if (bccomp($row->balance, '0.00', 2) === 0) {
                continue;
            }
            if ($row->type === 'income') {
                $income[] = ['code' => $row->code, 'name' => $row->name, 'balance' => $row->balance];
                $totalIncome = bcadd($totalIncome, $row->balance, 2);
            } elseif ($row->type === 'expense') {
                $expenses[] = ['code' => $row->code, 'name' => $row->name, 'balance' => $row->balance];
                $totalExpenses = bcadd($totalExpenses, $row->balance, 2);
            }
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'income' => $income,
            'expenses' => $expenses,
            'totals' => [
                'income' => $totalIncome,
                'expenses' => $totalExpenses,
                'surplus' => bcsub($totalIncome, $totalExpenses, 2),
            ],
        ];
    }

    /**
     * Portfolio at Risk / aging report (UMRA loan classification).
     * PAR ratio = outstanding principal of loans >0 days in arrears / gross portfolio.
     */
    public function parReport(): array
    {
        $buckets = [];
        $grossPortfolio = '0.00';
        $atRisk = '0.00';
        $totalProvision = '0.00';

        foreach (LoanService::CLASSIFICATIONS as $bucket) {
            $row = Loan::where('status', 'active')
                ->where('classification', $bucket['name'])
                ->selectRaw('COUNT(*) as loans, COALESCE(SUM(principal_outstanding),0) as outstanding, COALESCE(SUM(provision_amount),0) as provision')
                ->first();

            $outstanding = (string) $row->outstanding;
            $provision = (string) $row->provision;

            $buckets[] = [
                'classification' => $bucket['name'],
                'provision_rate' => bcmul($bucket['rate'], '100', 0) . '%',
                'loans' => (int) $row->loans,
                'outstanding' => $outstanding,
                'provision' => $provision,
            ];

            $grossPortfolio = bcadd($grossPortfolio, $outstanding, 2);
            $totalProvision = bcadd($totalProvision, $provision, 2);
            if ($bucket['name'] !== 'performing') {
                $atRisk = bcadd($atRisk, $outstanding, 2);
            }
        }

        $parRatio = bccomp($grossPortfolio, '0.00', 2) === 1
            ? bcmul(bcdiv($atRisk, $grossPortfolio, 6), '100', 2)
            : '0.00';

        return [
            'as_of' => today()->toDateString(),
            'buckets' => $buckets,
            'totals' => [
                'gross_portfolio' => $grossPortfolio,
                'portfolio_at_risk' => $atRisk,
                'par_ratio' => $parRatio,
                'total_provision' => $totalProvision,
                'net_portfolio' => bcsub($grossPortfolio, $totalProvision, 2),
            ],
        ];
    }

    /** Savings summary per product. */
    public function savingsSummary(): array
    {
        $products = SavingsProduct::query()
            ->leftJoin('savings_accounts', function ($join) {
                $join->on('savings_accounts.savings_product_id', '=', 'savings_products.id')
                    ->where('savings_accounts.status', '!=', 'closed');
            })
            ->groupBy('savings_products.id', 'savings_products.code', 'savings_products.name')
            ->selectRaw('savings_products.code, savings_products.name,
                COUNT(savings_accounts.id) as accounts,
                COALESCE(SUM(savings_accounts.balance),0) as balance,
                COALESCE(SUM(savings_accounts.blocked_amount),0) as blocked')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'accounts' => (int) $row->accounts,
                'balance' => (string) $row->balance,
                'blocked' => (string) $row->blocked,
            ]);

        return [
            'as_of' => today()->toDateString(),
            'products' => $products->all(),
            'totals' => [
                'accounts' => $products->sum('accounts'),
                'balance' => (string) $products->reduce(fn ($c, $r) => bcadd($c, $r['balance'], 2), '0.00'),
                'blocked' => (string) $products->reduce(fn ($c, $r) => bcadd($c, $r['blocked'], 2), '0.00'),
            ],
        ];
    }

    /** Institutional overview for the UMRA periodic return. */
    public function institutionalSummary(): array
    {
        return [
            'as_of' => today()->toDateString(),
            'members' => [
                'total' => Member::count(),
                'active' => Member::where('status', 'active')->count(),
                'pending' => Member::where('status', 'pending')->count(),
                'exited' => Member::where('status', 'exited')->count(),
            ],
            'savings' => [
                'accounts' => SavingsAccount::where('status', '!=', 'closed')->count(),
                'balance' => (string) SavingsAccount::where('status', '!=', 'closed')->sum('balance'),
            ],
            'shares' => [
                'shareholders' => ShareAccount::where('status', 'active')->where('shares_count', '>', 0)->count(),
                'shares' => (int) ShareAccount::where('status', 'active')->sum('shares_count'),
                'capital' => (string) ShareAccount::where('status', 'active')->sum('value'),
            ],
            'loans' => [
                'active' => Loan::where('status', 'active')->count(),
                'outstanding' => (string) Loan::where('status', 'active')->sum('principal_outstanding'),
                'in_arrears' => Loan::where('status', 'active')->where('days_in_arrears', '>', 0)->count(),
                'provisions' => (string) Loan::where('status', 'active')->sum('provision_amount'),
            ],
        ];
    }

    /**
     * Account balances by type, optionally date-bounded, signed by normal
     * balance (assets/expenses debit-normal; the rest credit-normal).
     *
     * @return \Illuminate\Support\Collection<int, object{code:string,name:string,type:string,balance:string}>
     */
    protected function accountBalances(?CarbonInterface $from = null, ?CarbonInterface $to = null)
    {
        return GlAccount::query()
            ->whereNotNull('parent_id') // leaf/postable accounts only
            ->leftJoin('journal_lines', 'journal_lines.gl_account_id', '=', 'gl_accounts.id')
            ->leftJoin('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            // NB: entry_date is stored as a datetime ('Y-m-d 00:00:00'), so
            // bound with full day datetimes, not date strings.
            ->when($from, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('journal_entries.entry_date', '>=', $from->copy()->startOfDay()->toDateTimeString())
                ->orWhereNull('journal_entries.id')))
            ->when($to, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('journal_entries.entry_date', '<=', $to->copy()->endOfDay()->toDateTimeString())
                ->orWhereNull('journal_entries.id')))
            ->groupBy('gl_accounts.id', 'gl_accounts.code', 'gl_accounts.name', 'gl_accounts.type')
            ->orderBy('gl_accounts.code')
            ->selectRaw("gl_accounts.code, gl_accounts.name, gl_accounts.type,
                CASE WHEN gl_accounts.type IN ('asset', 'expense')
                    THEN COALESCE(SUM(journal_lines.debit),0) - COALESCE(SUM(journal_lines.credit),0)
                    ELSE COALESCE(SUM(journal_lines.credit),0) - COALESCE(SUM(journal_lines.debit),0)
                END as balance")
            ->get()
            ->map(function ($row) {
                $row->balance = number_format((float) $row->balance, 2, '.', '');

                return $row;
            });
    }
}
