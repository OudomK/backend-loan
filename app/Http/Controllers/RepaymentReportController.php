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
                'loan' => function ($q) {
                    $q->withTrashed();
                },
                'loan.borrower' => function ($q) {
                    $q->withTrashed();
                },
                'loan.coBorrower' => function ($q) {
                    $q->withTrashed();
                },
                'loan.guarantor' => function ($q) {
                    $q->withTrashed();
                },
                'loan.officer' => function ($q) {
                    $q->withTrashed();
                },
                'loan.collaterals',
                'loan.product' => function ($q) {
                    $q->withTrashed();
                },
                'collector' => function ($q) {
                    $q->withTrashed();
                },
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


        // Transaction amounts are summed per repayment row.
        $totalsResult = (clone $query)->selectRaw('
            loans.currency as currency_value,
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
            ->groupBy('loans.currency')
            ->get();

        // Loan-level amounts must only be counted once even when a loan has
        // multiple repayment transactions in the selected period.
        $uniqueLoans = (clone $query)
            ->select([
                'loans.id as loan_id',
                'loans.currency as currency_value',
                'loans.amount as disb_amount',
                'loans.refinanced_amount as re_finance',
                'loans.refinance_fee as re_finance_fee',
            ])
            ->distinct()
            ->get();

        $grandTotals = [];
        foreach ($totalsResult as $row) {
            $currencyCode = $this->currencyCode($row->currency_value ?? 'USD');
            $grandTotals[$currencyCode] ??= $this->emptyGrandTotal();
            $grandTotals[$currencyCode]['principal_paid'] += (float) $row->principal_paid;
            $grandTotals[$currencyCode]['interest_paid'] += (float) $row->interest_paid;
            $grandTotals[$currencyCode]['penalty_paid'] += (float) $row->penalty_paid;
            $grandTotals[$currencyCode]['paid_off_paid'] += (float) $row->paid_off_paid;
            $grandTotals[$currencyCode]['recovery'] += (float) $row->recovery;
            $grandTotals[$currencyCode]['prepayment'] += (float) $row->prepayment;
            $grandTotals[$currencyCode]['withd_prepayment'] += (float) $row->withd_prepayment;
            $grandTotals[$currencyCode]['fee_paid'] += (float) $row->fee_paid;
            $grandTotals[$currencyCode]['total_paid'] += (float) $row->total_paid;
        }

        foreach ($uniqueLoans as $loan) {
            $currencyCode = $this->currencyCode($loan->currency_value ?? 'USD');
            $grandTotals[$currencyCode] ??= $this->emptyGrandTotal();
            $grandTotals[$currencyCode]['disb_amount'] += (float) $loan->disb_amount;
            $grandTotals[$currencyCode]['re_finance'] += (float) $loan->re_finance;
            $grandTotals[$currencyCode]['re_finance_fee'] += (float) $loan->re_finance_fee;
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

    private function currencyCode(?string $currency): string
    {
        return strtoupper(explode(' ', trim((string) ($currency ?: 'USD')))[0]);
    }

    private function emptyGrandTotal(): array
    {
        return [
            'disb_amount' => 0.0,
            're_finance' => 0.0,
            're_finance_fee' => 0.0,
            'principal_paid' => 0.0,
            'interest_paid' => 0.0,
            'penalty_paid' => 0.0,
            'paid_off_paid' => 0.0,
            'recovery' => 0.0,
            'prepayment' => 0.0,
            'withd_prepayment' => 0.0,
            'fee_paid' => 0.0,
            'total_paid' => 0.0,
        ];
    }
}
