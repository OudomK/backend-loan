<?php

namespace App\Http\Controllers;

use App\Http\Resources\LoanReportResource;
use App\Models\RepaymentTransaction;
use Illuminate\Http\Request;

class RepaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $officerId = $request->query('officer_id');

        $query = RepaymentTransaction::with([
            'loan.borrower',
            'loan.coBorrower',
            'loan.guarantor',
            'loan.officer',
            'loan.collaterals',
            'collector'
        ]);

        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('transaction_date', '<=', $toDate);
        }
        if ($officerId && $officerId !== 'all') {
            $query->where(function ($q) use ($officerId) {
                $q->where('collector_id', $officerId)
                    ->orWhereHas('loan', function ($sub) use ($officerId) {
                        $sub->where('loan_officer_id', $officerId);
                    });
            });
        }

        $currency = $request->query('currency');
        if ($currency && $currency !== 'all') {
            $query->whereHas('loan', function ($q) use ($currency) {
                $q->where('currency', 'LIKE', $currency . '%');
            });
        }

        $reports = $query->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->orderBy('repayment_transactions.transaction_date', 'desc')
            ->orderBy('borrowers.last_name', 'asc')
            ->orderBy('borrowers.first_name', 'asc')
            ->orderBy('repayment_transactions.id', 'desc')
            ->select('repayment_transactions.*')
            ->get();

        // Using resolve() to return a flat array for frontend compatibility (Export)
        return LoanReportResource::collection($reports)->resolve();
    }
}