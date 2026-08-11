<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use LogsActivity, SoftDeletes;

    protected $appends = ['print_schedule'];

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
        'locked_aging',
        'accumulated_penalty',
        'late_since_date',
        'penalty_late_since_date',
        'monthly_interest',
        'reschedule_fee',
        'rescheduled_at',
        'payment_qr_id',
        'submitted_by',
        'checked_by',
        'verified_by',
        'approved_by',
        'checked_at',
        'verified_at',
        'approved_at',
        'rejection_reason',
    ];

    // ── Approval Workflow ────────────────────────────────────────────

    public function approvals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoanApproval::class)->orderBy('created_at', 'desc');
    }

    public function submitter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function checker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function verifier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canBeChecked(): bool
    {
        return $this->status === LoanApproval::STATUS_PENDING_CHECK;
    }

    public function canBeVerified(): bool
    {
        return $this->status === LoanApproval::STATUS_PENDING_VERIFY;
    }

    public function canBeApproved(): bool
    {
        return $this->status === LoanApproval::STATUS_PENDING_APPROVAL;
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, LoanApproval::pendingStatuses());
    }

    public function isPendingAnyApproval(): bool
    {
        return in_array($this->status, LoanApproval::pendingStatuses());
    }

    public function canBeResubmitted(): bool
    {
        return $this->status === LoanApproval::STATUS_REJECTED;
    }

    // ── End Approval Workflow ────────────────────────────────────────

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
        // The persisted contract principal is the OS source of truth. Automatic
        // schedule recalculation must never inflate the customer's debt. When an
        // authorized user edits schedule principal, LoanController synchronizes
        // this amount in the same transaction.
        return max(0, (float) $this->amount);
    }

    /**
     * Return the upfront administrative fee as an amount.
     *
     * admin_fee is stored as a percentage. Monthly fees belong to individual
     * installments and therefore are not an upfront processing fee.
     */
    public function processingFeeAmount(): float
    {
        $feePercent = max(0, (float) ($this->admin_fee ?? 0));
        $feeType = trim((string) ($this->admin_fee_type ?? '')) ?: 'one_time';

        if ($feePercent <= 0 || $feeType === 'monthly') {
            return 0.0;
        }

        $baseAmount = $feeType === 'capitalized_upfront'
            ? (float) ($this->disbursed_amount ?? 0)
            : (float) ($this->amount ?? 0);

        if ($baseAmount <= 0) {
            $baseAmount = (float) ($this->amount ?? 0);
        }

        return round($baseAmount * $feePercent / 100, 2);
    }

    public function updateAging(): void
    {
        $today = \Carbon\Carbon::today();
        $usesInstallmentFee = (trim((string) ($this->admin_fee_type ?? '')) ?: 'one_time') === 'monthly';
        $arrearExpression = $usesInstallmentFee
            ? 'total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0) - 0.01)'
            : 'total_paid < (principal_amount + interest_amount - 0.01)';

        // Row-level aging follows the oldest installment that is still overdue.
        // Loan-level aging follows the continuous penalty period and must not
        // move backwards when an older overdue installment is settled.
        $earliestArrear = \App\Models\Payment::where('loan_id', $this->id)
            ->where('payment_date', '<', $today->toDateString())
            ->whereRaw($arrearExpression)
            ->orderBy('payment_date', 'asc')
            ->first();

        if (! $earliestArrear) {
            $penaltyBalance = $this->currentPenaltyDue($today);
            $continuousAging = $this->currentAging($today);

            if ($penaltyBalance > 0.01) {
                $this->update([
                    'late_since_date' => null,
                    'penalty_late_since_date' => null,
                    'aging' => $continuousAging,
                    'locked_aging' => $continuousAging,
                    'accumulated_penalty' => $penaltyBalance,
                ]);

                return;
            }

            $this->update([
                'late_since_date' => null,
                'penalty_late_since_date' => null,
                'aging' => 0,
                'locked_aging' => 0,
                'accumulated_penalty' => 0,
            ]);

            return;
        }

        $earliestDate = \Carbon\Carbon::parse($earliestArrear->payment_date)->startOfDay();
        $penaltyStartDate = $this->penalty_late_since_date
            ? \Carbon\Carbon::parse($this->penalty_late_since_date)->startOfDay()
            : $earliestDate;
        $continuousLateDays = $today->gt($penaltyStartDate)
            ? (int) abs($today->diffInDays($penaltyStartDate))
            : 0;
        $loanAging = max($continuousLateDays, (int) ($this->locked_aging ?? 0));

        $updates = [
            'late_since_date' => $earliestDate->toDateString(),
            'aging' => $loanAging,
            'locked_aging' => (float) ($this->accumulated_penalty ?? 0) > 0.01
                ? (int) ($this->locked_aging ?? 0)
                : 0,
        ];

        // Penalty uses its own anchor so moving schedule aging to a newer row
        // cannot erase or double-count an already active penalty period.
        if (! $this->penalty_late_since_date) {
            $updates['penalty_late_since_date'] = $earliestDate->toDateString();
        }

        $this->update($updates);
    }

    /**
     * Return the loan-level aging used by live screens.
     */
    public function currentAging(?\Carbon\Carbon $referenceDate = null): int
    {
        $referenceDate = ($referenceDate ?? \Carbon\Carbon::today())->copy()->startOfDay();

        $lockedAging = max(0, (int) ($this->locked_aging ?? 0));
        $agingStartDate = $this->penalty_late_since_date ?? $this->late_since_date;

        if (! $agingStartDate) {
            return $lockedAging;
        }

        $lateSinceDate = \Carbon\Carbon::parse($agingStartDate)->startOfDay();
        $currentLateDays = $referenceDate->gt($lateSinceDate)
            ? (int) abs($referenceDate->diffInDays($lateSinceDate))
            : 0;

        return max($currentLateDays, $lockedAging);
    }

    /**
     * Return the daily penalty locked onto this loan when it was created.
     */
    public function resolvePenaltyRate(): float
    {
        if ($this->penalty_rate !== null) {
            return (float) $this->penalty_rate;
        }

        $settingKey = $this->currency === 'KHR'
            ? 'default_penalty_khr'
            : 'default_penalty_usd';
        $defaultRate = $this->currency === 'KHR' ? 10000.0 : 2.5;

        return (float) (Setting::where('key', $settingKey)->value('value') ?? $defaultRate);
    }

    /**
     * Resolve aging at a report reference date without using row-level values on live screens.
     */
    public function agingAt(\Carbon\Carbon $referenceDate, ?string $arrearDate = null, bool $hasArrears = false): int
    {
        $referenceDate = $referenceDate->copy()->startOfDay();

        if (! $arrearDate) {
            return 0;
        }

        $arrearDate = \Carbon\Carbon::parse($arrearDate)->startOfDay();
        $aging = $referenceDate->gt($arrearDate)
            ? (int) abs($referenceDate->diffInDays($arrearDate))
            : 0;

        return $aging;
    }

    /**
     * Return penalty payments and waivers made during the active late period.
     */
    public function currentPeriodPenaltyCredits(?\Carbon\Carbon $referenceDate = null): float
    {
        $penaltySinceDate = $this->penalty_late_since_date ?? $this->late_since_date;
        if (! $penaltySinceDate) {
            return 0.0;
        }

        $referenceDate = ($referenceDate ?? \Carbon\Carbon::today())->copy()->startOfDay();

        return (float) $this->transactions()
            ->whereDate('transaction_date', '>=', $penaltySinceDate)
            ->whereDate('transaction_date', '<=', $referenceDate->toDateString())
            ->sum(\Illuminate\Support\Facades\DB::raw('penalty_paid + waived_amount'));
    }

    /**
     * Calculate the outstanding penalty for this loan at a given date.
     *
     * accumulated_penalty is the frozen balance from completed late periods.
     */
    public function currentPenaltyDue(?\Carbon\Carbon $referenceDate = null): float
    {
        $referenceDate = ($referenceDate ?? \Carbon\Carbon::today())->copy()->startOfDay();
        $frozenPenalty = max(0, (float) ($this->accumulated_penalty ?? 0));
        $penaltySinceDate = $this->penalty_late_since_date ?? $this->late_since_date;

        if (! $penaltySinceDate) {
            return round($frozenPenalty, 2);
        }

        $lateSinceDate = \Carbon\Carbon::parse($penaltySinceDate)->startOfDay();
        $currentLateDays = $referenceDate->gt($lateSinceDate)
            ? (int) abs($referenceDate->diffInDays($lateSinceDate))
            : 0;
        $currentPenalty = $currentLateDays * $this->resolvePenaltyRate();

        return round(max(0, $frozenPenalty + $currentPenalty - $this->currentPeriodPenaltyCredits($referenceDate)), 2);
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
        $outstandingPrincipal = round((float) $this->amount - $totalPrincipalPaid, 2);

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

        $retainedPayments = Payment::where('loan_id', $this->id)
            ->where('payment_number', '<=', $lastPaidNumber)
            ->get();

        $unpaidPrincipalInRetainedRows = $retainedPayments->sum(function (Payment $payment): float {
            $paidToPrincipalAndInterest = max(
                0,
                (float) $payment->total_paid - (float) ($payment->fee_paid ?? 0)
            );
            $interestPaid = min((float) $payment->interest_amount, $paidToPrincipalAndInterest);
            $principalPaid = min(
                (float) $payment->principal_amount,
                max(0, $paidToPrincipalAndInterest - $interestPaid)
            );

            return max(0, (float) $payment->principal_amount - $principalPaid);
        });

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

        // Partially paid retained rows may still contain unpaid principal. Do not
        // allocate that amount again into future rows, otherwise every partial or
        // prepayment can progressively inflate both principal and interest.
        $futurePrincipalTarget = max(
            0,
            round($outstandingPrincipal - $unpaidPrincipalInRetainedRows, 2)
        );
        $ratio = $futurePrincipalTarget / $scheduledRemainingPrincipal;
        $currentBalance = $futurePrincipalTarget;
        $newMonthlyPayment = 0;

        foreach ($futurePayments as $index => $payment) {
            /** @var \App\Models\Payment $payment */
            $isLast = ($index === $futurePayments->count() - 1);

            $newInterest = round($payment->interest_amount * $ratio, 2);

            if ($isLast) {
                // Absorb any rounding differences in the final payment
                $newPrincipal = $currentBalance;
            } else {
                $newPrincipal = round($payment->principal_amount * $ratio, 2);
            }

            if ($index === 0) {
                // Approximate new monthly payment based on the first upcoming adjusted installment
                $newMonthlyPayment = round($newPrincipal + $newInterest + $payment->fee_amount, 2);
            }

            $payment->principal_amount = $newPrincipal;
            $payment->interest_amount = $newInterest;
            $payment->total_due = round($newPrincipal + $newInterest + ($payment->fee_amount ?? 0), 2);
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
                'unpaid_principal_in_retained_rows' => round($unpaidPrincipalInRetainedRows, 2),
                'future_principal_target' => round($futurePrincipalTarget, 2),
                'monthly_payment' => $newMonthlyPayment,
            ])
            ->log('Recalculated future loan schedule');
    }

    public function modifications()
    {
        return $this->hasMany(LoanModification::class);
    }

    public function getPrintScheduleAttribute()
    {
        if (! $this->relationLoaded('payments') || $this->payments->isEmpty()) {
            return [];
        }

        $paymentsArray = $this->payments->toArray();

        return \App\Services\LoanCalculator::formatScheduleForPrint(
            $paymentsArray,
            $this->repayment_method ?? '',
            (float) ($this->amount ?? 0)
        );
    }
}
