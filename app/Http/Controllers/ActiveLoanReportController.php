<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        // Query Active Loans
        $query = Loan::with([
            'borrower',
            'officer',
            'disburseOfficer',
            'collaterals',
            'product'
        ])
            ->where('status', 'active');

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

        // Add calculated fields using subqueries (similar to ArrearReport for performance)
        // 1. Total Paid Principal
        $query->addSelect([
            'total_principal_paid' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, LEAST(principal_amount, total_paid - interest_amount)))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<=', $refDateStr),

            'total_interest_paid' => \App\Models\Payment::selectRaw('SUM(LEAST(interest_amount, total_paid))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<=', $refDateStr),

            // Last Transaction Date
            'last_payment_date' => \App\Models\RepaymentTransaction::select('transaction_date')
                ->whereColumn('loan_id', 'loans.id')
                ->where('transaction_date', '<=', $refDateStr)
                ->orderBy('transaction_date', 'desc')
                ->limit(1),

            // Earliest Arrear Date (for Aging)
            'earliest_arrear_date' => \App\Models\Payment::select('payment_date')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                ->orderBy('payment_date', 'asc')
                ->limit(1),

            // Overdue Amount
            'total_overdue_amount' => \App\Models\Payment::selectRaw('SUM(principal_amount + interest_amount - total_paid)')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)'),

            // Check if rescheduled
            'is_rescheduled' => \App\Models\RepaymentTransaction::selectRaw('COUNT(*)')
                ->whereColumn('loan_id', 'loans.id')
                ->where('repayment_type', 'Reschedule'),
        ]);

        $loans = $query->get();

        // Transform Data
        $data = $loans->map(function ($loan) use ($refDate) {
            $borrower = $loan->borrower;
            $officer = $loan->officer;

            $product = $loan->product;

            // Calculations
            $principalPaid = $loan->total_principal_paid ?? 0;
            $outstanding = $loan->amount - $principalPaid;
            if ($outstanding < 0)
                $outstanding = 0;

            // Aging
            $agingDays = 0;
            if ($loan->earliest_arrear_date) {
                $agingDays = $refDate->diffInDays(Carbon::parse($loan->earliest_arrear_date));
            }

            // Collateral
            $collateralType = $loan->collaterals->isNotEmpty() ? $loan->collaterals->first()->type : '';

            // Formatting
            return [
                'disbursement_date' => $loan->start_date, // or formatted
                'loan_code' => $loan->loan_code,
                'client_name' => $borrower ? ($borrower->last_name . ' ' . $borrower->first_name) : '',
                // Address
                'village_name' => $borrower->village ?? '',
                'commune_name' => $borrower->commune ?? '',
                'district_name' => $borrower->district ?? '',
                'province_name' => $borrower->province ?? '',

                'disbursement_amount' => $loan->amount,
                'currency_code' => $loan->currency,
                'interest_rate' => $loan->interest_rate,
                'processing_fee' => 0,
                'monthly_interest_rate' => $loan->interest_rate / 12, // Approx
                'term' => $loan->duration_months,
                'tenor' => strtolower($loan->payment_frequency ?? '') === 'monthly' ? 'Months' : 'ដង',
                'payment_method' => $loan->repayment_method,
                'loan_cycle' => $loan->loan_cycle,
                'refinance_amount' => $loan->refinanced_amount ?? 0,
                'restructure' => $loan->is_rescheduled > 0 ? 1 : 0,
                'admin_fee' => $loan->admin_fee,
                'refinance_fee' => $loan->refinance_fee,
                'collateral_type' => $collateralType,
                'co_disburse' => $loan->disburseOfficer ? $loan->disburseOfficer->name : ($officer ? $officer->name : ''),
                'co_repay' => $officer ? $officer->name : '',
                'officer_name' => $officer ? $officer->name : 'N/A',
                'product_name' => $product ? $product->name : 'General Loan',
                'customer_code' => $borrower ? $borrower->customer_code : 'N/A',

                'outstanding_amount' => $outstanding,
                'principal_paid' => $principalPaid,
                'interest_paid' => $loan->total_interest_paid ?? 0,

                // Dates
                'maturity_date' => Carbon::parse($loan->start_date)->addMonths($loan->duration_months)->toDateString(),
                'aging_days' => $agingDays,
                'overdue_amount' => $loan->total_overdue_amount ?? 0,

                'sector_name' => 'General', // Placeholder
                'first_repayment_date' => Carbon::parse($loan->start_date)->addMonth()->toDateString(), // Approx
                'last_payment_date' => $loan->last_payment_date,

                'account_status' => $loan->status,
                'account_rating' => $this->getAccountRating($agingDays),
                'short_long_term' => $loan->duration_months > 12 ? 'Long Term' : 'Short Term',
                'secure_loan_type' => $loan->collaterals->isNotEmpty() ? 'Secured' : 'Unsecured',
                'provision_amount' => $outstanding * $this->getProvisionRate($agingDays),
            ];
        });

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
