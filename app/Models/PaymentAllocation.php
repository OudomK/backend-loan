<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $repayment_transaction_id
 * @property float $amount_applied
 * @property float $fee_applied
 * @property float $interest_applied
 * @property float $principal_applied
 * @property string $created_at
 * @property string $updated_at
 */
class PaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'repayment_transaction_id',
        'amount_applied',
        'fee_applied',
        'interest_applied',
        'principal_applied',
        'penalty_applied',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RepaymentTransaction::class, 'repayment_transaction_id');
    }
}
