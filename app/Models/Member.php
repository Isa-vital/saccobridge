<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// CHANGED: activitylog v5 moved these classes
// use Spatie\Activitylog\LogOptions;
// use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_no', 'type', 'first_name', 'last_name', 'nin', 'date_of_birth',
        'gender', 'phone', 'email', 'district', 'subcounty', 'village',
        'occupation', 'photo_path', 'signature_path', 'status',
        'created_by', 'approved_by', 'approved_at', 'joined_at', 'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'approved_at' => 'datetime',
            'joined_at' => 'date',
            'exited_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'first_name', 'last_name', 'nin', 'phone'])
            ->logOnlyDirty()
            ->useLogName('member');
    }

    public function nextOfKin(): HasMany
    {
        return $this->hasMany(NextOfKin::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Full display name (groups/institutions keep their name in first_name). */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    /** Search by name, member no, NIN or phone (FR-MEM-08). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('member_no', 'like', "%{$term}%")
                ->orWhere('nin', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
