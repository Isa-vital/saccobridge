<?php

namespace App\Services;

use Carbon\CarbonInterface;
use DomainException;

/**
 * Pure amortization calculator (FR-LNS-05). No side effects — returns
 * schedule rows as arrays. All math in bcmath (scale 2 for money).
 *
 * Methods:
 *  - flat: interest = P × monthly_rate × n, split evenly per installment
 *  - reducing_balance: annuity A = P·r(1+r)^n / ((1+r)^n − 1)
 *
 * The final installment absorbs rounding remainders so that
 * Σ principal == P exactly and Σ interest == computed total exactly.
 */
class AmortizationCalculator
{
    /**
     * @return array<int, array{installment_no:int, due_date:string, principal_due:string, interest_due:string, balance_after:string}>
     */
    public function schedule(
        string $principal,
        string $annualRatePercent,
        int $termMonths,
        string $method,
        CarbonInterface $firstPaymentDate,
    ): array {
        if (bccomp($principal, '0.00', 2) !== 1) {
            throw new DomainException('Principal must be greater than zero.');
        }
        if ($termMonths < 1) {
            throw new DomainException('Term must be at least 1 month.');
        }

        $monthlyRate = bcdiv($annualRatePercent, '1200', 12); // % annual → monthly fraction

        return match ($method) {
            'flat' => $this->flat($principal, $monthlyRate, $termMonths, $firstPaymentDate),
            'reducing_balance' => $this->reducingBalance($principal, $monthlyRate, $termMonths, $firstPaymentDate),
            default => throw new DomainException("Unknown interest method [{$method}]."),
        };
    }

    /** Total interest over the life of the loan. */
    public function totalInterest(string $principal, string $annualRatePercent, int $termMonths, string $method): string
    {
        $rows = $this->schedule($principal, $annualRatePercent, $termMonths, $method, today());

        return array_reduce($rows, fn ($carry, $row) => bcadd($carry, $row['interest_due'], 2), '0.00');
    }

    protected function flat(string $principal, string $monthlyRate, int $n, CarbonInterface $start): array
    {
        $totalInterest = bcmul(bcmul($principal, $monthlyRate, 12), (string) $n, 2);

        $principalPer = bcdiv($principal, (string) $n, 2);
        $interestPer = bcdiv($totalInterest, (string) $n, 2);

        $rows = [];
        $remaining = $principal;
        $interestRemaining = $totalInterest;

        for ($i = 1; $i <= $n; $i++) {
            $isLast = $i === $n;
            // Last installment absorbs rounding remainders
            $principalDue = $isLast ? $remaining : $principalPer;
            $interestDue = $isLast ? $interestRemaining : $interestPer;

            $remaining = bcsub($remaining, $principalDue, 2);
            $interestRemaining = bcsub($interestRemaining, $interestDue, 2);

            $rows[] = [
                'installment_no' => $i,
                'due_date' => $start->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                'principal_due' => $principalDue,
                'interest_due' => $interestDue,
                'balance_after' => $remaining,
            ];
        }

        return $rows;
    }

    protected function reducingBalance(string $principal, string $monthlyRate, int $n, CarbonInterface $start): array
    {
        // Zero-rate edge case: straight principal split
        if (bccomp($monthlyRate, '0', 12) === 0) {
            return $this->flat($principal, '0', $n, $start);
        }

        // annuity = P * r * (1+r)^n / ((1+r)^n - 1)
        $onePlusR = bcadd('1', $monthlyRate, 12);
        $factor = $this->bcpow($onePlusR, $n, 12);
        $annuity = bcdiv(
            bcmul($principal, bcmul($monthlyRate, $factor, 12), 12),
            bcsub($factor, '1', 12),
            2,
        );

        $rows = [];
        $remaining = $principal;

        for ($i = 1; $i <= $n; $i++) {
            $isLast = $i === $n;

            $interestDue = bcmul($remaining, $monthlyRate, 2);

            if ($isLast) {
                // Final installment: pay off exactly what remains
                $principalDue = $remaining;
            } else {
                $principalDue = bcsub($annuity, $interestDue, 2);
                if (bccomp($principalDue, $remaining, 2) === 1) {
                    $principalDue = $remaining;
                }
            }

            $remaining = bcsub($remaining, $principalDue, 2);

            $rows[] = [
                'installment_no' => $i,
                'due_date' => $start->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                'principal_due' => $principalDue,
                'interest_due' => $interestDue,
                'balance_after' => $remaining,
            ];
        }

        return $rows;
    }

    /** Integer-exponent bcpow with controlled scale. */
    protected function bcpow(string $base, int $exp, int $scale): string
    {
        $result = '1';
        for ($i = 0; $i < $exp; $i++) {
            $result = bcmul($result, $base, $scale);
        }

        return $result;
    }
}
