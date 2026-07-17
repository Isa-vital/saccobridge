<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Console\Command;

/**
 * Daily UMRA loan classification & provisioning (FR-LNS-08).
 * Run per tenant: php artisan tenants:run loans:classify
 */
class ClassifyLoans extends Command
{
    protected $signature = 'loans:classify';

    protected $description = 'Age loans into UMRA classification buckets and adjust provisions';

    public function handle(LoanService $loans): int
    {
        $buckets = [];

        Loan::where('status', 'active')
            ->chunkById(200, function ($chunk) use ($loans, &$buckets) {
                foreach ($chunk as $loan) {
                    $classified = $loans->classify($loan);
                    $buckets[$classified->classification] = ($buckets[$classified->classification] ?? 0) + 1;
                }
            });

        foreach ($buckets as $bucket => $count) {
            $this->line(str_pad($bucket, 12) . $count);
        }

        return self::SUCCESS;
    }
}
