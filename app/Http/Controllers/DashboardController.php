<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $memberStats = Member::query()
            ->selectRaw("count(*) as total")
            ->selectRaw("sum(case when status = 'active' then 1 else 0 end) as active")
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending")
            ->first();

        $recentMembers = Member::query()
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn(Member $member) => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'full_name' => $member->full_name,
                'status' => $member->status,
                'created_at' => $member->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'members_total' => (int) $memberStats->total,
                'members_active' => (int) $memberStats->active,
                'members_pending' => (int) $memberStats->pending,
                // CHANGED: savings now live (sum of account balances); loans in Phase 6
                'savings_balance' => (float) \App\Models\SavingsAccount::where('status', '!=', 'closed')->sum('balance'),
                'loans_outstanding' => (float) \App\Models\Loan::where('status', 'active')->sum('principal_outstanding'),
            ],
            'recentMembers' => $recentMembers,
        ]);
    }
}
