<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $borrower_id
 * @property float $amount
 * @property float $interest_rate
 * @property int $duration_months
 * @property float $monthly_payment
 * @property string $start_date
 * @property string $status
 * @property int|null $co_borrower_id
 * @property int|null $guarantor_id
 * @property string $currency
 * @property string $repayment_method
 * @property string $loan_code
 * @property string $payment_frequency
 * @property int|null $loan_officer_id
 * @property float $admin_fee
 * @property int|null $refinanced_from_loan_id
 * @property float $refinance_fee
 * @property float $refinanced_amount
 * @property int $loan_cycle
 * @property-read \App\Models\Borrower $borrower
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Payment[] $payments
 */
class Loan extends Model
{

    protected $fillable = [
        'borrower_id',
        'amount',
        'interest_rate',
        'duration_months',
        'monthly_payment',
        'start_date',
        'status',
        'co_borrower_id',
        'co_borrower_relationship',
        'guarantor_id',
        'guarantor_relationship',
        'currency',
        'repayment_method',
        'loan_code',
        'payment_frequency',
        'loan_officer_id',
        'admin_fee',
        'refinanced_from_loan_id',
        'refinance_fee',
        'refinanced_amount',
        'loan_cycle',
        'disbursed_by_officer_id',
        'written_off_at',
        'write_off_reason',
        'classify_wo',
        'write_off_balance',
        'recovery_amount',
        'maturity_date'
    ];

    public function borrower(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Borrower::class, 'borrower_id');
    }

    public function coBorrower(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CoBorrower::class, 'co_borrower_id');
    }

    public function guarantor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Guarantor::class, 'guarantor_id');
    }

    public function officer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoanOfficer::class, 'loan_officer_id');
    }

    public function disburseOfficer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoanOfficer::class, 'disbursed_by_officer_id');
    }

    public function refinancedFrom(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Loan::class, 'refinanced_from_loan_id');
    }

    public function collaterals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Collateral::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RepaymentTransaction::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
