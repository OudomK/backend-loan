<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArrearReportResource;
use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ArrearReportController extends Controller
{
    public function index(Request $request)
    {
        $officerId = $request->query('officer_id');
        $currency = $request->query('currency');
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $reportType = $request->query('report_type', 'under30');
        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr) : null;
        $referenceDate = $toDateStr ? Carbon::parse($toDateStr) : Carbon::today();
        $refDateStr = $referenceDate->toDateString();

        // 1. Build Query with SQL Subqueries for Performance
        $query = Loan::with([
            'borrower' => function($q) { $q->withTrashed()->select('id', 'customer_code', 'first_name', 'last_name', 'gender', 'phone', 'village', 'commune'); },
            'coBorrower' => function($q) { $q->withTrashed()->select('id', 'first_name', 'last_name', 'phone'); },
            'guarantor' => function($q) { $q->withTrashed()->select('id', 'first_name', 'last_name', 'phone'); },
            'officer:id,name',
            'collaterals:id,loan_id,type,description'
        ])
            ->select([
                'id',
                'loan_code',
                'borrower_id',
                'co_borrower_id',
                'guarantor_id',
                'loan_officer_id',
                'amount',
                'start_date',
                'status',
                'currency',
                'late_since_date',
                'aging',
                'penalty_rate'
            ])
            ->where('status', 'active')
            // Only get loans with overdue payments
            ->whereHas('payments', function ($pQuery) use ($refDateStr) {
                $pQuery->where('payment_date', '<', $refDateStr)
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)');
            });

        if ($officerId && $officerId !== 'all') {
            $query->where('loan_officer_id', $officerId);
        }

        if ($currency && $currency !== 'all') {
            $query->where('currency', $currency);
        }

        // Add subqueries for aggregate data
        $query->addSelect([
            // Total Outstanding Principal
            'calculated_outstanding' => \App\Models\Payment::selectRaw('SUM(principal_amount - GREATEST(0, total_paid - interest_amount))')
                ->whereColumn('loan_id', 'loans.id'),

            // Arrear Principal (Total unpaid principal for past due installments)
            'arrear_principal' => \App\Models\Payment::selectRaw('SUM(principal_amount - GREATEST(0, total_paid - interest_amount))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)'),

            // Arrear Interest
            'arrear_interest' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, interest_amount - total_paid))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr),

            // Penalty
            'arrear_penalty' => \App\Models\Payment::selectRaw('SUM(penalty_amount)')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr),

            // Last Payment Date (from transactions)
            'last_transaction_date' => \App\Models\RepaymentTransaction::select('transaction_date')
                ->whereColumn('loan_id', 'loans.id')
                ->orderBy('transaction_date', 'desc')
                ->limit(1),

            // Total penalty collected and waived for this loan
            'penalty_paid_total' => \App\Models\RepaymentTransaction::selectRaw('COALESCE(SUM(penalty_paid + waived_amount), 0)')
                ->whereColumn('loan_id', 'loans.id'),
        ]);

        $loans = $query->get();

        // 2. Filter by Aging in PHP (if necessary)
        $filtered = $loans->filter(function ($loan) use ($referenceDate, $reportType, $fromDate) {
            if (!$loan->late_since_date) {
                return false;
            }

            $arrearDate = Carbon::parse($loan->late_since_date);

            if ($reportType === 'all')
                return true;

            if ($fromDate && $arrearDate->lt($fromDate)) {
                return false;
            }

            $aging = abs($referenceDate->diffInDays($arrearDate));

            return $aging >= 1 && $aging <= 30;
        })->values();

        return ArrearReportResource::collection($filtered)->resolve();
    }
}
