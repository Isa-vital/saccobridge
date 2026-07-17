<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\TellerSession;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Loan lifecycle (FR-LNS-01..11):
 * apply → submit → approve/reject → disburse → repay → close / write off,
 * plus penalties and UMRA classification & provisioning.
 */
class LoanService
{
    /** UMRA aging buckets → provisioning rates (FR-LNS-08). */
    public const CLASSIFICATIONS = [
        ['name' => 'performing',  'max_days' => 0,   'rate' => '0.01'],
        ['name' => 'watch',       'max_days' => 30,  'rate' => '0.05'],
        ['name' => 'substandard', 'max_days' => 60,  'rate' => '0.25'],
        ['name' => 'doubtful',    'max_days' => 90,  'rate' => '0.50'],
        ['name' => 'loss',        'max_days' => PHP_INT_MAX, 'rate' => '1.00'],
    ];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly AmortizationCalculator $amortization,
        private readonly SavingsService $savings,
    ) {
    }

    /**
     * Create + submit a loan application (FR-LNS-02/03).
     *
     * @param array<int, array{member_id:int, savings_account_id:int, guaranteed_amount:string}> $guarantors
     * @param array<int, array{description:string, estimated_value:string}> $collateral
     */
    public function apply(
        Member $member,
        LoanProduct $product,
        string $amount,
        int $termMonths,
        ?string $purpose,
        array $guarantors,
        array $collateral,
        User $officer,
    ): Loan {
        if ($member->status !== 'active') {
            throw new DomainException('Only active members can apply for loans.');
        }

        if (! $product->is_active) {
            throw new DomainException('This loan product is not active.');
        }

        if (bccomp($amount, (string) $product->min_amount, 2) === -1
            || ($product->max_amount !== null && bccomp($amount, (string) $product->max_amount, 2) === 1)) {
            throw new DomainException("Amount must be between {$product->min_amount} and " . ($product->max_amount ?? '∞') . '.');
        }

        if ($termMonths < $product->min_term_months || $termMonths > $product->max_term_months) {
            throw new DomainException("Term must be between {$product->min_term_months} and {$product->max_term_months} months.");
        }

        if ($member->loans()->whereIn('status', ['disbursed', 'active'])->exists()) {
            throw new DomainException('Member already has an active loan.');
        }

        // Savings-multiple rule (FR-LNS-01)
        if ($product->savings_multiple !== null) {
            $totalSavings = (string) SavingsAccount::where('member_id', $member->id)
                ->where('status', 'active')->sum('balance');
            $maxLoan = bcmul($totalSavings, (string) $product->savings_multiple, 2);

            if (bccomp($amount, $maxLoan, 2) === 1) {
                throw new DomainException(
                    "Amount exceeds {$product->savings_multiple}× member savings ({$totalSavings}). Max: {$maxLoan}."
                );
            }
        }

        if (count($guarantors) < $product->required_guarantors) {
            throw new DomainException("This product requires at least {$product->required_guarantors} guarantor(s).");
        }

        return DB::transaction(function () use ($member, $product, $amount, $termMonths, $purpose, $guarantors, $collateral, $officer) {
            $loan = Loan::create([
                'loan_no' => $this->nextLoanNo(),
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'applied_amount' => $amount,
                'applied_term_months' => $termMonths,
                'purpose' => $purpose,
                'status' => 'submitted',
                'created_by' => $officer->id,
                'submitted_at' => now(),
            ]);

            foreach ($guarantors as $guarantor) {
                $this->pledgeGuarantee($loan, $guarantor);
            }

            foreach ($collateral as $item) {
                $loan->collateral()->create([
                    'description' => $item['description'],
                    'estimated_value' => $item['estimated_value'],
                ]);
            }

            return $loan;
        });
    }

    /** Approve a submitted application — maker-checker (FR-LNS-04). */
    public function approve(Loan $loan, User $approver, ?string $amount = null, ?int $termMonths = null): Loan
    {
        if (! in_array($loan->status, ['submitted', 'under_review'], true)) {
            throw new DomainException('Only submitted applications can be approved.');
        }

        if ($loan->created_by !== null && $loan->created_by === $approver->id) {
            throw new DomainException('A loan cannot be approved by the officer who submitted it (maker-checker).');
        }

        $amount ??= (string) $loan->applied_amount;
        $termMonths ??= $loan->applied_term_months;

        if (bccomp($amount, (string) $loan->applied_amount, 2) === 1) {
            throw new DomainException('Approved amount cannot exceed the applied amount.');
        }

        $loan->update([
            'status' => 'approved',
            'approved_amount' => $amount,
            'approved_term_months' => $termMonths,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $loan;
    }

    public function reject(Loan $loan, User $approver, string $reason): Loan
    {
        if (! in_array($loan->status, ['submitted', 'under_review'], true)) {
            throw new DomainException('Only submitted applications can be rejected.');
        }

        DB::transaction(function () use ($loan, $approver, $reason) {
            $this->releaseGuarantees($loan);
            $loan->update([
                'status' => 'rejected',
                'approved_by' => $approver->id,
                'rejection_reason' => $reason,
            ]);
        });

        return $loan;
    }

    /**
     * Disburse an approved loan (FR-LNS-05): generates the amortization
     * schedule and posts GL. Fees are deducted from the payout.
     * GL: Dr portfolio (principal) / Cr cash|savings (net) + Cr fee income (fees).
     */
    public function disburse(Loan $loan, User $disburser, string $method = 'cash', ?CarbonInterface $date = null): Loan
    {
        if ($loan->status !== 'approved') {
            throw new DomainException('Only approved loans can be disbursed.');
        }

        if ($loan->approved_by !== null && $disburser->id === $loan->created_by) {
            // officer who submitted may not also disburse — separation of duties
            throw new DomainException('The submitting officer cannot disburse this loan (maker-checker).');
        }

        $date ??= today();

        return DB::transaction(function () use ($loan, $disburser, $method, $date) {
            $product = $loan->product;
            $principal = (string) $loan->approved_amount;

            // Fees (FR-LNS-01/11)
            $processingFee = bcmul($principal, bcdiv((string) $product->processing_fee_percent, '100', 8), 2);
            $totalFees = bcadd((string) $product->application_fee, $processingFee, 2);
            $netPayout = bcsub($principal, $totalFees, 2);

            if (bccomp($netPayout, '0.00', 2) !== 1) {
                throw new DomainException('Fees exceed the loan principal.');
            }

            // Build schedule
            $firstPayment = $date->copy()->addMonthNoOverflow()->addDays($product->grace_period_days);
            $rows = $this->amortization->schedule(
                $principal,
                (string) $product->interest_rate,
                $loan->approved_term_months,
                $product->interest_method,
                $firstPayment,
            );

            $totalInterest = '0.00';
            foreach ($rows as $row) {
                $loan->schedules()->create($row);
                $totalInterest = bcadd($totalInterest, $row['interest_due'], 2);
            }

            // Payout destination
            $savingsTransactionId = null;
            if ($method === 'savings') {
                $account = SavingsAccount::where('member_id', $loan->member_id)
                    ->where('status', 'active')->orderBy('id')->first();

                if ($account === null) {
                    throw new DomainException('Member has no active savings account for disbursement.');
                }

                $txn = $this->savings->creditLoanDisbursement($account, $netPayout, "Loan disbursement {$loan->loan_no}", $disburser);
                $savingsTransactionId = $txn->id;
                $cashLine = ['account' => $account->product->gl_liability_account_id, 'credit' => $netPayout];
            } else {
                $this->requireOpenSession($disburser);
                $cashLine = ['account' => GlCodes::tellerCash(), 'credit' => $netPayout];
            }

            $lines = [
                ['account' => $product->gl_portfolio_account_id, 'debit' => $principal],
                $cashLine,
            ];
            if (bccomp($totalFees, '0.00', 2) === 1) {
                $lines[] = ['account' => $product->gl_fee_income_account_id, 'credit' => $totalFees];
            }

            $this->transactions->post(
                description: "Loan disbursement {$loan->loan_no} — {$loan->member->member_no}",
                lines: $lines,
                date: $date,
                source: $loan,
                postedBy: $disburser,
            );

            $loan->update([
                'status' => 'active',
                'principal_disbursed' => $principal,
                'principal_outstanding' => $principal,
                'interest_outstanding' => $totalInterest,
                'disbursed_at' => $date->toDateString(),
                'first_payment_date' => $firstPayment->toDateString(),
            ]);

            return $loan;
        });
    }

    /**
     * Post a repayment (FR-LNS-06). Allocation waterfall:
     * penalties → fees → interest → principal, oldest installment first.
     */
    public function repay(Loan $loan, string $amount, User $teller, string $source = 'cash', ?CarbonInterface $date = null): LoanRepayment
    {
        if ($loan->status !== 'active') {
            throw new DomainException('Repayments can only be posted on active loans.');
        }

        if (bccomp($amount, '0.00', 2) !== 1) {
            throw new DomainException('Repayment must be greater than zero.');
        }

        $date ??= today();

        return DB::transaction(function () use ($loan, $amount, $teller, $source, $date) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (bccomp($amount, $loan->totalOutstanding(), 2) === 1) {
                throw new DomainException("Repayment exceeds total outstanding ({$loan->totalOutstanding()}).");
            }

            $session = null;
            $savingsTransactionId = null;

            if ($source === 'savings') {
                $account = SavingsAccount::where('member_id', $loan->member_id)
                    ->where('status', 'active')->orderBy('id')->first();
                if ($account === null) {
                    throw new DomainException('Member has no active savings account.');
                }
                $txn = $this->savings->debitLoanRepayment($account, $amount, "Loan repayment {$loan->loan_no}", $teller);
                $savingsTransactionId = $txn->id;
            } else {
                $session = $this->requireOpenSession($teller);
            }

            // Waterfall: oldest installment first; within an installment
            // penalties → fees → interest → principal (FR-LNS-06)
            $allocated = ['penalties' => '0.00', 'fees' => '0.00', 'interest' => '0.00', 'principal' => '0.00'];
            $remaining = $amount;

            $installments = $loan->schedules()->where('is_settled', false)->orderBy('installment_no')->lockForUpdate()->get();

            foreach ($installments as $installment) {
                foreach (['penalties', 'fees', 'interest', 'principal'] as $component) {
                    if (bccomp($remaining, '0.00', 2) !== 1) {
                        break 2;
                    }

                    $due = $installment->due($component);
                    if (bccomp($due, '0.00', 2) !== 1) {
                        continue;
                    }

                    $portion = bccomp($remaining, $due, 2) === -1 ? $remaining : $due;
                    $installment->{$component . '_paid'} = bcadd($installment->{$component . '_paid'}, $portion, 2);
                    $installment->save();

                    $allocated[$component] = bcadd($allocated[$component], $portion, 2);
                    $remaining = bcsub($remaining, $portion, 2);
                }
            }

            // Settle fully-paid installments
            foreach ($installments as $installment) {
                if (! $installment->is_settled && bccomp($installment->fresh()->totalDue(), '0.00', 2) !== 1) {
                    $installment->update(['is_settled' => true, 'settled_at' => $date->toDateString()]);
                }
            }

            // Update loan outstanding totals
            $loan->update([
                'principal_outstanding' => bcsub($loan->principal_outstanding, $allocated['principal'], 2),
                'interest_outstanding' => bcsub($loan->interest_outstanding, $allocated['interest'], 2),
                'fees_outstanding' => bcsub($loan->fees_outstanding, $allocated['fees'], 2),
                'penalties_outstanding' => bcsub($loan->penalties_outstanding, $allocated['penalties'], 2),
            ]);

            $repayment = LoanRepayment::create([
                'reference' => $this->nextRepaymentNo(),
                'loan_id' => $loan->id,
                'amount' => $amount,
                'principal_portion' => $allocated['principal'],
                'interest_portion' => $allocated['interest'],
                'fees_portion' => $allocated['fees'],
                'penalties_portion' => $allocated['penalties'],
                'source' => $source,
                'savings_transaction_id' => $savingsTransactionId,
                'teller_session_id' => $session?->id,
                'performed_by' => $teller->id,
                'value_date' => $date->toDateString(),
            ]);

            // GL posting
            $product = $loan->product;
            $debitAccount = $source === 'savings'
                ? SavingsAccount::where('member_id', $loan->member_id)->where('status', 'active')->orderBy('id')->first()->product->gl_liability_account_id
                : GlCodes::tellerCash();

            $lines = [['account' => $debitAccount, 'debit' => $amount]];
            if (bccomp($allocated['principal'], '0.00', 2) === 1) {
                $lines[] = ['account' => $product->gl_portfolio_account_id, 'credit' => $allocated['principal']];
            }
            if (bccomp($allocated['interest'], '0.00', 2) === 1) {
                $lines[] = ['account' => $product->gl_interest_income_account_id, 'credit' => $allocated['interest']];
            }
            if (bccomp($allocated['fees'], '0.00', 2) === 1) {
                $lines[] = ['account' => $product->gl_fee_income_account_id, 'credit' => $allocated['fees']];
            }
            if (bccomp($allocated['penalties'], '0.00', 2) === 1) {
                $lines[] = ['account' => $product->gl_penalty_income_account_id, 'credit' => $allocated['penalties']];
            }

            $entry = $this->transactions->post(
                description: "Loan repayment {$repayment->reference} — {$loan->loan_no}",
                lines: $lines,
                date: $date,
                source: $repayment,
                postedBy: $teller,
            );

            $repayment->update(['journal_entry_id' => $entry->id]);

            // Close the loan when everything is settled
            $loan->refresh();
            if (bccomp($loan->totalOutstanding(), '0.00', 2) !== 1) {
                $this->releaseGuarantees($loan);
                $loan->update(['status' => 'closed', 'closed_at' => $date->toDateString(), 'classification' => 'performing', 'days_in_arrears' => 0]);
            }

            return $repayment->refresh();
        });
    }

    /**
     * Apply monthly penalties on overdue installments (FR-LNS-07).
     * penalty = product.penalty_rate% × overdue amount. Idempotent per month
     * via the penalties_due increase being computed from a marker date.
     */
    public function applyPenalty(Loan $loan, ?CarbonInterface $asOf = null): string
    {
        $asOf ??= today();

        return DB::transaction(function () use ($loan, $asOf) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $product = $loan->product;

            if ($loan->status !== 'active' || bccomp((string) $product->penalty_rate, '0', 6) !== 1) {
                return '0.00';
            }

            $totalPenalty = '0.00';

            $overdue = $loan->schedules()
                ->where('is_settled', false)
                ->where('due_date', '<', $asOf->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($overdue as $installment) {
                // One penalty application per installment per month
                $marker = 'penalty_' . $installment->id . '_' . $asOf->format('Y-m');
                $exists = DB::table('settings')->where('key', $marker)->exists();
                if ($exists) {
                    continue;
                }

                $overdueAmount = bcadd($installment->due('principal'), $installment->due('interest'), 2);
                if (bccomp($overdueAmount, '0.00', 2) !== 1) {
                    continue;
                }

                $penalty = bcmul($overdueAmount, bcdiv((string) $product->penalty_rate, '100', 8), 2);
                if (bccomp($penalty, '0.00', 2) !== 1) {
                    continue;
                }

                $installment->update(['penalties_due' => bcadd($installment->penalties_due, $penalty, 2)]);
                DB::table('settings')->insert([
                    'key' => $marker, 'value' => json_encode($penalty),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $totalPenalty = bcadd($totalPenalty, $penalty, 2);
            }

            if (bccomp($totalPenalty, '0.00', 2) === 1) {
                $loan->update(['penalties_outstanding' => bcadd($loan->penalties_outstanding, $totalPenalty, 2)]);
            }

            return $totalPenalty;
        });
    }

    /**
     * UMRA classification & provisioning (FR-LNS-08). Ages the loan by its
     * oldest unsettled overdue installment and adjusts the GL provision.
     */
    public function classify(Loan $loan, ?CarbonInterface $asOf = null): Loan
    {
        $asOf ??= today();

        return DB::transaction(function () use ($loan, $asOf) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($loan->status, ['active'], true)) {
                return $loan;
            }

            $oldestOverdue = $loan->schedules()
                ->where('is_settled', false)
                ->where('due_date', '<', $asOf->toDateString())
                ->orderBy('due_date')
                ->first();

            $days = $oldestOverdue ? $oldestOverdue->due_date->diffInDays($asOf) : 0;

            $classification = 'performing';
            $rate = '0.01';
            foreach (self::CLASSIFICATIONS as $bucket) {
                if ($days <= $bucket['max_days']) {
                    $classification = $bucket['name'];
                    $rate = $bucket['rate'];
                    break;
                }
            }

            $requiredProvision = bcmul($loan->principal_outstanding, $rate, 2);
            $delta = bcsub($requiredProvision, (string) $loan->provision_amount, 2);

            // Post only the CHANGE in provision: Dr 5020 expense / Cr 1190 allowance (or reverse)
            if (bccomp($delta, '0.00', 2) !== 0) {
                $lines = bccomp($delta, '0.00', 2) === 1
                    ? [
                        ['account' => GlCodes::id('5020'), 'debit' => $delta],
                        ['account' => GlCodes::id('1190'), 'credit' => $delta],
                    ]
                    : [
                        ['account' => GlCodes::id('1190'), 'debit' => bcmul($delta, '-1', 2)],
                        ['account' => GlCodes::id('5020'), 'credit' => bcmul($delta, '-1', 2)],
                    ];

                $this->transactions->post(
                    description: "Provision adjustment {$loan->loan_no} → {$classification} ({$days} days)",
                    lines: $lines,
                    date: $asOf,
                    source: $loan,
                );
            }

            $loan->update([
                'classification' => $classification,
                'days_in_arrears' => (int) $days,
                'provision_amount' => $requiredProvision,
            ]);

            return $loan;
        });
    }

    /** Early-settlement payoff quote (FR-LNS-10): outstanding principal + accrued dues, unearned interest waived. */
    public function payoffQuote(Loan $loan, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= today();

        // Due (past or current) unsettled components + all outstanding principal
        $accrued = ['interest' => '0.00', 'fees' => '0.00', 'penalties' => '0.00'];

        foreach ($loan->schedules()->where('is_settled', false)->get() as $installment) {
            $isDue = $installment->due_date->lte($asOf);
            if ($isDue) {
                $accrued['interest'] = bcadd($accrued['interest'], $installment->due('interest'), 2);
            }
            $accrued['fees'] = bcadd($accrued['fees'], $installment->due('fees'), 2);
            $accrued['penalties'] = bcadd($accrued['penalties'], $installment->due('penalties'), 2);
        }

        $total = bcadd(
            bcadd($loan->principal_outstanding, $accrued['interest'], 2),
            bcadd($accrued['fees'], $accrued['penalties'], 2),
            2,
        );

        return [
            'principal' => (string) $loan->principal_outstanding,
            'interest_due' => $accrued['interest'],
            'fees_due' => $accrued['fees'],
            'penalties_due' => $accrued['penalties'],
            'waived_future_interest' => bcsub($loan->interest_outstanding, $accrued['interest'], 2),
            'total' => $total,
            'as_of' => $asOf->toDateString(),
        ];
    }

    /** Total cost of credit disclosure (FR-LNS-11). */
    public function costOfCredit(LoanProduct $product, string $amount, int $termMonths): array
    {
        $processingFee = bcmul($amount, bcdiv((string) $product->processing_fee_percent, '100', 8), 2);
        $totalFees = bcadd((string) $product->application_fee, $processingFee, 2);
        $totalInterest = $this->amortization->totalInterest($amount, (string) $product->interest_rate, $termMonths, $product->interest_method);
        $totalCost = bcadd($totalFees, $totalInterest, 2);

        return [
            'principal' => $amount,
            'total_interest' => $totalInterest,
            'total_fees' => $totalFees,
            'total_cost' => $totalCost,
            'total_repayable' => bcadd($amount, $totalInterest, 2),
            'net_disbursed' => bcsub($amount, $totalFees, 2),
            'interest_method' => $product->interest_method,
            'annual_rate' => (string) $product->interest_rate,
        ];
    }

    // ── internals ──────────────────────────────────────────────────────────

    protected function pledgeGuarantee(Loan $loan, array $guarantor): void
    {
        $account = SavingsAccount::whereKey($guarantor['savings_account_id'])->lockForUpdate()->firstOrFail();

        if ((int) $account->member_id !== (int) $guarantor['member_id']) {
            throw new DomainException('Guarantor savings account does not belong to the guarantor.');
        }

        $available = bcsub($account->balance, $account->blocked_amount, 2);
        if (bccomp((string) $guarantor['guaranteed_amount'], $available, 2) === 1) {
            throw new DomainException("Guarantor {$account->member->full_name} has insufficient free savings ({$available}).");
        }

        $account->update(['blocked_amount' => bcadd($account->blocked_amount, (string) $guarantor['guaranteed_amount'], 2)]);

        $loan->guarantors()->create([
            'member_id' => $guarantor['member_id'],
            'savings_account_id' => $account->id,
            'guaranteed_amount' => $guarantor['guaranteed_amount'],
            'status' => 'pledged',
        ]);
    }

    protected function releaseGuarantees(Loan $loan): void
    {
        foreach ($loan->guarantors()->where('status', 'pledged')->get() as $guarantee) {
            if ($guarantee->savings_account_id) {
                $account = SavingsAccount::whereKey($guarantee->savings_account_id)->lockForUpdate()->first();
                $account?->update(['blocked_amount' => bcsub($account->blocked_amount, (string) $guarantee->guaranteed_amount, 2)]);
            }
            $guarantee->update(['status' => 'released']);
        }

        $loan->collateral()->where('status', 'held')->update(['status' => 'released']);
    }

    protected function requireOpenSession(User $teller): TellerSession
    {
        $session = TellerSession::openFor($teller);

        if ($session === null) {
            throw new DomainException('You need an open teller session before handling loan cash.');
        }

        return $session;
    }

    protected function nextLoanNo(): string
    {
        return $this->nextSequence('loan_sequence', 'LN', 5);
    }

    protected function nextRepaymentNo(): string
    {
        return $this->nextSequence('loan_repayment_sequence', 'LRP', 6);
    }

    protected function nextSequence(string $key, string $prefix, int $pad): string
    {
        $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();

        if ($row === null) {
            DB::table('settings')->insert([
                'key' => $key, 'value' => json_encode(1),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $sequence = 1;
        } else {
            $sequence = (int) json_decode($row->value) + 1;
            DB::table('settings')->where('key', $key)
                ->update(['value' => json_encode($sequence), 'updated_at' => now()]);
        }

        return sprintf('%s-%0' . $pad . 'd', $prefix, $sequence);
    }
}
