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
            $query->whereHas('loan', function ($q) use ($officerId) {
                $q->where('loan_officer_id', $officerId);
            });
        }

        $reports = $query->orderBy('transaction_date', 'desc')->get();

        // Using resolve() to return a flat array for frontend compatibility (Export)
        return LoanReportResource::collection($reports)->resolve();
    }
}