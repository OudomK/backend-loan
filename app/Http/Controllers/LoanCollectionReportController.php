<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanCollectionReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $fromDate = $fromDateInput
            ? Carbon::parse($fromDateInput)->startOfDay()
            : Carbon::today()->startOfMonth()->startOfDay();
        $toDate = $toDateInput
            ? Carbon::parse($toDateInput)->endOfDay()
            : Carbon::today()->endOfDay();

        $query = \App\Models\RepaymentTransaction::query()
            ->select([
                'repayment_transactions.transaction_date',
                'repayment_transactions.principal_paid',
                'repayment_transactions.interest_paid',
                'repayment_transactions.penalty_paid',
                'repayment_transactions.fee_paid',
                'repayment_transactions.amount_paid',
                'loans.loan_code',
                'loans.currency',
                'borrowers.id as borrower_id',
                'borrowers.first_name as borrower_first',
                'borrowers.last_name as borrower_last',
                'borrowers.phone',
                'borrowers.village',
                'borrowers.commune',
                'loan_officers.name as officer_name',
            ])
            ->whereNull('repayment_transactions.deleted_at')
            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->leftJoin('loan_officers', 'repayment_transactions.collector_id', '=', 'loan_officers.id');

        $query->whereBetween('repayment_transactions.transaction_date', [
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString(),
        ]);

        if ($currency && $currency !== 'all') {
            $query->where('loans.currency', $currency);
        }

        // Order by Currency then Date then Loan Code
        $query->orderBy('loans.currency')
              ->orderBy('repayment_transactions.transaction_date')
              ->orderBy('loans.loan_code');

        $results = $query->get()->map(function ($row) {
            return [
                'date' => $row->transaction_date,
                'loan_code' => $row->loan_code,
                'cid' => $row->borrower_id,
                'name' => $row->borrower_last . ' ' . $row->borrower_first,
                'phone' => $row->phone,
                'co_name' => $row->officer_name,
                'village' => $row->village,
                'commune' => $row->commune,
                'principal' => $row->principal_paid,
                'interest' => $row->interest_paid,
                'penalty' => $row->penalty_paid,
                'fee' => $row->fee_paid,
                'total' => $row->amount_paid,
                'currency' => $row->currency,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }
}
