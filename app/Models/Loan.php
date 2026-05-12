<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * @property int $id
 * @property int $borrower_id
 * @property float $amount
 * @property float $total_paid
 * @property float $interest_rate
 * @property int $duration_months
 * @property float $monthly_payment
 * @property string $start_date
 * @property string $status
 * @property int|null $co_borrower_id
 * @property int|null $guarantor_id
 * @property string $currency
 * @property string $repayment_method
 * @property string|null $purpose
 * @property string $loan_code
 * @property string $payment_frequency
 * @property int|null $loan_officer_id
 * @property float $admin_fee
 * @property string $admin_fee_type one_time|monthly
 * @property int|null $refinanced_from_loan_id
 * @property float $refinance_fee
 * @property float $refinanced_amount
 * @property int $loan_cycle
 * @property-read \App\Models\Borrower $borrower
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Payment[] $payments
  */
class Loan extends Model
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
        'borrower_id',
        'amount',
        'disbursed_amount',
        'total_paid',
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
        'purpose',
        'sector',
        'loan_code',
        'payment_frequency',
        'loan_officer_id',
        'admin_fee',
        'admin_fee_type',
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
        'maturity_date',
        'product_id',
        'aging',
        'monthly_interest',
        'reschedule_fee',
        'rescheduled_at',
        'payment_qr_id',
    ];

    public function paymentQr(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentQr::class, 'payment_qr_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'product_id');
    }

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

    public function updateAging(): void
    {
        $today = \Carbon\Carbon::today();
        
        // Find the earliest installment that is past due and not fully paid
        $earliestArrear = \App\Models\Payment::where('loan_id', $this->id)
            ->where('payment_date', '<', $today->toDateString())
            ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
            ->orderBy('payment_date', 'asc')
            ->first();

        if ($earliestArrear) {
            $lastDue = \Carbon\Carbon::parse($earliestArrear->payment_date);
            $aging = (int) $today->diffInDays($lastDue);
            $this->update(['aging' => $aging]);
        } else {
            // No arrears, reset aging to 0
            $this->update(['aging' => 0]);
        }
    }

    /**
     * Recalculates the remaining payment schedule based on the current outstanding principal.
     */
    public function recalculateSchedule(): void
    {
        // 0. Clean up orphaned payments: rows with total_paid > 0 but no repayment_transaction_id
        //    This can happen after voiding a Pay Off where some rows were not directly linked
        Payment::where('loan_id', $this->id)
            ->where('total_paid', '>', 0.001)
            ->whereNull('repayment_transaction_id')
            ->update([
                'total_paid' => 0,
                'fee_paid' => 0,
                'penalty_amount' => 0,
            ]);

        // 1. Calculate current outstanding principal (scheduled principal + prepayment surplus + payoff principal - withdrawn prepayments)
        $totalPrincipalPaid = (float) $this->transactions()->sum('principal_paid')
                            + (float) $this->transactions()->sum('prepayment_paid')
                            + (float) $this->transactions()->sum('paid_off_amount')
                            - (float) $this->transactions()->sum('withdrawn_prepayment');
        $outstandingPrincipal = round($this->amount - $totalPrincipalPaid, 2);

        if ($outstandingPrincipal <= 0) {
            Payment::where('loan_id', $this->id)
                ->whereRaw('total_paid < 0.01')
                ->delete();
            $this->update(['status' => 'completed', 'monthly_payment' => 0]);
            return;
        }

        // 2. Identify remaining term
        $lastPaidInstallment = Payment::where('loan_id', $this->id)
            ->where('total_paid', '>', 0)
            ->orderBy('payment_number', 'desc')
            ->first();

        $lastPaidNumber = $lastPaidInstallment ? $lastPaidInstallment->payment_number : 0;
        $remainingMonths = $this->duration_months - $lastPaidNumber;

        if ($remainingMonths <= 0) {
            return;
        }

        // 3. Delete all future installments that haven't been touched yet
        Payment::where('loan_id', $this->id)
            ->where('payment_number', '>', $lastPaidNumber)
            ->where('total_paid', '<', 0.01)
            ->delete();

        $r = ($this->interest_rate / 100);
        $n = $remainingMonths;
        $p = $outstandingPrincipal;
        $method = strtolower(trim($this->repayment_method ?? ''));

        if ($n <= 0) {
            $this->update(['monthly_payment' => 0]);
            return;
        }

        // Determine if this is a flat/fixed-principal method
        $isFlat = str_contains($method, 'fixed') || str_contains($method, 'flat')
               || str_contains($method, '100%') || str_contains($method, 'declining')
               || str_contains($method, '70%') || str_contains($method, '50%');

        if ($isFlat) {
            // Fixed Principal method: principal is evenly split, interest on remaining balance
            $fixedPrincipal = round($p / $n, 2);
            $newMonthlyPayment = round($fixedPrincipal + ($p * $r), 2);
            $this->update(['monthly_payment' => $newMonthlyPayment]);
        } else {
            // Amortization (annuity) formula: EMI = [P x R x (1+R)^N] / [(1+R)^N - 1]
            if ($r > 0) {
                $denominator = pow(1 + $r, $n) - 1;
                if ($denominator == 0) {
                    $newMonthlyPayment = $p / $n;
                } else {
                    $newMonthlyPayment = ($p * $r * pow(1 + $r, $n)) / $denominator;
                }
            } else {
                $newMonthlyPayment = $p / $n;
            }
            $newMonthlyPayment = round($newMonthlyPayment, 2);
            $this->update(['monthly_payment' => $newMonthlyPayment]);
        }

        // 4. Generate new future installments
        $lastDate = $lastPaidInstallment
            ? \Carbon\Carbon::parse($lastPaidInstallment->payment_date)
            : \Carbon\Carbon::parse($this->start_date);

        $currentBalance = $p;
        for ($i = 1; $i <= $n; $i++) {
            $paymentNumber = $lastPaidNumber + $i;
            $paymentDate = $lastDate->copy()->addMonths($i);

            $interestAmount = round($currentBalance * $r, 2);

            if ($isFlat) {
                // Fixed principal each month
                $principalAmount = round($p / $n, 2);
                // Adjust final installment to absorb rounding
                if ($i === $n) {
                    $principalAmount = $currentBalance;
                }
            } else {
                // Amortization: principal = EMI - interest
                $principalAmount = round($newMonthlyPayment - $interestAmount, 2);
                if ($i === $n) {
                    $principalAmount = $currentBalance;
                }
            }

            Payment::create([
                'loan_id' => $this->id,
                'payment_number' => $paymentNumber,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'fee_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $paymentDate->toDateString(),
            ]);

            $currentBalance -= $principalAmount;
        }
    }

    public function modifications()
    {
        return $this->hasMany(LoanModification::class);
    }
}
