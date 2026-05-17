<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $payment_number
 * @property float $principal_amount
 * @property float $interest_amount
 * @property float $penalty_amount
 * @property float $total_paid
 * @property string $payment_date
 * @property string $payment_method
 */
class Payment extends Model
{
    use SoftDeletes, LogsActivity;

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
        'fee_amount',
        'fee_paid',
        'total_due',
        'prepayment',
        'penalty_amount',
        'total_paid',
        'payment_date',
        'payment_method',
        'repayment_transaction_id',
    ];

    public function loan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
