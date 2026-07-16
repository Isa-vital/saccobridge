<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TellerSession extends Model
{
    protected $fillable = [
        'user_id',
        'opening_float',
        'closing_declared',
        'closing_system',
        'variance',
        'status',
        'opened_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'closing_declared' => 'decimal:2',
            'closing_system' => 'decimal:2',
            'variance' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    /** The currently open session for a teller, if any. */
    public static function openFor(User $teller): ?self
    {
        return static::where('user_id', $teller->id)->where('status', 'open')->first();
    }

    /** Expected cash in drawer: float + cash deposits - cash withdrawals. */
    public function expectedCash(): string
    {
        $in = (string) $this->transactions()
            ->where('status', 'completed')
            ->whereIn('type', ['deposit'])
            ->sum('amount');

        $out = (string) $this->transactions()
            ->where('status', 'completed')
            ->whereIn('type', ['withdrawal'])
            ->sum('amount');

        return bcsub(bcadd($this->opening_float, $in, 2), $out, 2);
    }
}
