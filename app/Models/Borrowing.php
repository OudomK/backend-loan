<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrowing extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'lender_id',
        'transaction_no',
        'loan_account',
        'category',
        'borrowing_date',
        'account_no',
        'contract_no',
        'payment_method',
        'first_pay_date',
        'currency',
        'term_months',
        'amount',
        'interest_rate',
        'penalty_rate',
        'int_pay_mode',
        'fee',
        'maturity_date',
        'sl_term',
        'status',
        'late_principal',
        'loan_interest',
    ];

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(BorrowingRepayment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BorrowingSchedule::class);
    }

    protected static function booted()
    {
        static::created(function ($borrowing) {
            $borrowing->generateSchedule();
        });

        static::updated(function ($borrowing) {
            // Only regenerate if critical calculation fields have changed.
            // Never regenerate after repayments exist, to avoid breaking paid schedule history.
            $criticalFields = ['amount', 'interest_rate', 'term_months', 'first_pay_date', 'borrowing_date', 'payment_method'];
            if ($borrowing->wasChanged($criticalFields)) {
                $borrowing->generateSchedule();
            }
        });
    }

    public function generateSchedule()
    {
        if ($this->repayments()->exists()) {
            return;
        }

        $deletedSchedules = $this->schedules()->count();
        $this->schedules()->delete();

        $amount = (float) $this->amount;
        $rate = (float) $this->interest_rate / 100;
        $terms = (int) $this->term_months;
        $startDate = \Carbon\Carbon::parse($this->first_pay_date ?? $this->borrowing_date);
        $method = strtolower($this->payment_method); // Balloon, Declining

        $currentBalance = $amount;
        for ($i = 1; $i <= $terms; $i++) {
            $dueDate = $startDate->copy()->addMonths($i - 1);
            $interest = round($currentBalance * $rate, 2);
            $principal = 0;

            if ($method === 'declining' || $method === 'fixed') {
                $principal = round($amount / $terms, 2);
                if ($i === $terms) {
                    $principal = $currentBalance;
                }
            } elseif ($method === 'balloon' || $method === 'negotiable') {
                if ($i === $terms) {
                    $principal = $amount;
                }
            } else {
                // Default to Fixed Principal if unknown
                $principal = round($amount / $terms, 2);
                if ($i === $terms) {
                    $principal = $currentBalance;
                }
            }

            $this->schedules()->create([
                'installment_no' => $i,
                'due_date' => $dueDate->toDateString(),
                'principal_due' => $principal,
                'interest_due' => $interest,
                'total_due' => $principal + $interest,
                'status' => 'pending',
            ]);

            $currentBalance -= $principal;
        }

        activity('borrowings')
            ->performedOn($this)
            ->withProperties([
                'deleted_schedules' => $deletedSchedules,
                'generated_schedules' => $terms,
                'payment_method' => $this->payment_method,
            ])
            ->log('Generated borrowing schedule');
    }
}
