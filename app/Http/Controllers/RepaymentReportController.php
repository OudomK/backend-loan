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

        $query = RepaymentTransaction::query()
            ->with([
                'loan' => function($q) { $q->withTrashed(); },
                'loan.borrower' => function($q) { $q->withTrashed(); },
                'loan.coBorrower' => function($q) { $q->withTrashed(); },
                'loan.guarantor' => function($q) { $q->withTrashed(); },
                'loan.officer',
                'loan.collaterals',
                'loan.product',
                'collector'
            ])
            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->whereNull('loans.deleted_at');

        if ($fromDate) {
            $query->where('repayment_transactions.transaction_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('repayment_transactions.transaction_date', '<=', $toDate);
        }

        if ($officerId && $officerId !== 'all') {
            $query->where(function ($q) use ($officerId) {
                $q->where('repayment_transactions.collector_id', $officerId)
                    ->orWhere('loans.loan_officer_id', $officerId);
            });
        }

        $currency = $request->query('currency');
        if ($currency && $currency !== 'all') {
            $query->where('loans.currency', 'LIKE', $currency . '%');
        }

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $query->where('loans.status', $status);
        }

        $reports = $query->orderBy('repayment_transactions.transaction_date', 'desc')
            ->orderBy('repayment_transactions.id', 'desc')
            ->select('repayment_transactions.*')
            ->get();

        // Using resolve() to return a flat array for frontend compatibility (Export)
        return LoanReportResource::collection($reports)->resolve();
    }
}
