<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The double-entry engine. EVERY financial operation in SaccoBridge posts
 * through here — never write journal_entries/journal_lines directly.
 *
 * Invariants enforced:
 *  - Σ debits == Σ credits (to the cent)
 *  - every line has exactly one non-zero side, positive
 *  - the covering financial period is open
 *  - corrections are reversing entries; posted entries are never mutated
 */
class TransactionService
{
    /**
     * Post a balanced journal entry atomically.
     *
     * @param array<int, array{account: \App\Models\GlAccount|int, debit?: string|float|int, credit?: string|float|int, memo?: string}> $lines
     */
    public function post(
        string $description,
        array $lines,
        ?CarbonInterface $date = null,
        ?Model $source = null,
        ?User $postedBy = null,
    ): JournalEntry {
        $date ??= today();

        $normalized = $this->validateLines($lines);

        return DB::transaction(function () use ($description, $normalized, $date, $source, $postedBy) {
            $period = FinancialPeriod::openFor($date);

            $entry = JournalEntry::create([
                'reference' => $this->nextReference(),
                'entry_date' => $date->toDateString(),
                'financial_period_id' => $period->id,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'status' => 'posted',
                'posted_by' => $postedBy?->id,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }

    /**
     * Reverse a posted entry with a mirror-image entry (audit-safe correction).
     */
    public function reverse(JournalEntry $entry, string $reason, ?User $postedBy = null): JournalEntry
    {
        if ($entry->status === 'reversed') {
            throw new DomainException("Entry {$entry->reference} has already been reversed.");
        }

        return DB::transaction(function () use ($entry, $reason, $postedBy) {
            $lines = $entry->lines->map(fn($line) => [
                'gl_account_id' => $line->gl_account_id,
                'debit' => $line->credit,   // mirror
                'credit' => $line->debit,   // mirror
                'memo' => $line->memo,
            ])->all();

            $period = FinancialPeriod::openFor(today());

            $reversal = JournalEntry::create([
                'reference' => $this->nextReference(),
                'entry_date' => today()->toDateString(),
                'financial_period_id' => $period->id,
                'description' => "Reversal of {$entry->reference}: {$reason}",
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'status' => 'posted',
                'reversal_of_id' => $entry->id,
                'posted_by' => $postedBy?->id,
            ]);

            foreach ($lines as $line) {
                $reversal->lines()->create($line);
            }

            $entry->update(['status' => 'reversed']);

            return $reversal;
        });
    }

    /**
     * Validate & normalize lines. Returns rows ready for journal_lines insert.
     *
     * @return array<int, array{gl_account_id: int, debit: string, credit: string, memo: ?string}>
     */
    protected function validateLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw new DomainException('A journal entry requires at least two lines.');
        }

        $normalized = [];
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($lines as $line) {
            $debit = number_format((float) ($line['debit'] ?? 0), 2, '.', '');
            $credit = number_format((float) ($line['credit'] ?? 0), 2, '.', '');

            if (bccomp($debit, '0.00', 2) < 0 || bccomp($credit, '0.00', 2) < 0) {
                throw new DomainException('Journal line amounts must be positive.');
            }

            $hasDebit = bccomp($debit, '0.00', 2) === 1;
            $hasCredit = bccomp($credit, '0.00', 2) === 1;

            if ($hasDebit === $hasCredit) { // both set or both zero
                throw new DomainException('Each journal line must have exactly one of debit or credit.');
            }

            $accountId = is_object($line['account'] ?? null) ? $line['account']->id : ($line['account'] ?? null);

            if (! $accountId) {
                throw new DomainException('Each journal line requires a GL account.');
            }

            $totalDebit = bcadd($totalDebit, $debit, 2);
            $totalCredit = bcadd($totalCredit, $credit, 2);

            $normalized[] = [
                'gl_account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new DomainException(
                "Journal entry is not balanced: debits {$totalDebit} != credits {$totalCredit}."
            );
        }

        return $normalized;
    }

    /** Sequential reference JE-000001, safe under concurrency (row lock on max). */
    protected function nextReference(): string
    {
        $last = DB::table('journal_entries')->lockForUpdate()->max('id') ?? 0;

        return sprintf('JE-%06d', $last + 1);
    }
}
