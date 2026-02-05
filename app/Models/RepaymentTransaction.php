<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $loan_id
 * @property int|null $collector_id
 * @property float $amount_paid
 * @property float $principal_paid
 * @property float $interest_paid
 * @property float $penalty_paid
 * @property string $payment_method
 * @property string $repayment_type
 * @property string $transaction_date
 * @property-read \App\Models\Loan $loan
 * @property-read \App\Models\LoanOfficer|null $collector
 */
class RepaymentTransaction extends Model
{
    protected $fillable = [
        'loan_id',
        'collector_id',
        'amount_paid',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'payment_method',
        'repayment_type',
        'transaction_date'
    ];

    public function loan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function collector(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoanOfficer::class, 'collector_id');
    }
}
