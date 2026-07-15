<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPeriod extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'status', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get (or lazily create) the OPEN period covering the given date.
     * Periods are calendar months. Throws if the covering period is closed.
     */
    public static function openFor(CarbonInterface $date): self
    {
        // NB: query with a Carbon instance so the value matches the stored
        // datetime format ('Y-m-d 00:00:00') produced by the date cast.
        $period = static::firstOrCreate(
            ['starts_on' => $date->copy()->startOfMonth()],
            [
                'name' => $date->format('Y-m'),
                'ends_on' => $date->copy()->endOfMonth(),
            ],
        );

        if ($period->status === 'closed') {
            throw new \DomainException("Financial period {$period->name} is closed — cannot post to it.");
        }

        return $period;
    }
}
