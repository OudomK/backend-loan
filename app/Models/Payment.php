<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $payment_number
 * @property float $principal_amount
 * @property float $interest_amount
 * @property float|null $outstanding_balance
 * @property float $penalty_amount
 * @property float $total_paid
 * @property string $payment_date
 * @property string $payment_method
 * @property string|null $settled_at
 * @property string|null $settled_due_date
 * @property int|null $settled_days_variance
 * @property string|null $settlement_source
 */
class Payment extends Model
{
    use LogsActivity, SoftDeletes;

    protected static function booted()
    {
        static::saving(function ($payment) {
            unset($payment->total_due);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'loan_id',
        'payment_number',
        'principal_amount',
        'interest_amount',
        'outstanding_balance',
        'fee_amount',
        'fee_paid',
        'total_due',
        'prepayment',
        'penalty_amount',
        'total_paid',
        'payment_date',
        'payment_method',
        'repayment_transaction_id',
        'settled_at',
        'settled_due_date',
        'settled_days_variance',
        'settlement_source',
    ];

    public function loan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
