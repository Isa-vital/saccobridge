<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'parent_id', 'is_system', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Signed balance following normal-balance convention:
     * assets/expenses are debit-normal, the rest credit-normal.
     */
    public function balance(): string
    {
        $sums = $this->lines()
            ->selectRaw('COALESCE(SUM(debit),0) as debits, COALESCE(SUM(credit),0) as credits')
            ->first();

        return $this->isDebitNormal()
            ? bcsub($sums->debits, $sums->credits, 2)
            : bcsub($sums->credits, $sums->debits, 2);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    /** Find a system account by its seeded code (e.g. '1010' teller cash). */
    public static function byCode(string $code): self
    {
        return static::where('code', $code)->firstOrFail();
    }
}
