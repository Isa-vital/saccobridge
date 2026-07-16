<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareAccount extends Model
{
    protected $fillable = [
        'member_id', 'share_product_id', 'shares_count', 'value', 'status',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShareProduct::class, 'share_product_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ShareTransaction::class);
    }
}
