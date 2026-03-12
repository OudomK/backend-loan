<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanOperationController extends Controller
{
    /**
     * Get dashboard statistics for Loan Operation.
     */
    public function getStats()
    {
        $exchangeRate = (int) (\App\Models\Setting::where('key', 'exchange_rate')->value('value') ?? 4000);

        $activeLoansCount = Loan::where('status', 'active')->count();

        // Outstanding from schedule: sum of (principal - principal_paid) per payment row.
        $outstandingUSD = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'USD%')
            ->select(DB::raw('SUM(GREATEST(0, principal_amount - GREATEST(0, total_paid - interest_amount))) as outstanding'))
            ->value('outstanding') ?? 0;

        $outstandingKHR = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'KHR%')
            ->select(DB::raw('SUM(GREATEST(0, principal_amount - GREATEST(0, total_paid - interest_amount))) as outstanding'))
            ->value('outstanding') ?? 0;

        // Active loans with no payment schedule yet: full loan amount counts as outstanding.
        $noScheduleUSD = Loan::where('status', 'active')
            ->where('currency', 'LIKE', 'USD%')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('payments')->whereColumn('payments.loan_id', 'loans.id');
            })
            ->sum('amount');
        $noScheduleKHR = Loan::where('status', 'active')
            ->where('currency', 'LIKE', 'KHR%')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('payments')->whereColumn('payments.loan_id', 'loans.id');
            })
            ->sum('amount');

        $outstandingUSD += $noScheduleUSD;
        $outstandingKHR += $noScheduleKHR;

        $totalOutstanding = $outstandingUSD + ($outstandingKHR / $exchangeRate);

        // "Today" in business timezone so overdue is correct (Cambodia UTC+7).
        $today = Carbon::today('Asia/Phnom_Penh');

        // Overdue = sum of (due - total_paid) for payments with payment_date < today and unpaid
        $overdueUSD = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'USD%')
            ->where('payments.payment_date', '<', $today)
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->select(DB::raw('SUM((payments.principal_amount + payments.interest_amount) - payments.total_paid) as overdue'))
            ->value('overdue') ?? 0;

        $overdueKHR = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'KHR%')
            ->where('payments.payment_date', '<', $today)
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->select(DB::raw('SUM((payments.principal_amount + payments.interest_amount) - payments.total_paid) as overdue'))
            ->value('overdue') ?? 0;

        $overdueAmount = $overdueUSD + ($overdueKHR / $exchangeRate);

        // PAR% 30 = (outstanding balance of loans with any payment overdue > 30 days) / total_outstanding * 100
        $thirtyDaysAgo = $today->copy()->subDays(30);
        $loanIdsOverdue30 = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('payments.payment_date', '<', $thirtyDaysAgo)
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->distinct()
            ->pluck('loans.id')
            ->unique()
            ->values();

        $par30AmountUSD = 0.0;
        $par30AmountKHR = 0.0;
        if ($loanIdsOverdue30->isNotEmpty()) {
            $par30AmountUSD = DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereIn('loans.id', $loanIdsOverdue30)
                ->where('loans.currency', 'LIKE', 'USD%')
                ->select(DB::raw('SUM(GREATEST(0, principal_amount - GREATEST(0, total_paid - interest_amount))) as outstanding'))
                ->value('outstanding') ?? 0;
            $par30AmountKHR = DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereIn('loans.id', $loanIdsOverdue30)
                ->where('loans.currency', 'LIKE', 'KHR%')
                ->select(DB::raw('SUM(GREATEST(0, principal_amount - GREATEST(0, total_paid - interest_amount))) as outstanding'))
                ->value('outstanding') ?? 0;
        }
        $par30PrincipalAmount = $par30AmountUSD + ($par30AmountKHR / $exchangeRate);
        $par30 = ($totalOutstanding > 0) ? round(($par30PrincipalAmount / $totalOutstanding) * 100, 2) : 0;

        return response()->json([
            'active_loans' => $activeLoansCount,
            'total_outstanding' => round($totalOutstanding, 2),
            'outstanding_usd' => round($outstandingUSD, 2),
            'outstanding_khr' => round($outstandingKHR, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_usd' => round($overdueUSD, 2),
            'overdue_khr' => round($overdueKHR, 2),
            'par_30' => round($par30, 2),
        ]);
    }

    /**
     * Get recent loan activity list.
     */
    public function getRecentActivity(Request $request)
    {
        $loans = Loan::with(['borrower'])
            ->where('status', '!=', 'completed')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($loans);
    }
}
