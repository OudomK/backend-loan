<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;

class InactiveLoanReportController extends Controller
{
    public function index(Request $request)
    {
        $officerId = $request->query('officer_id');
        $currency = $request->query('currency');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $statuses = ['completed', 'paid_off', 'written_off'];

        $query = Loan::with([
            'borrower' => function ($q) {
                $q->withTrashed();
            },
            'officer',
            'disburseOfficer',
            'collaterals',
            'product',
        ])
            ->whereIn('status', $statuses);

        if ($officerId && $officerId !== 'all') {
            $query->where('loan_officer_id', $officerId);
        }
        if ($currency && $currency !== 'all') {
            $query->where('currency', 'LIKE', $currency . '%');
        }

        $query->addSelect([
            'last_payment_date' => \App\Models\RepaymentTransaction::select('transaction_date')
                ->whereColumn('loan_id', 'loans.id')
                ->orderBy('transaction_date', 'desc')
                ->limit(1),
            'total_principal_paid' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, LEAST(principal_amount, total_paid - interest_amount)))')
                ->whereColumn('loan_id', 'loans.id'),
            'total_interest_paid' => \App\Models\Payment::selectRaw('SUM(LEAST(interest_amount, total_paid))')
                ->whereColumn('loan_id', 'loans.id'),
        ]);

        $loans = $query->orderBy('borrower_id', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($fromDate || $toDate) {
            $loans = $loans->filter(function ($loan) use ($fromDate, $toDate) {
                if (!$loan->last_payment_date) {
                    return false;
                }
                if ($fromDate && $loan->last_payment_date < $fromDate) {
                    return false;
                }
                if ($toDate && $loan->last_payment_date > $toDate) {
                    return false;
                }
                return true;
            });
        }

        $data = $loans->map(function ($loan) {
            $borrower = $loan->borrower;
            $officer = $loan->officer;
            $product = $loan->product;
            $inactiveDate = $loan->last_payment_date;

            return [
                'disbursement_date' => $loan->start_date,
                'loan_code' => \App\Support\FormatHelper::formatLoanCode((string) $loan->loan_code),
                'client_code' => $borrower?->customer_code ?? '',
                'client_name' => $borrower ? ($borrower->first_name . ' ' . $borrower->last_name) : '',
                'village_name' => $borrower?->village ?? '',
                'commune_name' => $borrower?->commune ?? '',
                'district_name' => $borrower?->district ?? '',
                'province_name' => $borrower?->province ?? '',
                'disbursement_amount' => $loan->amount,
                'currency_code' => $loan->currency,
                'interest_rate' => $loan->interest_rate,
                'monthly_interest_rate' => \App\Support\FormatHelper::calculateMonthlyRate(($loan->interest_rate ?? 0), $loan->payment_frequency),
                'term' => $loan->duration_months,
                'tenor' => $this->tenorLabel($loan->payment_frequency),
                'payment_method' => \App\Support\FormatHelper::formatPaymentMethod((string) $loan->repayment_method),
                'payment_frequency' => $loan->payment_frequency,
                'loan_cycle' => $loan->loan_cycle,
                'refinance_amount' => $loan->refinanced_amount ?? 0,
                'admin_fee' => $loan->admin_fee,
                'processing_fee' => 0,
                'refinance_fee' => $loan->refinance_fee,
                'loan_product' => $product ? $product->name : 'General Loan',
                'product_name' => $product ? $product->name : 'General Loan',
                'collateral_type' => $loan->collaterals->isNotEmpty() ? $loan->collaterals->first()->type : '',
                'co_disburse' => $loan->disburseOfficer ? $loan->disburseOfficer->name : ($officer ? $officer->name : ''),
                'co_repay' => $officer ? $officer->name : '',
                'outstanding_amount' => 0,
                'principal_paid' => in_array($loan->status, ['completed', 'paid_off']) ? $loan->amount : ($loan->total_principal_paid ?? 0),
                'interest_paid' => $loan->total_interest_paid ?? 0,
                'inactive_date' => $inactiveDate,
                'write_off_amount' => $loan->write_off_balance ?? 0,
            ];
        })->filter()->values()->toArray();

        $paginate = filter_var($request->query('paginate', 'true'), FILTER_VALIDATE_BOOLEAN);
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 50);

        if (!$paginate) {
            return response()->json($data);
        }

        $grandTotals = [];
        foreach ($data as $item) {
            $curr = strtoupper(explode(' ', (string) ($item['currency_code'] ?? 'USD'))[0]);
            if (!isset($grandTotals[$curr])) {
                $grandTotals[$curr] = [
                    'disbursement_amount' => 0,
                    'outstanding_amount' => 0,
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'write_off_amount' => 0,
                ];
            }
            $grandTotals[$curr]['disbursement_amount'] += (float) ($item['disbursement_amount'] ?? 0);
            $grandTotals[$curr]['outstanding_amount'] += (float) ($item['outstanding_amount'] ?? 0);
            $grandTotals[$curr]['principal_paid'] += (float) ($item['principal_paid'] ?? 0);
            $grandTotals[$curr]['interest_paid'] += (float) ($item['interest_paid'] ?? 0);
            $grandTotals[$curr]['write_off_amount'] += (float) ($item['write_off_amount'] ?? 0);
        }

        $totalRecords = count($data);
        $lastPage = (int) ceil($totalRecords / $limit);
        $offset = ($page - 1) * $limit;

        $paginatedData = array_slice($data, $offset, $limit);

        return response()->json([
            'data' => $paginatedData,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage > 0 ? $lastPage : 1,
                'total' => $totalRecords,
                'grand_totals' => $grandTotals
            ]
        ]);
    }

    public function exportExcel(Request $request)
    {
        $officerId = $request->query('officer_id');
        $currency = $request->query('currency');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Reuse index logic to fetch data
        $originalRequest = new Request([
            'officer_id' => $officerId,
            'currency' => $currency,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'paginate' => 'false'
        ]);

        $response = $this->index($originalRequest);
        $data = json_decode($response->getContent(), true);

        $officerName = 'ALL';
        if ($officerId && $officerId !== 'all') {
            $officer = \App\Models\User::find($officerId);
            if ($officer) {
                $officerName = $officer->name;
            }
        }

        $exporter = new \App\Exports\Excel\InactiveLoanExcelExport();
        return $exporter->download($data, $request, $fromDate, $toDate, $officerName);
    }

    private function tenorLabel(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        return match ($normalized) {
            'monthly' => 'Months',
            'biweekly' => 'Bi-weekly',
            'weekly' => 'Weeks',
            'daily' => 'Days',
            'term' => 'Installments',
            'bi-monthly', 'bimonthly', 'semi-monthly' => 'Semi-Monthly',
            default => $normalized !== '' ? ucwords(str_replace(['_', '-'], ' ', $normalized)) : '',
        };
    }
}
