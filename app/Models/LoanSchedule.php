<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    protected $fillable = [
        'loan_id', 'installment_no', 'due_date', 'principal_due', 'interest_due',
        'fees_due', 'penalties_due', 'principal_paid', 'interest_paid',
        'fees_paid', 'penalties_paid', 'balance_after', 'is_settled', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'fees_due' => 'decimal:2',
            'penalties_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'fees_paid' => 'decimal:2',
            'penalties_paid' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'is_settled' => 'boolean',
            'settled_at' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** Unpaid remainder of a component. */
    public function due(string $component): string
    {
        return bcsub($this->{$component . '_due'}, $this->{$component . '_paid'}, 2);
    }

    /** Total unpaid across all components. */
    public function totalDue(): string
    {
        return bcadd(
            bcadd($this->due('principal'), $this->due('interest'), 2),
            bcadd($this->due('fees'), $this->due('penalties'), 2),
            2,
        );
    }
}
