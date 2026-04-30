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
        try {
            $rawRate = \App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
                ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value');
            $exchangeRate = max(1, (int) ($rawRate ?? 4000));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LoanOperation getStats: exchange_rate failed', ['error' => $e->getMessage()]);
            $exchangeRate = 4000;
        }

        try {
        // Exact same scope as getRecentActivity so stats match the table.
        $activeLoansCount = Loan::where('status', '!=', 'completed')->count();
        $disbursedUSD = (float) Loan::where('status', '!=', 'completed')->where('currency', 'LIKE', 'USD%')->sum('amount');
        $disbursedKHR = (float) Loan::where('status', '!=', 'completed')->where('currency', 'LIKE', 'KHR%')->sum('amount');

        $principalPaidUSD = 0.0;
        $principalPaidKHR = 0.0;
        try {
            $principalPaidUSD = (float) (DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereNull('payments.deleted_at')
                ->whereNull('loans.deleted_at')
                ->where('loans.status', '!=', 'completed')
                ->where('loans.currency', 'LIKE', 'USD%')
                ->selectRaw('COALESCE(SUM(LEAST(COALESCE(payments.principal_amount,0), GREATEST(0, COALESCE(payments.total_paid,0) - COALESCE(payments.interest_amount,0)))), 0) as paid')
                ->value('paid') ?? 0);
            $principalPaidKHR = (float) (DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereNull('payments.deleted_at')
                ->whereNull('loans.deleted_at')
                ->where('loans.status', '!=', 'completed')
                ->where('loans.currency', 'LIKE', 'KHR%')
                ->selectRaw('COALESCE(SUM(LEAST(COALESCE(payments.principal_amount,0), GREATEST(0, COALESCE(payments.total_paid,0) - COALESCE(payments.interest_amount,0)))), 0) as paid')
                ->value('paid') ?? 0);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LoanOperation getStats: principal paid query failed', ['error' => $e->getMessage()]);
        }

        $outstandingUSD = max(0, $disbursedUSD - $principalPaidUSD);
        $outstandingKHR = max(0, $disbursedKHR - $principalPaidKHR);

        $totalOutstanding = $outstandingUSD + ($outstandingKHR / $exchangeRate);

        $overdueUSD = 0.0;
        $overdueKHR = 0.0;
        $par30 = 0.0;
        try {
            $today = Carbon::today('Asia/Phnom_Penh');
            $overdueUSD = (float) (DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereNull('payments.deleted_at')
                ->whereNull('loans.deleted_at')
                ->where('loans.status', '!=', 'completed')
                ->where('loans.currency', 'LIKE', 'USD%')
                ->where('payments.payment_date', '<', $today)
                ->whereRaw('COALESCE(payments.total_paid,0) < (COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0))')
                ->selectRaw('COALESCE(SUM((COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0)) - COALESCE(payments.total_paid,0)), 0) as overdue')
                ->value('overdue') ?? 0);
            $overdueKHR = (float) (DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereNull('payments.deleted_at')
                ->whereNull('loans.deleted_at')
                ->where('loans.status', '!=', 'completed')
                ->where('loans.currency', 'LIKE', 'KHR%')
                ->where('payments.payment_date', '<', $today)
                ->whereRaw('COALESCE(payments.total_paid,0) < (COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0))')
                ->selectRaw('COALESCE(SUM((COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0)) - COALESCE(payments.total_paid,0)), 0) as overdue')
                ->value('overdue') ?? 0);
            $thirtyDaysAgo = $today->copy()->subDays(30);
            $loanIdsOverdue30 = DB::table('payments')
                ->join('loans', 'payments.loan_id', '=', 'loans.id')
                ->whereNull('payments.deleted_at')
                ->whereNull('loans.deleted_at')
                ->where('loans.status', '!=', 'completed')
                ->where('payments.payment_date', '<', $thirtyDaysAgo)
                ->whereRaw('COALESCE(payments.total_paid,0) < (COALESCE(payments.principal_amount,0) + COALESCE(payments.interest_amount,0))')
                ->distinct()
                ->pluck('loans.id')
                ->unique()
                ->values();
            $par30AmountUSD = 0.0;
            $par30AmountKHR = 0.0;
            if ($loanIdsOverdue30->isNotEmpty()) {
                $par30PrincipalPaidUSD = (float) (DB::table('payments')->join('loans', 'payments.loan_id', '=', 'loans.id')->whereNull('payments.deleted_at')->whereNull('loans.deleted_at')->whereIn('loans.id', $loanIdsOverdue30)->where('loans.currency', 'LIKE', 'USD%')->selectRaw('COALESCE(SUM(LEAST(COALESCE(payments.principal_amount,0), GREATEST(0, COALESCE(payments.total_paid,0)-COALESCE(payments.interest_amount,0)))), 0) as paid')->value('paid') ?? 0);
                $par30DisbursedUSD = (float) Loan::whereIn('id', $loanIdsOverdue30)->where('currency', 'LIKE', 'USD%')->sum('amount');
                $par30AmountUSD = max(0, $par30DisbursedUSD - $par30PrincipalPaidUSD);
                $par30PrincipalPaidKHR = (float) (DB::table('payments')->join('loans', 'payments.loan_id', '=', 'loans.id')->whereNull('payments.deleted_at')->whereNull('loans.deleted_at')->whereIn('loans.id', $loanIdsOverdue30)->where('loans.currency', 'LIKE', 'KHR%')->selectRaw('COALESCE(SUM(LEAST(COALESCE(payments.principal_amount,0), GREATEST(0, COALESCE(payments.total_paid,0)-COALESCE(payments.interest_amount,0)))), 0) as paid')->value('paid') ?? 0);
                $par30DisbursedKHR = (float) Loan::whereIn('id', $loanIdsOverdue30)->where('currency', 'LIKE', 'KHR%')->sum('amount');
                $par30AmountKHR = max(0, $par30DisbursedKHR - $par30PrincipalPaidKHR);
            }
            $par30PrincipalAmount = $par30AmountUSD + ($par30AmountKHR / $exchangeRate);
            $par30 = ($totalOutstanding > 0) ? round(($par30PrincipalAmount / $totalOutstanding) * 100, 2) : 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LoanOperation getStats: overdue/par30 failed', ['error' => $e->getMessage()]);
        }
        $overdueAmount = $overdueUSD + ($overdueKHR / $exchangeRate);

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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LoanOperation getStats failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // Fallback: try raw count/sum so we don't return all zeros
            try {
                $c = DB::table('loans')->whereNull('deleted_at')->where('status', '!=', 'completed')->count();
                $usd = (float) DB::table('loans')->whereNull('deleted_at')->where('status', '!=', 'completed')->where('currency', 'LIKE', 'USD%')->sum('amount');
                $khr = (float) DB::table('loans')->whereNull('deleted_at')->where('status', '!=', 'completed')->where('currency', 'LIKE', 'KHR%')->sum('amount');
                $total = $usd + ($khr / max(1, $exchangeRate));
                return response()->json([
                    'active_loans' => $c,
                    'total_outstanding' => round($total, 2),
                    'outstanding_usd' => round($usd, 2),
                    'outstanding_khr' => round($khr, 2),
                    'overdue_amount' => 0,
                    'overdue_usd' => 0,
                    'overdue_khr' => 0,
                    'par_30' => 0,
                ]);
            } catch (\Throwable $e2) {
                return response()->json([
                    'active_loans' => 0,
                    'total_outstanding' => 0,
                    'outstanding_usd' => 0,
                    'outstanding_khr' => 0,
                    'overdue_amount' => 0,
                    'overdue_usd' => 0,
                    'overdue_khr' => 0,
                    'par_30' => 0,
                ]);
            }
        }
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

        // Ensure admin_fee is in the response (each loan already has it; makeVisible if ever hidden)
        $loans->getCollection()->each(function ($loan) {
            $loan->makeVisible(['admin_fee']);
        });

        return response()->json($loans);
    }
}
