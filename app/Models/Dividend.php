<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dividend extends Model
{
    protected $fillable = [
        'financial_year', 'share_product_id', 'rate', 'total_declared',
        'status', 'declared_by', 'approved_by', 'approved_at', 'distributed_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'total_declared' => 'decimal:2',
            'approved_at' => 'datetime',
            'distributed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShareProduct::class, 'share_product_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(DividendPayout::class);
    }

    public function declarer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
