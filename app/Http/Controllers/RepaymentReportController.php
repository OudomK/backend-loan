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


        $totalsQuery = clone $query;
        // Calculate totals group by currency
        $totalsResult = $totalsQuery->selectRaw('
            SUBSTRING_INDEX(loans.currency, " ", 1) as currency_code,
            SUM(loans.amount) as disb_amount,
            SUM(loans.refinanced_amount) as re_finance,
            SUM(loans.refinance_fee) as re_finance_fee,
            SUM(repayment_transactions.principal_paid) as principal_paid,
            SUM(repayment_transactions.interest_paid) as interest_paid,
            SUM(repayment_transactions.penalty_paid) as penalty_paid,
            SUM(repayment_transactions.paid_off_amount) as paid_off_paid,
            SUM(repayment_transactions.recovery_amount) as recovery,
            SUM(repayment_transactions.prepayment_paid) as prepayment,
            SUM(repayment_transactions.withdrawn_prepayment) as withd_prepayment,
            SUM(repayment_transactions.fee_paid) as fee_paid,
            SUM(
                CASE WHEN repayment_transactions.repayment_type = "Withdraw" 
                THEN -repayment_transactions.amount_paid 
                ELSE repayment_transactions.amount_paid 
                END 
                + repayment_transactions.penalty_paid 
                + repayment_transactions.fee_paid
            ) as total_paid
        ')
        ->groupBy('currency_code')
        ->get();

        $grandTotals = [];
        foreach ($totalsResult as $row) {
            $currency = strtoupper($row->currency_code ?? 'USD');
            $grandTotals[$currency] = [
                'disb_amount' => (float) $row->disb_amount,
                're_finance' => (float) $row->re_finance,
                're_finance_fee' => (float) $row->re_finance_fee,
                'principal_paid' => (float) $row->principal_paid,
                'interest_paid' => (float) $row->interest_paid,
                'penalty_paid' => (float) $row->penalty_paid,
                'paid_off_paid' => (float) $row->paid_off_paid,
                'recovery' => (float) $row->recovery,
                'prepayment' => (float) $row->prepayment,
                'withd_prepayment' => (float) $row->withd_prepayment,
                'fee_paid' => (float) $row->fee_paid,
                'total_paid' => (float) $row->total_paid,
            ];
        }

        $query->orderBy('repayment_transactions.transaction_date', 'desc')
            ->orderBy('repayment_transactions.id', 'desc')
            ->select('repayment_transactions.*');

        $isExport = $request->query('export') === 'excel';
        $noPaginate = $request->query('paginate') === 'false';

        if ($isExport || $noPaginate) {
            $reports = $query->get();
            $data = LoanReportResource::collection($reports)->resolve();

            if ($isExport) {
                return (new \App\Exports\Excel\RepaymentReportExcelExport())->download($data, $request);
            }

            return response()->json([
                'data' => $data,
                'meta' => [
                    'total' => count($data),
                    'grand_totals' => $grandTotals,
                ]
            ]);
        }

        $limit = $request->query('limit', 50);
        $paginator = $query->paginate($limit);
        
        $data = LoanReportResource::collection($paginator->items())->resolve();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'grand_totals' => $grandTotals
            ]
        ]);
    }
}
