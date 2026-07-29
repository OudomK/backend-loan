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
        'penalty_rate',
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
        'late_since_date',
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

    public function getBasePrincipalForOS(): float
    {
        $firstPayment = $this->payments->first();
        if ($firstPayment && $firstPayment->outstanding_balance !== null && (float)$firstPayment->outstanding_balance > 0) {
            $os = (float)$firstPayment->outstanding_balance;
            $princ = (float)$firstPayment->principal_amount;

            // If the outstanding_balance exactly matches the loan amount, it was saved as the starting balance.
            if (abs($os - (float)$this->amount) < 0.01) {
                return $os;
            }

            return $os + $princ;
        }
        $totalSchedulePrincipal = (float) $this->payments->sum('principal_amount');
        return $totalSchedulePrincipal > 0 ? $totalSchedulePrincipal : (float)$this->amount;
    }

    public function updateAging(): void
    {
        $today = \Carbon\Carbon::today();
        $usesInstallmentFee = (trim((string) ($this->admin_fee_type ?? '')) ?: 'one_time') === 'monthly';
        $arrearExpression = $usesInstallmentFee
            ? 'total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0) - 0.01)'
            : 'total_paid < (principal_amount + interest_amount - 0.01)';

        // Check if there's any unpaid past due installment
        $hasUnpaidRows = \App\Models\Payment::where('loan_id', $this->id)
            ->where('payment_date', '<', $today->toDateString())
            ->whereRaw($arrearExpression)
            ->exists();

        // Calculate the end date for aging calculation
        $endDate = $today;
        if (!$hasUnpaidRows) {
            $lastTxDate = \App\Models\RepaymentTransaction::where('loan_id', $this->id)->max('transaction_date');
            if ($lastTxDate) {
                $parsed = \Carbon\Carbon::parse($lastTxDate)->startOfDay();
                if ($parsed->lt($today)) {
                    $endDate = $parsed;
                }
            }
        }

        // Calculate current late days based on the end date
        $currentLateDays = 0;
        if ($this->late_since_date) {
            $earliestDate = \Carbon\Carbon::parse($this->late_since_date)->startOfDay();
            if ($endDate->gt($earliestDate)) {
                $currentLateDays = (int) abs($endDate->diffInDays($earliestDate, false));
            }
        }

        $totalAging = $this->locked_aging + $currentLateDays;

        // Calculate Penalty Gross using total aging
        $penaltyRate = $this->penalty_rate ?? (str_contains(strtoupper($this->currency ?? ''), 'KHR') ? 10000.0 : 2.5);
        $penaltyGross = round($totalAging * $penaltyRate, 2);
        
        // Calculate total penalty paid so far
        $penaltyPaidTotal = (float) \App\Models\RepaymentTransaction::where('loan_id', $this->id)
            ->sum(\Illuminate\Support\Facades\DB::raw('penalty_paid + waived_amount'));

        $isPenaltyFullyPaid = $penaltyPaidTotal >= ($penaltyGross - 0.01);

        if (!$hasUnpaidRows) {
            if ($isPenaltyFullyPaid) {
                // Completely caught up!
                $this->update([
                    'late_since_date' => null,
                    'locked_aging' => 0,
                    'aging' => 0
                ]);
            } else {
                // Installments paid, but penalty not paid. Lock the aging.
                if ($this->late_since_date) {
                    $this->update([
                        'locked_aging' => $totalAging,
                        'late_since_date' => null,
                        'aging' => $totalAging
                    ]);
                } else {
                    $this->update([
                        'aging' => $this->locked_aging
                    ]);
                }
            }
        } else {
            // Still late (either owes installments OR owes penalty)
            if (!$this->late_since_date) {
                // Determine when it first became late
                $earliestArrear = \App\Models\Payment::where('loan_id', $this->id)
                    ->where('payment_date', '<', $today->toDateString())
                    ->whereRaw($arrearExpression)
                    ->orderBy('payment_date', 'asc')
                    ->first();
                if ($earliestArrear) {
                    $this->update(['late_since_date' => $earliestArrear->payment_date]);

                    // Recalculate currentLateDays since late_since_date just changed
                    $earliestDate = \Carbon\Carbon::parse($earliestArrear->payment_date)->startOfDay();
                    if ($endDate->gt($earliestDate)) {
                        $currentLateDays = (int) abs($endDate->diffInDays($earliestDate, false));
                    }
                }
            }

            $this->update(['aging' => $this->locked_aging + $currentLateDays]);
        }
    }

    /**
     * Recalculates the remaining payment schedule based on the current outstanding principal.
     */
    public function recalculateSchedule(): void
    {
        // 0. Clean up orphaned payments: rows with total_paid > 0 but no repayment_transaction_id
        //    This can happen after voiding a Pay Off where some rows were not directly linked
        $orphanedPaymentsReset = Payment::where('loan_id', $this->id)
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
        $outstandingPrincipal = round($this->amount - $totalPrincipalPaid, 0);

        if ($outstandingPrincipal <= 0) {
            $deletedPayments = Payment::where('loan_id', $this->id)
                ->whereRaw('total_paid < 0.01')
                ->forceDelete();
            $this->update(['status' => 'completed', 'monthly_payment' => 0]);

            activity('loan_schedule')
                ->performedOn($this)
                ->withProperties([
                    'orphaned_payments_reset' => $orphanedPaymentsReset,
                    'deleted_payments' => $deletedPayments,
                    'outstanding_principal' => 0,
                ])
                ->log('Recalculated loan schedule to completion');

            return;
        }

        // 2. Identify remaining term and untouched future installments
        $lastPaidInstallment = Payment::where('loan_id', $this->id)
            ->where('total_paid', '>', 0)
            ->orderBy('payment_number', 'desc')
            ->first();

        $lastPaidNumber = $lastPaidInstallment ? $lastPaidInstallment->payment_number : 0;

        $futurePayments = Payment::where('loan_id', $this->id)
            ->where('payment_number', '>', $lastPaidNumber)
            ->where('total_paid', '<', 0.01)
            ->orderBy('payment_number', 'asc')
            ->get();

        if ($futurePayments->isEmpty()) {
            $this->update(['monthly_payment' => 0]);
            return;
        }

        // 3. Calculate Ratio and update future installments in place
        // This preserves complex schedules (like 15-day, custom skips, and balloon setups) 
        // by scaling the remaining principal and interest instead of naive recreation.
        $scheduledRemainingPrincipal = $futurePayments->sum('principal_amount');

        // If scheduled principal is essentially zero but balance remains, or vice-versa, handle cleanup
        if ($scheduledRemainingPrincipal <= 0.001) {
            $deletedPayments = Payment::where('loan_id', $this->id)
                ->where('payment_number', '>', $lastPaidNumber)
                ->where('total_paid', '<', 0.01)
                ->forceDelete();
            $this->update(['monthly_payment' => 0]);

            activity('loan_schedule')
                ->performedOn($this)
                ->withProperties([
                    'orphaned_payments_reset' => $orphanedPaymentsReset,
                    'deleted_payments' => $deletedPayments,
                    'outstanding_principal' => $outstandingPrincipal,
                ])
                ->log('Trimmed empty future loan schedule');

            return;
        }

        $ratio = $outstandingPrincipal / $scheduledRemainingPrincipal;
        $currentBalance = $outstandingPrincipal;
        $newMonthlyPayment = 0;

        foreach ($futurePayments as $index => $payment) {
            /** @var \App\Models\Payment $payment */
            $isLast = ($index === $futurePayments->count() - 1);

            $newInterest = round($payment->interest_amount * $ratio, 0);

            if ($isLast) {
                // Absorb any rounding differences in the final payment
                $newPrincipal = $currentBalance;
            } else {
                $newPrincipal = round($payment->principal_amount * $ratio, 0);
            }

            if ($index === 0) {
                // Approximate new monthly payment based on the first upcoming adjusted installment
                $newMonthlyPayment = round($newPrincipal + $newInterest + $payment->fee_amount, 0);
            }

            $payment->principal_amount = $newPrincipal;
            $payment->interest_amount = $newInterest;
            $payment->total_due = round($newPrincipal + $newInterest + ($payment->fee_amount ?? 0), 0);
            $payment->save();

            $currentBalance -= $newPrincipal;
        }

        $this->update(['monthly_payment' => $newMonthlyPayment]);

        activity('loan_schedule')
            ->performedOn($this)
            ->withProperties([
                'orphaned_payments_reset' => $orphanedPaymentsReset,
                'updated_installments' => $futurePayments->count(),
                'outstanding_principal' => $outstandingPrincipal,
                'monthly_payment' => $newMonthlyPayment,
            ])
            ->log('Recalculated future loan schedule');
    }

    public function modifications()
    {
        return $this->hasMany(LoanModification::class);
    }
}
