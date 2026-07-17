<?php

namespace Tests\Unit;

use App\Services\AmortizationCalculator;
use DomainException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class AmortizationCalculatorTest extends TestCase
{
    private AmortizationCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new AmortizationCalculator();
    }

    public function test_flat_schedule_1m_at_24_percent_12_months(): void
    {
        // 1,000,000 @ 24% p.a. flat, 12 months:
        // monthly rate 2% → total interest = 1,000,000 × 0.02 × 12 = 240,000
        // per installment: principal 83,333.33, interest 20,000
        $rows = $this->calc->schedule('1000000.00', '24', 12, 'flat', Carbon::parse('2026-08-01'));

        $this->assertCount(12, $rows);
        $this->assertSame('83333.33', $rows[0]['principal_due']);
        $this->assertSame('20000.00', $rows[0]['interest_due']);

        // Final row absorbs rounding: 1,000,000 − 11×83,333.33 = 83,333.37
        $this->assertSame('83333.37', $rows[11]['principal_due']);

        // Sums are exact
        $totalPrincipal = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['principal_due'], 2), '0.00');
        $totalInterest = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['interest_due'], 2), '0.00');
        $this->assertSame('1000000.00', $totalPrincipal);
        $this->assertSame('240000.00', $totalInterest);

        // Balance reaches exactly zero
        $this->assertSame('0.00', $rows[11]['balance_after']);
    }

    public function test_reducing_balance_1m_at_24_percent_12_months(): void
    {
        // Annuity for P=1,000,000, r=0.02, n=12: A ≈ 94,559.60
        $rows = $this->calc->schedule('1000000.00', '24', 12, 'reducing_balance', Carbon::parse('2026-08-01'));

        $this->assertCount(12, $rows);

        // First installment: interest = 20,000.00; principal = A − interest.
        // Exact annuity ≈ 94,559.596; bcdiv truncates to 94,559.59 and the
        // final installment absorbs the remainder.
        $this->assertSame('20000.00', $rows[0]['interest_due']);
        $this->assertSame('74559.59', $rows[0]['principal_due']);

        // Principal sums exactly; balance ends at zero
        $totalPrincipal = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['principal_due'], 2), '0.00');
        $this->assertSame('1000000.00', $totalPrincipal);
        $this->assertSame('0.00', $rows[11]['balance_after']);

        // Reducing balance total interest < flat total interest (240,000)
        $totalInterest = array_reduce($rows, fn ($c, $r) => bcadd($c, $r['interest_due'], 2), '0.00');
        $this->assertSame(-1, bccomp($totalInterest, '240000.00', 2));
        // and within the expected annuity ballpark (≈134,715)
        $this->assertGreaterThan(130000, (float) $totalInterest);
        $this->assertLessThan(140000, (float) $totalInterest);
    }

    public function test_interest_decreases_each_month_on_reducing_balance(): void
    {
        $rows = $this->calc->schedule('500000.00', '18', 6, 'reducing_balance', Carbon::parse('2026-08-01'));

        for ($i = 1; $i < count($rows); $i++) {
            $this->assertSame(
                -1,
                bccomp($rows[$i]['interest_due'], $rows[$i - 1]['interest_due'], 2),
                "Interest should strictly decrease (row {$i})",
            );
        }
    }

    public function test_due_dates_are_monthly(): void
    {
        $rows = $this->calc->schedule('100000.00', '12', 3, 'flat', Carbon::parse('2026-01-31'));

        $this->assertSame('2026-01-31', $rows[0]['due_date']);
        $this->assertSame('2026-02-28', $rows[1]['due_date']); // no overflow
        $this->assertSame('2026-03-31', $rows[2]['due_date']);
    }

    public function test_zero_rate_reducing_balance_splits_principal(): void
    {
        $rows = $this->calc->schedule('90000.00', '0', 3, 'reducing_balance', Carbon::parse('2026-08-01'));

        $this->assertSame('30000.00', $rows[0]['principal_due']);
        $this->assertSame('0.00', $rows[0]['interest_due']);
        $this->assertSame('0.00', $rows[2]['balance_after']);
    }

    public function test_rejects_invalid_input(): void
    {
        $this->expectException(DomainException::class);
        $this->calc->schedule('0.00', '24', 12, 'flat', Carbon::parse('2026-08-01'));
    }
}
