<?php

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Illuminate\Console\Command;

/**
 * Monthly savings interest accrual (FR-SAV-06).
 *
 * Run per tenant:  php artisan tenants:run savings:post-interest
 * Scheduled monthly in routes/console.php. Chunked so it scales to
 * tens of thousands of accounts in constant memory. Idempotent —
 * one interest posting per account per month.
 */
class PostSavingsInterest extends Command
{
    protected $signature = 'savings:post-interest';

    protected $description = 'Accrue and post monthly interest on all active savings accounts';

    public function handle(SavingsService $savings): int
    {
        $posted = 0;

        SavingsAccount::query()
            ->where('status', 'active')
            ->chunkById(500, function ($accounts) use ($savings, &$posted) {
                foreach ($accounts as $account) {
                    if ($savings->postInterest($account) !== null) {
                        $posted++;
                    }
                }
            });

        $this->info("Interest posted on {$posted} account(s).");

        return self::SUCCESS;
    }
}
