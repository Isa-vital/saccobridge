<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    protected $fillable = [
        'reference', 'loan_id', 'amount', 'principal_portion', 'interest_portion',
        'fees_portion', 'penalties_portion', 'source', 'savings_transaction_id',
        'journal_entry_id', 'teller_session_id', 'performed_by', 'value_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_portion' => 'decimal:2',
            'interest_portion' => 'decimal:2',
            'fees_portion' => 'decimal:2',
            'penalties_portion' => 'decimal:2',
            'value_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
