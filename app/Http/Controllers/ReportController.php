<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(): Response
    {
        return Inertia::render('reports/Index', [
            'summary' => $this->reports->institutionalSummary(),
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $asOf = $request->date('as_of') ?? today();

        return Inertia::render('reports/BalanceSheet', [
            'report' => $this->reports->balanceSheet($asOf),
        ]);
    }

    public function incomeStatement(Request $request): Response
    {
        $from = $request->date('from') ?? today()->startOfYear();
        $to = $request->date('to') ?? today();

        return Inertia::render('reports/IncomeStatement', [
            'report' => $this->reports->incomeStatement($from, $to),
        ]);
    }

    public function par(): Response
    {
        return Inertia::render('reports/Par', [
            'report' => $this->reports->parReport(),
        ]);
    }

    public function savingsSummary(): Response
    {
        return Inertia::render('reports/SavingsSummary', [
            'report' => $this->reports->savingsSummary(),
        ]);
    }

    /** Export any report as pdf or csv. */
    public function export(Request $request, string $report, string $format)
    {
        abort_unless($request->user()->can('reports.export'), 403);
        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $document = $this->buildDocument($request, $report);

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', $document)
                ->setPaper('a4', $report === 'member-register' ? 'landscape' : 'portrait')
                ->download("{$report}-" . today()->toDateString() . '.pdf');
        }

        return $this->streamCsv($report, $document);
    }

    /** Unified document structure: title, meta, tables[{title, columns, rows}]. */
    protected function buildDocument(Request $request, string $report): array
    {
        $tenant = tenant('name');

        return match ($report) {
            'balance-sheet' => $this->balanceSheetDocument($request, $tenant),
            'income-statement' => $this->incomeStatementDocument($request, $tenant),
            'par' => $this->parDocument($tenant),
            'savings-summary' => $this->savingsSummaryDocument($tenant),
            'member-register' => $this->memberRegisterDocument($tenant),
            default => abort(404),
        };
    }

    protected function balanceSheetDocument(Request $request, ?string $tenant): array
    {
        $data = $this->reports->balanceSheet($request->date('as_of') ?? today());

        $section = fn (array $rows) => array_map(fn ($r) => [$r['code'], $r['name'], number_format((float) $r['balance'], 2)], $rows);

        return [
            'title' => 'Balance Sheet',
            'tenant' => $tenant,
            'meta' => 'As of ' . $data['as_of'],
            'tables' => [
                ['title' => 'Assets', 'columns' => ['Code', 'Account', 'Balance (UGX)'], 'rows' => $section($data['assets']),
                    'footer' => ['', 'Total Assets', number_format((float) $data['totals']['assets'], 2)]],
                ['title' => 'Liabilities', 'columns' => ['Code', 'Account', 'Balance (UGX)'], 'rows' => $section($data['liabilities']),
                    'footer' => ['', 'Total Liabilities', number_format((float) $data['totals']['liabilities'], 2)]],
                ['title' => 'Equity', 'columns' => ['Code', 'Account', 'Balance (UGX)'], 'rows' => [
                    ...$section($data['equity']),
                    ['', 'Net Surplus (YTD)', number_format((float) $data['totals']['surplus'], 2)],
                ], 'footer' => ['', 'Total Liabilities & Equity', number_format((float) $data['totals']['liabilities_and_equity'], 2)]],
            ],
        ];
    }

    protected function incomeStatementDocument(Request $request, ?string $tenant): array
    {
        $data = $this->reports->incomeStatement(
            $request->date('from') ?? today()->startOfYear(),
            $request->date('to') ?? today(),
        );

        $section = fn (array $rows) => array_map(fn ($r) => [$r['code'], $r['name'], number_format((float) $r['balance'], 2)], $rows);

        return [
            'title' => 'Income Statement',
            'tenant' => $tenant,
            'meta' => "Period {$data['from']} to {$data['to']}",
            'tables' => [
                ['title' => 'Income', 'columns' => ['Code', 'Account', 'Amount (UGX)'], 'rows' => $section($data['income']),
                    'footer' => ['', 'Total Income', number_format((float) $data['totals']['income'], 2)]],
                ['title' => 'Expenses', 'columns' => ['Code', 'Account', 'Amount (UGX)'], 'rows' => $section($data['expenses']),
                    'footer' => ['', 'Total Expenses', number_format((float) $data['totals']['expenses'], 2)]],
                ['title' => 'Result', 'columns' => ['', '', ''], 'rows' => [],
                    'footer' => ['', 'Net Surplus / (Deficit)', number_format((float) $data['totals']['surplus'], 2)]],
            ],
        ];
    }

    protected function parDocument(?string $tenant): array
    {
        $data = $this->reports->parReport();

        return [
            'title' => 'Portfolio at Risk (PAR) / Loan Aging',
            'tenant' => $tenant,
            'meta' => 'As of ' . $data['as_of'] . " — PAR ratio: {$data['totals']['par_ratio']}%",
            'tables' => [[
                'title' => 'Classification buckets',
                'columns' => ['Classification', 'Provision rate', 'Loans', 'Outstanding (UGX)', 'Provision (UGX)'],
                'rows' => array_map(fn ($b) => [
                    ucfirst($b['classification']), $b['provision_rate'], $b['loans'],
                    number_format((float) $b['outstanding'], 2), number_format((float) $b['provision'], 2),
                ], $data['buckets']),
                'footer' => ['Total', '', '', number_format((float) $data['totals']['gross_portfolio'], 2), number_format((float) $data['totals']['total_provision'], 2)],
            ]],
        ];
    }

    protected function savingsSummaryDocument(?string $tenant): array
    {
        $data = $this->reports->savingsSummary();

        return [
            'title' => 'Savings Summary',
            'tenant' => $tenant,
            'meta' => 'As of ' . $data['as_of'],
            'tables' => [[
                'title' => 'Per product',
                'columns' => ['Code', 'Product', 'Accounts', 'Balance (UGX)', 'Blocked (UGX)'],
                'rows' => array_map(fn ($p) => [
                    $p['code'], $p['name'], $p['accounts'],
                    number_format((float) $p['balance'], 2), number_format((float) $p['blocked'], 2),
                ], $data['products']),
                'footer' => ['', 'Total', $data['totals']['accounts'], number_format((float) $data['totals']['balance'], 2), number_format((float) $data['totals']['blocked'], 2)],
            ]],
        ];
    }

    protected function memberRegisterDocument(?string $tenant): array
    {
        $rows = Member::query()->orderBy('member_no')->get()->map(fn (Member $m) => [
            $m->member_no, $m->full_name, ucfirst($m->type), $m->nin ?? '', $m->phone,
            $m->gender ?? '', $m->district ?? '', ucfirst($m->status), $m->joined_at?->format('Y-m-d') ?? '',
        ])->all();

        return [
            'title' => 'Statutory Member Register',
            'tenant' => $tenant,
            'meta' => 'As of ' . today()->toDateString() . ' — ' . count($rows) . ' members',
            'tables' => [[
                'title' => 'Members',
                'columns' => ['Member No', 'Name', 'Type', 'NIN', 'Phone', 'Gender', 'District', 'Status', 'Joined'],
                'rows' => $rows,
            ]],
        ];
    }

    protected function streamCsv(string $report, array $document): StreamedResponse
    {
        return response()->streamDownload(function () use ($document) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [$document['title'] . ($document['tenant'] ? ' — ' . $document['tenant'] : '')]);
            fputcsv($handle, [$document['meta']]);

            foreach ($document['tables'] as $table) {
                fputcsv($handle, []);
                fputcsv($handle, [$table['title']]);
                fputcsv($handle, $table['columns']);
                foreach ($table['rows'] as $row) {
                    fputcsv($handle, $row);
                }
                if (isset($table['footer'])) {
                    fputcsv($handle, $table['footer']);
                }
            }

            fclose($handle);
        }, "{$report}-" . today()->toDateString() . '.csv', ['Content-Type' => 'text/csv']);
    }
}
