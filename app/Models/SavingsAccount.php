<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsAccount extends Model
{
    /** @use HasFactory<\Database\Factories\SavingsAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'account_no', 'member_id', 'savings_product_id', 'balance',
        'blocked_amount', 'status', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'blocked_amount' => 'decimal:2',
            'opened_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class, 'savings_product_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    /** Balance a member can actually withdraw (excludes blocks + product min balance). */
    public function availableBalance(): string
    {
        $afterBlocks = bcsub($this->balance, $this->blocked_amount, 2);
        $available = bcsub($afterBlocks, $this->product->min_balance, 2);

        return bccomp($available, '0.00', 2) === 1 ? $available : '0.00';
    }
}
