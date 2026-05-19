<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActiveLoanReportController extends Controller
{
    public function index(Request $request)
    {
        $officerId = $request->query('officer_id');
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date') ?? $request->query('report_date');

        // Use toDate or today as reference for calculations (e.g. aging, outstanding at that date)
        $refDate = $toDateStr ? Carbon::parse($toDateStr) : Carbon::today();
        $refDateStr = $refDate->toDateString();

        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr) : null;
        $fromDateStr = $fromDate ? $fromDate->toDateString() : null;

        // Load candidate loans and reconstruct the portfolio as of the selected date.
        $query = Loan::with([
            'borrower' => function($q) { $q->withTrashed(); },
            'officer',
            'disburseOfficer',
            'collaterals',
            'product',
            'payments' => function ($query) {
                $query->orderBy('payment_date', 'asc');
            },
            'transactions' => function ($query) use ($refDateStr) {
                $query->where('transaction_date', '<=', $refDateStr);
            },
        ])
            ->where('status', '!=', 'pending')
            ->where(function ($query) use ($refDateStr) {
                $query->whereNull('written_off_at')
                    ->orWhereDate('written_off_at', '>', $refDateStr);
            });

        // Filter by Disbursement Date Range
        if ($fromDateStr) {
            $query->where('start_date', '>=', $fromDateStr);
        }
        if ($refDateStr) {
            $query->where('start_date', '<=', $refDateStr);
        }

        if ($officerId && $officerId !== 'all') {
            $query->where('loan_officer_id', $officerId);
        }

        $loans = $query->get();

        // Transform Data
        $data = $loans->map(function ($loan) use ($refDate) {
            $borrower = $loan->borrower;
            $officer = $loan->officer;
            $product = $loan->product;
            $transactionsAtDate = $loan->transactions;

            $principalPaid = $transactionsAtDate->sum(function ($transaction) {
                return (float) ($transaction->principal_paid ?? 0)
                    + (float) ($transaction->prepayment_paid ?? 0)
                    + (float) ($transaction->paid_off_amount ?? 0)
                    - (float) ($transaction->withdrawn_prepayment ?? 0);
            });

            $outstanding = max(0, (float) $loan->amount - $principalPaid);
            if ($outstanding <= 0.01) {
                return null;
            }

            $interestPaid = $transactionsAtDate->sum(function ($transaction) {
                return (float) ($transaction->interest_paid ?? 0);
            });

            $scheduledPaidAtDate = $transactionsAtDate->sum(function ($transaction) {
                return (float) ($transaction->principal_paid ?? 0)
                    + (float) ($transaction->interest_paid ?? 0)
                    + (float) ($transaction->paid_off_amount ?? 0);
            });

            $paymentsBeforeRefDate = $loan->payments->filter(function ($payment) use ($refDate) {
                return $payment->payment_date < $refDate->toDateString();
            });

            $totalDueBeforeRefDate = 0.0;
            $cumulativeDue = 0.0;
            $earliestArrearDate = null;

            foreach ($paymentsBeforeRefDate as $payment) {
                $installmentDue = (float) ($payment->principal_amount ?? 0)
                    + (float) ($payment->interest_amount ?? 0);

                $totalDueBeforeRefDate += $installmentDue;
                $cumulativeDue += $installmentDue;

                if (!$earliestArrearDate && ($cumulativeDue - $scheduledPaidAtDate) > 0.01) {
                    $earliestArrearDate = $payment->payment_date;
                }
            }

            $overdueAmount = max(0, $totalDueBeforeRefDate - $scheduledPaidAtDate);
            $agingDays = $earliestArrearDate
                ? $refDate->diffInDays(Carbon::parse($earliestArrearDate))
                : 0;

            // Collateral
            $collateralType = $loan->collaterals->isNotEmpty() ? $loan->collaterals->first()->type : '';
            $firstRepaymentDate = optional($loan->payments->first())->payment_date
                ?? Carbon::parse($loan->start_date)->addMonth()->toDateString();
            $maturityDate = $loan->maturity_date
                ?? optional($loan->payments->last())->payment_date
                ?? Carbon::parse($loan->start_date)->addMonths($loan->duration_months)->toDateString();
            $lastPaymentDate = $transactionsAtDate->max('transaction_date');
            $loanProduct = $product ? $product->name : 'General Loan';
            $isRescheduled = $transactionsAtDate->contains(function ($transaction) {
                return $transaction->repayment_type === 'Reschedule';
            });

            // Formatting
            return [
                'disbursement_date' => $loan->start_date, // or formatted
                'loan_code' => $loan->loan_code,
                'client_name' => $borrower ? ($borrower->first_name . ' ' . $borrower->last_name) : '',
                // Address
                'village_name' => $borrower->village ?? '',
                'commune_name' => $borrower->commune ?? '',
                'district_name' => $borrower->district ?? '',
                'province_name' => $borrower->province ?? '',

                'disbursement_amount' => $loan->amount,
                'currency_code' => $loan->currency,
                'interest_rate' => $loan->interest_rate,
                'processing_fee' => 0,
                'monthly_interest_rate' => $loan->monthly_interest ?? ($loan->interest_rate / 12),
                'term' => $loan->duration_months,
                'tenor' => strtolower($loan->payment_frequency ?? '') === 'monthly' ? 'Months' : 'ដង',
                'payment_method' => $loan->repayment_method,
                'loan_cycle' => $loan->loan_cycle,
                'refinance_amount' => $loan->refinanced_amount ?? 0,
                'restructure' => $isRescheduled ? 1 : 0,
                'admin_fee' => $loan->admin_fee,
                'refinance_fee' => $loan->refinance_fee,
                'collateral_type' => $collateralType,
                'co_disburse' => $loan->disburseOfficer ? $loan->disburseOfficer->name : ($officer ? $officer->name : ''),
                'co_repay' => $officer ? $officer->name : '',
                'officer_name' => $officer ? $officer->name : 'N/A',
                'loan_product' => $loanProduct,
                'product_name' => $loanProduct,
                'customer_code' => $borrower ? $borrower->customer_code : 'N/A',

                'outstanding_amount' => $outstanding,
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,

                // Dates
                'maturity_date' => $maturityDate,
                'aging_days' => $agingDays,
                'overdue_amount' => $overdueAmount,

                'sector_name' => $loan->sector ?? 'General',
                'first_repayment_date' => $firstRepaymentDate,
                'last_payment_date' => $lastPaymentDate,

                'account_status' => 'active',
                'account_rating' => $this->getAccountRating($agingDays),
                'short_long_term' => $loan->duration_months > 12 ? 'Long Term' : 'Short Term',
                'secure_loan_type' => $loan->collaterals->isNotEmpty() ? 'Secured' : 'Unsecured',
                'provision_amount' => $outstanding * $this->getProvisionRate($agingDays),
            ];
        })->filter()->values();

        return response()->json($data);
    }

    private function getAccountRating($days)
    {
        if ($days <= 30)
            return 'Standard';
        if ($days <= 89)
            return 'Special Mention';
        if ($days <= 179)
            return 'Substandard';
        if ($days <= 359)
            return 'Doubtful';
        return 'Loss';
    }

    private function getProvisionRate($days)
    {
        if ($days <= 30)
            return 0.01;
        if ($days <= 89)
            return 0.03;
        if ($days <= 179)
            return 0.20;
        if ($days <= 359)
            return 0.50;
        return 1.00;
    }
}
