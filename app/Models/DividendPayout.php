<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DividendPayout extends Model
{
    protected $fillable = [
        'dividend_id', 'share_account_id', 'shares_held', 'amount',
        'method', 'savings_transaction_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function dividend(): BelongsTo
    {
        return $this->belongsTo(Dividend::class);
    }

    public function shareAccount(): BelongsTo
    {
        return $this->belongsTo(ShareAccount::class);
    }
}
