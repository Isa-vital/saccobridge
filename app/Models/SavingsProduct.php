<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsProduct extends Model
{
    /** Defaults applied to in-memory instances (mirrors DB defaults). */
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'code', 'name', 'interest_rate', 'interest_basis', 'interest_posting',
        'min_opening_deposit', 'min_balance', 'withdrawal_fee',
        'max_withdrawals_per_month', 'gl_liability_account_id',
        'gl_interest_expense_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:6',
            'min_opening_deposit' => 'decimal:2',
            'min_balance' => 'decimal:2',
            'withdrawal_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_liability_account_id');
    }

    public function interestExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_interest_expense_account_id');
    }
}
