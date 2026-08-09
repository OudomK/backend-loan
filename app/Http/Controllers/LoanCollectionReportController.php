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
                'borrowers.customer_code as customer_code',
                'borrowers.first_name as borrower_first',
                'borrowers.last_name as borrower_last',
                'borrowers.phone',
                'borrowers.village',
                'borrowers.commune',
                'loan_officers.name as officer_name',
            ])
            ->whereNull('repayment_transactions.deleted_at')
            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
            ->whereNull('loans.deleted_at')
            ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->leftJoin('loan_officers', 'repayment_transactions.collector_id', '=', 'loan_officers.id');

        $query->whereBetween('repayment_transactions.transaction_date', [
            $fromDate->toDateTimeString(),
            $toDate->toDateTimeString(),
        ]);

        if ($currency && $currency !== 'all') {
            $query->where('loans.currency', $currency);
        }

        // Order by Borrower then Loan Cycle
        $query->orderBy('borrowers.id', 'desc')
              ->orderBy('loans.id', 'desc');

        $paginate = filter_var($request->query('paginate', 'true'), FILTER_VALIDATE_BOOLEAN);
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 50);

        $totalRecords = $query->count();
        
        $grandTotals = [];
        $allData = clone $query;
        foreach ($allData->get() as $item) {
            $curr = strtoupper(explode(' ', (string) ($item->currency ?? 'USD'))[0]);
            if (!isset($grandTotals[$curr])) {
                $grandTotals[$curr] = [
                    'principal' => 0,
                    'interest' => 0,
                    'penalty' => 0,
                    'fee' => 0,
                    'total' => 0,
                ];
            }
            $grandTotals[$curr]['principal'] += (float) ($item->principal_paid ?? 0);
            $grandTotals[$curr]['interest'] += (float) ($item->interest_paid ?? 0);
            $grandTotals[$curr]['penalty'] += (float) ($item->penalty_paid ?? 0);
            $grandTotals[$curr]['fee'] += (float) ($item->fee_paid ?? 0);
            $grandTotals[$curr]['total'] += round(
                (float) ($item->amount_paid ?? 0)
                    + (float) ($item->penalty_paid ?? 0)
                    + (float) ($item->fee_paid ?? 0),
                2
            );
        }

        if ($paginate) {
            $query->skip(($page - 1) * $limit)->take($limit);
            $lastPage = (int) ceil($totalRecords / $limit);
        } else {
            $lastPage = 1;
        }

        $results = $query->get()->map(function ($row) {
            return [
                'date' => $row->transaction_date,
                'loan_code' => \App\Support\FormatHelper::formatLoanCode((string) $row->loan_code),
                'cid' => $row->customer_code ?? '-',
                'name' => $row->borrower_first . ' ' . $row->borrower_last,
                'phone' => $row->phone,
                'co_name' => $row->officer_name,
                'village' => $row->village,
                'commune' => $row->commune,
                'principal' => $row->principal_paid,
                'interest' => $row->interest_paid,
                'penalty' => $row->penalty_paid,
                'fee' => $row->fee_paid,
                'total' => round(
                    (float) $row->amount_paid
                        + (float) $row->penalty_paid
                        + (float) $row->fee_paid,
                    2
                ),
                'currency' => $row->currency,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'current_page' => $page ?? 1,
                'last_page' => isset($lastPage) && $lastPage > 0 ? $lastPage : 1,
                'total' => $totalRecords ?? count($results),
                'grand_totals' => $grandTotals ?? []
            ]
        ]);
    }

    public function exportExcel(Request $request)
    {
        $request->merge(['paginate' => 'false']);
        $response = $this->index($request);
        $data = json_decode($response->getContent(), true);

        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $export = new \App\Exports\Excel\LoanCollectionExcelExport();
        return $export->download(
            $data['data'] ?? [],
            $request,
            $fromDateInput,
            $toDateInput,
            $currency
        );
    }
}
