<?php

namespace App\Services;

use App\Models\GlAccount;

/**
 * Fast access to seeded system GL accounts by their stable codes
 * (see ChartOfAccountsSeeder). Resolved once per request per code.
 */
class GlCodes
{
    /** @var array<string, int> */
    protected static array $cache = [];

    public static function vault(): int
    {
        return static::id('1010');
    }

    public static function tellerCash(): int
    {
        return static::id('1020');
    }

    public static function bank(): int
    {
        return static::id('1030');
    }

    public static function loanPortfolio(): int
    {
        return static::id('1100');
    }

    public static function memberSavings(): int
    {
        return static::id('2010');
    }

    public static function shareCapital(): int
    {
        return static::id('3010');
    }

    public static function feeIncome(): int
    {
        return static::id('4020');
    }

    public static function membershipFees(): int
    {
        return static::id('4040');
    }

    public static function savingsInterestExpense(): int
    {
        return static::id('5010');
    }

    public static function id(string $code): int
    {
        return static::$cache[$code] ??= GlAccount::byCode($code)->id;
    }

    /** Reset cache (needed between tenants/tests). */
    public static function flush(): void
    {
        static::$cache = [];
    }
}
