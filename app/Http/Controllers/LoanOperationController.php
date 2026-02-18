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
        $exchangeRate = 4000; // 1 USD = 4000 KHR

        $activeLoansCount = Loan::where('status', 'active')->count();

        // Total Outstanding Balance (convert KHR to USD)
        // Note: DB stores currency as 'USD ($)' and 'KHR (៛)'
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

        $totalOutstanding = $outstandingUSD + ($outstandingKHR / $exchangeRate);

        // Overdue Amount (convert KHR to USD)
        $overdueUSD = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'USD%')
            ->where('payments.payment_date', '<', Carbon::today())
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->select(DB::raw('SUM((payments.principal_amount + payments.interest_amount) - payments.total_paid) as overdue'))
            ->value('overdue') ?? 0;

        $overdueKHR = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('loans.currency', 'LIKE', 'KHR%')
            ->where('payments.payment_date', '<', Carbon::today())
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->select(DB::raw('SUM((payments.principal_amount + payments.interest_amount) - payments.total_paid) as overdue'))
            ->value('overdue') ?? 0;

        $overdueAmount = $overdueUSD + ($overdueKHR / $exchangeRate);

        // PAR% 30: Portfolio at Risk > 30 days
        $thirtyDaysAgo = Carbon::today()->subDays(30);

        $par30Principal = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('payments.payment_date', '<', $thirtyDaysAgo)
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->distinct('payments.loan_id')
            ->count('payments.loan_id');

        // PAR% is usually (Principal Overdue > 30 days / Total Outstanding) * 100
        $par30 = 0;
        if ($totalOutstanding > 0) {
            $par30 = ($par30Principal / $activeLoansCount) * 100; // This is a rough estimate
        }

        return response()->json([
            'active_loans' => $activeLoansCount,
            'total_outstanding' => round($totalOutstanding, 2), // Combined (optional)
            'outstanding_usd' => round($outstandingUSD, 2),
            'outstanding_khr' => round($outstandingKHR, 2),
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
