<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCollateral extends Model
{
    protected $table = 'loan_collateral';

    protected $fillable = [
        'loan_id', 'description', 'estimated_value', 'document_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
