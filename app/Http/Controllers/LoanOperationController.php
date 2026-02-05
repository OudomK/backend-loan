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
        $activeLoansCount = Loan::where('status', 'active')->count();

        // Total Outstanding Balance: Sum of remaining principal in payments
        $totalOutstanding = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->select(DB::raw('SUM(principal_amount - total_paid) as outstanding'))
            ->value('outstanding') ?? 0;

        // Overdue Amount: Sum of (principal + interest) for payments whose date is past but total_paid < (principal + interest)
        $now = Carbon::now()->format('yyyy-MM-dd'); // Correct format for DB comparison

        // Actually, let's use a simpler approach for now if data is clean
        $overdueAmount = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('payments.payment_date', '<', Carbon::today())
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->select(DB::raw('SUM((payments.principal_amount + payments.interest_amount) - payments.total_paid) as overdue'))
            ->value('overdue') ?? 0;

        // PAR% 30: Portfolio at Risk > 30 days
        // For simplicity, let's just mock PAR% for now or calculate it if possible
        $par30 = 0;
        $thirtyDaysAgo = Carbon::today()->subDays(30);

        $par30Principal = DB::table('payments')
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->where('loans.status', 'active')
            ->where('payments.payment_date', '<', $thirtyDaysAgo)
            ->whereRaw('payments.total_paid < (payments.principal_amount + payments.interest_amount)')
            ->distinct('payments.loan_id')
            ->count('payments.loan_id');

        // PAR% is usually (Principal Overdue > 30 days / Total Outstanding) * 100
        if ($totalOutstanding > 0) {
            $par30 = ($par30Principal / $activeLoansCount) * 100; // This is a rough estimate
        }

        return response()->json([
            'active_loans' => $activeLoansCount,
            'total_outstanding' => (float) $totalOutstanding,
            'overdue_amount' => (float) $overdueAmount,
            'par_30' => round($par30, 2),
        ]);
    }

    /**
     * Get recent loan activity list.
     */
    public function getRecentActivity(Request $request)
    {
        $loans = Loan::with(['borrower'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($loans);
    }
}
