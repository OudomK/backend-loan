<?php

namespace App\Http\Controllers;

use App\Exports\Excel\ArrearAllExcelExport;
use App\Exports\Excel\ArrearUnder30ExcelExport;
use App\Http\Resources\ArrearReportResource;
use App\Models\LoanOfficer;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArrearReportController extends Controller
{
    public function index(Request $request)
    {
        $officerId = $request->query('officer_id');
        $currency = $request->query('currency');
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $reportType = $request->query('report_type', 'under30');
        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr)->startOfDay() : null;
        $referenceDate = ($toDateStr ? Carbon::parse($toDateStr) : Carbon::today())->startOfDay();
        $refDateStr = $referenceDate->toDateString();

        // Arrears are installment-level: a row exists only while its scheduled
        // principal + interest + fee is due and not fully settled. Penalty is
        // deliberately not part of this inclusion condition.
        $query = Payment::query()
            ->with([
                'loan' => function ($loanQuery) {
                    $loanQuery->select([
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
                        'penalty_late_since_date',
                        'accumulated_penalty',
                        'penalty_rate',
                    ]);
                },
                'loan.borrower' => function ($borrowerQuery) {
                    $borrowerQuery->withTrashed()->select('id', 'customer_code', 'first_name', 'last_name', 'gender', 'phone', 'village', 'commune');
                },
                'loan.coBorrower' => function ($coBorrowerQuery) {
                    $coBorrowerQuery->withTrashed()->select('id', 'first_name', 'last_name', 'phone');
                },
                'loan.guarantor' => function ($guarantorQuery) {
                    $guarantorQuery->withTrashed()->select('id', 'first_name', 'last_name', 'phone');
                },
                'loan.officer:id,name',
                'loan.collaterals:id,loan_id,type,description',
            ])
            ->whereDate('payment_date', '<=', $refDateStr)
            ->whereRaw('total_paid < (principal_amount + interest_amount + COALESCE(fee_amount, 0) - 0.01)')
            ->whereHas('loan', function ($loanQuery) use ($officerId, $currency) {
                $loanQuery->where('status', 'active');

                if ($officerId && $officerId !== 'all') {
                    $loanQuery->where('loan_officer_id', $officerId);
                }

                if ($currency && $currency !== 'all') {
                    $loanQuery->where('currency', $currency);
                }
            });

        if ($reportType !== 'all') {
            // Period uses the selected From Date and remains capped at 30 DPD.
            $periodStart = $referenceDate->copy()->subDays(30);
            if ($fromDate && $fromDate->gt($periodStart)) {
                $periodStart = $fromDate;
            }
            $query->whereDate('payment_date', '>=', $periodStart->toDateString());
        }

        $query->addSelect([
            'payments.*',
            'calculated_outstanding' => DB::table('payments as loan_payments')
                ->selectRaw('SUM(GREATEST(0, principal_amount - GREATEST(0, GREATEST(0, total_paid - COALESCE(fee_paid, 0)) - interest_amount)))')
                ->whereColumn('loan_payments.loan_id', 'payments.loan_id')
                ->whereNull('loan_payments.deleted_at'),
            'last_transaction_date' => RepaymentTransaction::select('transaction_date')
                ->whereColumn('loan_id', 'payments.loan_id')
                ->orderByDesc('transaction_date')
                ->limit(1),
        ]);

        $payments = $query
            ->orderBy('payment_date')
            ->orderBy('loan_id')
            ->orderBy('payment_number')
            ->get();

        $mappedData = ArrearReportResource::collection($payments)->resolve($request);

        // A penalty belongs to the loan, not to every installment. Show it once
        // on that loan's oldest unpaid installment to avoid duplicate values.
        $penaltyByLoan = [];
        foreach ($payments as $payment) {
            $loan = $payment->loan;
            if (! $loan || isset($penaltyByLoan[$loan->id])) {
                continue;
            }

            $penaltyByLoan[$loan->id] = [
                'due' => $loan->currentPenaltyDue($referenceDate),
                'paid' => $loan->currentPeriodPenaltyCredits($referenceDate),
                'attached' => false,
            ];
        }

        if ($search !== '') {
            $searchText = mb_strtolower($search, 'UTF-8');
            $searchNumber = str_replace([',', ' '], '', $searchText);
            $searchFields = ['loan_no', 'name', 'village', 'commune'];

            $mappedData = array_values(array_filter($mappedData, function (array $item) use ($searchText, $searchNumber, $searchFields) {
                foreach ($searchFields as $field) {
                    if (str_contains(mb_strtolower((string) ($item[$field] ?? ''), 'UTF-8'), $searchText)) {
                        return true;
                    }
                }

                $amount = str_replace(',', '', number_format((float) ($item['disb_amount'] ?? 0), 2, '.', ','));

                return $searchNumber !== '' && str_contains($amount, $searchNumber);
            }));
        }

        if ($status !== '' && strtolower($status) !== 'all') {
            $mappedData = array_values(array_filter(
                $mappedData,
                fn (array $item) => strcasecmp((string) ($item['status'] ?? ''), $status) === 0
            ));
        }

        foreach ($mappedData as &$item) {
            $loanId = $item['loan_id'] ?? null;
            if ($loanId === null || ! isset($penaltyByLoan[$loanId]) || $penaltyByLoan[$loanId]['attached']) {
                continue;
            }

            $item['penalty_due'] = $penaltyByLoan[$loanId]['due'];
            $item['penalty_paid'] = $penaltyByLoan[$loanId]['paid'];
            $penaltyByLoan[$loanId]['attached'] = true;
        }
        unset($item);

        // Newly due installments first. Rows from the same date retain a stable
        // loan/payment ordering.
        usort($mappedData, function (array $left, array $right): int {
            $agingComparison = ((int) ($left['aging'] ?? 0)) <=> ((int) ($right['aging'] ?? 0));
            if ($agingComparison !== 0) {
                return $agingComparison;
            }

            $loanComparison = strnatcasecmp((string) ($right['loan_no'] ?? ''), (string) ($left['loan_no'] ?? ''));

            return $loanComparison !== 0
                ? $loanComparison
                : ((int) ($left['installment_no'] ?? 0)) <=> ((int) ($right['installment_no'] ?? 0));
        });

        $paginate = filter_var($request->query('paginate', true), FILTER_VALIDATE_BOOLEAN);
        if (! $paginate) {
            return $mappedData;
        }

        $grandTotals = [];
        $countedLoans = [];
        foreach ($mappedData as $item) {
            $curr = strtoupper(explode(' ', (string) ($item['currency'] ?? 'USD'))[0]);
            $loanId = (string) ($item['loan_id'] ?? '');
            if (! isset($grandTotals[$curr])) {
                $grandTotals[$curr] = [
                    'disb_amount' => 0,
                    'outstanding' => 0,
                    'arrear_amount' => 0,
                    'arrear_interest' => 0,
                    'arrear_fee' => 0,
                    'penalty_due' => 0,
                    'penalty_paid' => 0,
                ];
                $countedLoans[$curr] = [];
            }

            // Loan-level figures are shown on every installment but counted once.
            if (! isset($countedLoans[$curr][$loanId])) {
                $grandTotals[$curr]['disb_amount'] += (float) ($item['disb_amount'] ?? 0);
                $grandTotals[$curr]['outstanding'] += (float) ($item['outstanding'] ?? 0);
                $countedLoans[$curr][$loanId] = true;
            }

            $grandTotals[$curr]['arrear_amount'] += (float) ($item['arrear_amount'] ?? 0);
            $grandTotals[$curr]['arrear_interest'] += (float) ($item['arrear_interest'] ?? 0);
            $grandTotals[$curr]['arrear_fee'] += (float) ($item['arrear_fee'] ?? 0);
            $grandTotals[$curr]['penalty_due'] += (float) ($item['penalty_due'] ?? 0);
            $grandTotals[$curr]['penalty_paid'] += (float) ($item['penalty_paid'] ?? 0);
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = max(1, (int) $request->query('limit', 50));
        $totalRecords = count($mappedData);
        $lastPage = (int) ceil($totalRecords / $limit);
        $paginatedData = array_slice($mappedData, ($page - 1) * $limit, $limit);

        return response()->json([
            'data' => $paginatedData,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage > 0 ? $lastPage : 1,
                'total' => $totalRecords,
                'grand_totals' => $grandTotals,
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $request->merge(['report_type' => 'all', 'paginate' => false]);
        $data = $this->index($request);

        return (new ArrearAllExcelExport)->download(
            $data,
            $request,
            $request->query('to_date') ?? Carbon::today()->toDateString(),
            $request->query('currency', 'all'),
            $this->officerName($request->query('officer_id', 'all')),
        );
    }

    public function exportUnder30Excel(Request $request)
    {
        $request->merge(['report_type' => 'under30', 'paginate' => false]);
        $data = $this->index($request);

        return (new ArrearUnder30ExcelExport)->download(
            $data,
            $request,
            $request->query('to_date') ?? Carbon::today()->toDateString(),
            $request->query('currency', 'all'),
            $this->officerName($request->query('officer_id', 'all')),
        );
    }

    private function officerName(mixed $officerId): string
    {
        if (! $officerId || $officerId === 'all') {
            return 'ALL';
        }

        return LoanOfficer::find($officerId)?->name ?? 'ALL';
    }
}
