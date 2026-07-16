<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareProduct extends Model
{
    /** Defaults applied to in-memory instances (mirrors DB defaults). */
    protected $attributes = [
        'is_active' => true,
        'min_shares' => 1,
    ];

    protected $fillable = [
        'code', 'name', 'nominal_value', 'min_shares', 'max_shares',
        'gl_equity_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nominal_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ShareAccount::class);
    }

    public function equityAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_equity_account_id');
    }
}
