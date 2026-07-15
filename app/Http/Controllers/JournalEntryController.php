<?php

namespace App\Http\Controllers;

use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function index(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->with('poster')
            ->when($request->filled('search'), fn($q) => $q->where(fn($qq) => $qq
                ->where('reference', 'like', '%' . $request->string('search') . '%')
                ->orWhere('description', 'like', '%' . $request->string('search') . '%')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn(JournalEntry $entry) => [
                'id' => $entry->id,
                'reference' => $entry->reference,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'status' => $entry->status,
                'posted_by' => $entry->poster?->name,
                'is_reversal' => $entry->reversal_of_id !== null,
            ]);

        return Inertia::render('gl/Journal', [
            'entries' => $entries,
            'filters' => $request->only(['search']),
            'can' => ['post' => $request->user()->can('gl.post')],
        ]);
    }

    public function create(): Response
    {
        $accounts = GlAccount::where('is_active', true)
            ->whereNotNull('parent_id') // only postable (leaf) accounts
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('gl/JournalCreate', ['accounts' => $accounts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:gl_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->transactions->post(
                description: $data['description'],
                lines: collect($data['lines'])->map(fn($line) => [
                    'account' => (int) $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ])->all(),
                date: Carbon::parse($data['entry_date']),
                postedBy: $request->user(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('gl.journal.show', $entry)
            ->with('success', "Journal entry {$entry->reference} posted.");
    }

    public function show(JournalEntry $journal): Response
    {
        $journal->load(['lines.account', 'poster', 'reversalOf']);

        return Inertia::render('gl/JournalShow', [
            'entry' => [
                'id' => $journal->id,
                'reference' => $journal->reference,
                'entry_date' => $journal->entry_date->format('Y-m-d'),
                'description' => $journal->description,
                'status' => $journal->status,
                'posted_by' => $journal->poster?->name,
                'reversal_of' => $journal->reversalOf?->reference,
                'lines' => $journal->lines->map(fn($line) => [
                    'id' => $line->id,
                    'account' => $line->account->code . ' — ' . $line->account->name,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'memo' => $line->memo,
                ]),
            ],
            'can' => ['reverse' => request()->user()->can('gl.post')],
        ]);
    }

    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reversal = $this->transactions->reverse($journal, $data['reason'], $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('gl.journal.show', $reversal)
            ->with('success', "Entry {$journal->reference} reversed as {$reversal->reference}.");
    }
}
