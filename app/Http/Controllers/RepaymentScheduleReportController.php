<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepaymentScheduleReportController extends Controller
{
    public function index(Request $request)
    {
        [$query, $reportDate] = $this->buildScheduleQuery($request);
        $limit = min(max((int) $request->input('limit', 50), 1), 200);
        $page = max((int) $request->input('page', 1), 1);
        $grandTotals = $this->getGrandTotals(clone $query);

        $paginator = $this->selectScheduleColumns($query)
            ->orderBy('payments.payment_date', 'asc')
            ->orderBy('payments.id', 'asc')
            ->paginate($limit, ['*'], 'page', $page);

        $schedules = $this->transformSchedules(
            collect($paginator->items()),
            $reportDate
        );

        return response()->json([
            'success' => true,
            'data' => $schedules->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'grand_totals' => $grandTotals,
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $schedules = $this->getScheduleData($request);
        $export = new \App\Exports\Excel\RepaymentScheduleExcelExport;

        return $export->download($schedules->toArray(), $request);
    }

    public function exportPdf(Request $request)
    {
        $schedules = $this->getScheduleData($request);
        $export = new \App\Exports\Pdf\RepaymentSchedulePdfExport;

        return $export->download($schedules->toArray(), $request);
    }

    private function getScheduleData(Request $request): Collection
    {
        [$query, $reportDate] = $this->buildScheduleQuery($request);
        $schedules = $this->selectScheduleColumns($query)
            ->orderBy('payments.payment_date', 'asc')
            ->orderBy('payments.id', 'asc')
            ->get();

        return $this->transformSchedules($schedules, $reportDate);
    }

    private function buildScheduleQuery(Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $officerId = $request->input('officer_id');
        $currency = $request->input('currency');
        $reportDateInput = $request->input('report_date') ?: $endDate;
        $reportDate = $reportDateInput
            ? Carbon::parse($reportDateInput)->startOfDay()
            : Carbon::today();

        $query = Payment::query()
            ->join('loans', 'loans.id', '=', 'payments.loan_id')
            ->join('borrowers', 'borrowers.id', '=', 'loans.borrower_id')
            ->leftJoin('loan_officers', 'loan_officers.id', '=', 'loans.loan_officer_id')
            ->whereIn('loans.status', ['active', 'arrear'])
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount + COALESCE(payments.fee_amount, 0)) - 0.01')
            ->whereNull('payments.deleted_at')
            ->whereNull('loans.deleted_at');

        if ($startDate) {
            $query->where('payments.payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('payments.payment_date', '<=', $endDate);
        }
        if ($officerId && strtolower((string) $officerId) !== 'all') {
            $query->where('loans.loan_officer_id', $officerId);
        }
        if ($currency && strtolower((string) $currency) !== 'all') {
            if (str_contains(strtoupper((string) $currency), 'USD')) {
                $query->where('loans.currency', 'LIKE', 'USD%');
            } elseif (str_contains(strtoupper((string) $currency), 'KHR')) {
                $query->where('loans.currency', 'LIKE', 'KHR%');
            }
        }

        return [$query, $reportDate];
    }

    private function selectScheduleColumns(Builder $query): Builder
    {
        $collateralSql = DB::connection()->getDriverName() === 'sqlite'
            ? '(SELECT GROUP_CONCAT(type, ", ") FROM collaterals WHERE loan_id = loans.id) as collaterals'
            : '(SELECT GROUP_CONCAT(type SEPARATOR ", ") FROM collaterals WHERE loan_id = loans.id) as collaterals';

        return $query->select(
            'payments.id',
            'payments.payment_number',
            'payments.payment_date',
            'payments.principal_amount',
            'payments.interest_amount',
            'payments.outstanding_balance',
            'payments.fee_amount',
            'payments.total_due',
            'payments.penalty_amount',
            'payments.total_paid',
            'loans.loan_code',
            'loans.loan_cycle',
            DB::raw($collateralSql),
            'loans.currency',
            'loans.amount as loan_amount',
            'loans.duration_months',
            'loans.payment_frequency',
            'borrowers.first_name',
            'borrowers.last_name',
            'borrowers.phone',
            'borrowers.village',
            'borrowers.commune',
            'borrowers.district',
            'borrowers.province',
            'loan_officers.name as officer_name',
            DB::raw('((payments.principal_amount + payments.interest_amount + COALESCE(payments.fee_amount, 0)) - payments.total_paid) as remaining'),
            DB::raw('(SELECT COALESCE(SUM(p2.principal_amount), 0) FROM payments p2 WHERE p2.loan_id = payments.loan_id AND p2.payment_number <= payments.payment_number AND p2.deleted_at IS NULL) as cumulative_principal')
        );
    }

    private function getGrandTotals(Builder $query): array
    {
        $driver = DB::connection()->getDriverName();
        $fallbackOutstanding = $driver === 'sqlite'
            ? 'MAX(0, loans.amount - (SELECT COALESCE(SUM(p2.principal_amount), 0) FROM payments p2 WHERE p2.loan_id = payments.loan_id AND p2.payment_number <= payments.payment_number AND p2.deleted_at IS NULL))'
            : 'GREATEST(0, loans.amount - (SELECT COALESCE(SUM(p2.principal_amount), 0) FROM payments p2 WHERE p2.loan_id = payments.loan_id AND p2.payment_number <= payments.payment_number AND p2.deleted_at IS NULL))';
        $outstanding = "CASE WHEN payments.outstanding_balance IS NOT NULL AND payments.outstanding_balance >= 0 THEN payments.outstanding_balance ELSE {$fallbackOutstanding} END";
        $currencyCode = "CASE WHEN UPPER(loans.currency) LIKE 'KHR%' THEN 'KHR' ELSE 'USD' END";

        $rows = $query
            ->selectRaw("{$currencyCode} as currency_code")
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('SUM(loans.amount) as loan_amount')
            ->selectRaw("SUM({$outstanding}) as outstanding_balance")
            ->selectRaw('SUM(payments.principal_amount) as principal_amount')
            ->selectRaw('SUM(payments.interest_amount) as interest_amount')
            ->selectRaw('SUM(payments.total_due) as total_due')
            ->selectRaw('SUM(payments.total_paid) as total_paid')
            ->selectRaw('SUM((payments.principal_amount + payments.interest_amount + COALESCE(payments.fee_amount, 0)) - payments.total_paid) as remaining')
            ->groupByRaw($currencyCode)
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->currency_code] = [
                'item_count' => (int) $row->item_count,
                'loan_amount' => (float) $row->loan_amount,
                'outstanding_balance' => (float) $row->outstanding_balance,
                'principal_amount' => (float) $row->principal_amount,
                'interest_amount' => (float) $row->interest_amount,
                'total_due' => (float) $row->total_due,
                'total_paid' => (float) $row->total_paid,
                'remaining' => (float) $row->remaining,
            ];
        }

        return $totals;
    }

    private function transformSchedules(Collection $schedules, Carbon $reportDate): Collection
    {
        return $schedules->transform(function ($item) use ($reportDate) {
            if ($item->outstanding_balance !== null && (float) $item->outstanding_balance >= 0) {
                $item->outstanding_balance = round((float) $item->outstanding_balance, 2);
            } else {
                $item->outstanding_balance = round($item->loan_amount - $item->cumulative_principal, 2);
                if ($item->outstanding_balance < 0) {
                    $item->outstanding_balance = 0;
                }
            }

            $item->installment_display = $item->payment_number.'/'.$item->duration_months.' '.$this->installmentUnitLabel($item->payment_frequency);

            if ($item->loan_code) {
                $item->loan_code = \App\Support\FormatHelper::formatLoanCode((string) $item->loan_code);
            }
            if ($item->phone) {
                $item->phone = \App\Support\FormatHelper::formatPhoneNumber((string) $item->phone);
            }

            $paymentDate = Carbon::parse($item->payment_date)->startOfDay();
            $item->days_overdue = $paymentDate->lt($reportDate)
                ? $paymentDate->diffInDays($reportDate)
                : 0;

            $totalDue = (float) $item->principal_amount
                + (float) $item->interest_amount
                + (float) ($item->fee_amount ?? 0);
            $totalPaid = (float) ($item->total_paid ?? 0);

            if ($item->days_overdue > 0) {
                $item->payment_status = 'Overdue';
            } elseif ($totalPaid > 0.01 && $totalPaid < ($totalDue - 0.01)) {
                $item->payment_status = 'Partial';
            } elseif ($paymentDate->isSameDay($reportDate)) {
                $item->payment_status = 'Due';
            } else {
                $item->payment_status = 'Upcoming';
            }

            return $item;
        });
    }

    private function installmentUnitLabel(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        return match ($normalized) {
            'monthly' => 'Months',
            'biweekly' => 'Bi-Weekly',
            'weekly' => 'Weeks',
            'daily' => 'Days',
            'term' => 'Installments',
            default => 'Terms',
        };
    }
}
