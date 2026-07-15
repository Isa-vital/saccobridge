<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialPeriodController extends Controller
{
    public function index(): Response
    {
        $periods = FinancialPeriod::query()
            ->with('closer')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn(FinancialPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->format('Y-m-d'),
                'ends_on' => $period->ends_on->format('Y-m-d'),
                'status' => $period->status,
                'closed_by' => $period->closer?->name,
                'closed_at' => $period->closed_at?->format('Y-m-d H:i'),
                'entries' => JournalEntry::where('financial_period_id', $period->id)->count(),
            ]);

        return Inertia::render('gl/Periods', [
            'periods' => $periods,
            'can' => ['close' => request()->user()->can('gl.close_period')],
        ]);
    }

    public function close(Request $request, FinancialPeriod $period): RedirectResponse
    {
        if ($period->status === 'closed') {
            return back()->with('error', "Period {$period->name} is already closed.");
        }

        $period->update([
            'status' => 'closed',
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return back()->with('success', "Period {$period->name} closed.");
    }

    public function reopen(Request $request, FinancialPeriod $period): RedirectResponse
    {
        if ($period->status === 'open') {
            return back()->with('error', "Period {$period->name} is already open.");
        }

        $period->update(['status' => 'open', 'closed_by' => null, 'closed_at' => null]);

        return back()->with('success', "Period {$period->name} reopened.");
    }
}
