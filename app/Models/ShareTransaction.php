<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareTransaction extends Model
{
    protected $fillable = [
        'reference', 'share_account_id', 'type', 'shares', 'amount',
        'shares_after', 'journal_entry_id', 'counterparty_account_id',
        'performed_by', 'value_date', 'memo',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'value_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ShareAccount::class, 'share_account_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(ShareAccount::class, 'counterparty_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
