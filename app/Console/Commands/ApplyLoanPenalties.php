<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Console\Command;

/**
 * Daily penalty application on overdue installments (FR-LNS-07).
 * Run per tenant: php artisan tenants:run loans:apply-penalties
 * Chunked + idempotent (one penalty per installment per month).
 */
class ApplyLoanPenalties extends Command
{
    protected $signature = 'loans:apply-penalties';

    protected $description = 'Apply penalties to overdue loan installments';

    public function handle(LoanService $loans): int
    {
        $count = 0;

        Loan::where('status', 'active')
            ->chunkById(200, function ($chunk) use ($loans, &$count) {
                foreach ($chunk as $loan) {
                    if (bccomp($loans->applyPenalty($loan), '0.00', 2) === 1) {
                        $count++;
                    }
                }
            });

        $this->info("Penalties applied on {$count} loan(s).");

        return self::SUCCESS;
    }
}
