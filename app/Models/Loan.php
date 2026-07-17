<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\LoanFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'loan_no', 'member_id', 'loan_product_id', 'applied_amount',
        'applied_term_months', 'purpose', 'approved_amount', 'approved_term_months',
        'principal_disbursed', 'principal_outstanding', 'interest_outstanding',
        'fees_outstanding', 'penalties_outstanding', 'status', 'classification',
        'days_in_arrears', 'provision_amount', 'created_by', 'approved_by',
        'submitted_at', 'approved_at', 'disbursed_at', 'first_payment_date',
        'closed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'applied_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'principal_disbursed' => 'decimal:2',
            'principal_outstanding' => 'decimal:2',
            'interest_outstanding' => 'decimal:2',
            'fees_outstanding' => 'decimal:2',
            'penalties_outstanding' => 'decimal:2',
            'provision_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'date',
            'first_payment_date' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_amount', 'classification'])
            ->logOnlyDirty()
            ->useLogName('loan');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_no');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function collateral(): HasMany
    {
        return $this->hasMany(LoanCollateral::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Total currently owed (principal + interest + fees + penalties). */
    public function totalOutstanding(): string
    {
        return bcadd(
            bcadd($this->principal_outstanding, $this->interest_outstanding, 2),
            bcadd($this->fees_outstanding, $this->penalties_outstanding, 2),
            2,
        );
    }
}
