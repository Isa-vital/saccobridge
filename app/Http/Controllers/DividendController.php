<?php

namespace App\Http\Controllers;

use App\Models\Dividend;
use App\Models\ShareProduct;
use App\Services\ShareService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DividendController extends Controller
{
    public function __construct(private readonly ShareService $shares)
    {
    }

    public function index(Request $request): Response
    {
        $dividends = Dividend::query()
            ->with(['product:id,code,name', 'declarer:id,name', 'approver:id,name'])
            ->withCount('payouts')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Dividend $dividend) => [
                'id' => $dividend->id,
                'financial_year' => $dividend->financial_year,
                'product' => $dividend->product->code,
                'rate' => $dividend->rate,
                'total_declared' => $dividend->total_declared,
                'status' => $dividend->status,
                'declared_by' => $dividend->declarer->name,
                'declared_by_id' => $dividend->declared_by,
                'approved_by' => $dividend->approver?->name,
                'payouts_count' => $dividend->payouts_count,
                'distributed_at' => $dividend->distributed_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('shares/Dividends', [
            'dividends' => $dividends,
            'products' => ShareProduct::where('is_active', true)->get(['id', 'code', 'name']),
            'can' => ['declare' => $request->user()->can('dividends.declare')],
        ]);
    }

    public function declare(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('dividends.declare'), 403);

        $data = $request->validate([
            'financial_year' => ['required', 'string', 'max:9'],
            'share_product_id' => ['required', 'exists:share_products,id'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        try {
            $dividend = $this->shares->declareDividend(
                $data['financial_year'],
                ShareProduct::findOrFail($data['share_product_id']),
                (string) $data['rate'],
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return back()->with('error', 'A dividend for that year and product already exists.');
        }

        return back()->with('success', "Dividend FY{$dividend->financial_year} declared: {$dividend->total_declared} total. Awaiting approval.");
    }

    public function approve(Request $request, Dividend $dividend): RedirectResponse
    {
        abort_unless($request->user()->can('dividends.declare'), 403);

        try {
            $this->shares->approveDividend($dividend, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Dividend FY{$dividend->financial_year} approved. Ready to distribute.");
    }

    public function distribute(Request $request, Dividend $dividend): RedirectResponse
    {
        abort_unless($request->user()->can('dividends.declare'), 403);

        try {
            $paid = $this->shares->distributeDividend($dividend, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Dividend distributed to {$paid} shareholder(s).");
    }
}
