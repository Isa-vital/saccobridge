<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    /** Defaults applied to in-memory instances (mirrors DB defaults). */
    protected $attributes = [
        'is_active' => true,
        'grace_period_days' => 0,
        'application_fee' => 0,
        'processing_fee_percent' => 0,
        'penalty_rate' => 0,
        'required_guarantors' => 0,
        'min_term_months' => 1,
        'max_term_months' => 60,
        'min_amount' => 0,
    ];

    protected $fillable = [
        'code', 'name', 'interest_rate', 'interest_method',
        'min_term_months', 'max_term_months', 'min_amount', 'max_amount',
        'grace_period_days', 'application_fee', 'processing_fee_percent',
        'penalty_rate', 'required_guarantors', 'savings_multiple',
        'gl_portfolio_account_id', 'gl_interest_income_account_id',
        'gl_fee_income_account_id', 'gl_penalty_income_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:6',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'application_fee' => 'decimal:2',
            'processing_fee_percent' => 'decimal:6',
            'penalty_rate' => 'decimal:6',
            'savings_multiple' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function portfolioAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_portfolio_account_id');
    }
}
