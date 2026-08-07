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
            // Use 'active' status to match Dashboard stats exactly (exclude written_off, etc from portfolio metrics)
            $activeLoansCount = Loan::where('status', 'active')->count();
            $disbursedUSD = (float) Loan::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('amount');
            $disbursedKHR = (float) Loan::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('amount');

            $portfolioLoans = Loan::with([
                'payments' => function ($query) {
                    $query->orderBy('payment_date', 'asc');
                },
                'transactions' => function ($query) {
                    $query->where('transaction_date', '<=', Carbon::today('Asia/Phnom_Penh')->toDateString());
                },
            ])
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get();

            $outstandingUSD = 0.0;
            $outstandingKHR = 0.0;
            $overdueUSD = 0.0;
            $overdueKHR = 0.0;
            $par30AmountUSD = 0.0;
            $par30AmountKHR = 0.0;

            try {
                $today = Carbon::today('Asia/Phnom_Penh');

                foreach ($portfolioLoans as $loan) {
                    /** @var Loan $loan */
                    $snapshot = $this->portfolioSnapshot($loan, $today);
                    $currentOS = $snapshot['outstanding'];
                    if ($currentOS <= 0.01) {
                        continue;
                    }

                    if (str_starts_with((string) $loan->currency, 'KHR')) {
                        $outstandingKHR += $currentOS;
                        $overdueKHR += $snapshot['overdue_amount'];
                        if ($snapshot['aging'] >= 30) {
                            $par30AmountKHR += $currentOS;
                        }
                    } else {
                        $outstandingUSD += $currentOS;
                        $overdueUSD += $snapshot['overdue_amount'];
                        if ($snapshot['aging'] >= 30) {
                            $par30AmountUSD += $currentOS;
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('LoanOperation getStats: overdue/par30 failed', ['error' => $e->getMessage()]);
            }

            $totalOutstanding = $outstandingUSD + ($outstandingKHR / $exchangeRate);
            $par30PrincipalAmount = $par30AmountUSD + ($par30AmountKHR / $exchangeRate);
            $par30 = ($totalOutstanding > 0) ? round(($par30PrincipalAmount / $totalOutstanding) * 100, 2) : 0;
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
                $c = DB::table('loans')->whereNull('deleted_at')->where('status', 'active')->count();
                $usd = (float) DB::table('loans')->whereNull('deleted_at')->where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('amount');
                $khr = (float) DB::table('loans')->whereNull('deleted_at')->where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('amount');
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
            ->where('status', 'active')
            ->orderBy('borrower_id', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Ensure admin_fee is in the response (each loan already has it; makeVisible if ever hidden)
        $loans->transform(function (Loan $loan) {
            $loan->makeVisible(['admin_fee']);

            // Abbreviate Refinance and Reschedule for better display
            if ($loan->loan_code) {
                $loan->loan_code = str_ireplace(['-Refinanced', '-Rescheduled'], ['-RF', '-RS'], $loan->loan_code);
            }
            if ($loan->purpose) {
                $loan->purpose = str_ireplace(['Refinance', 'Reschedule'], ['RF', 'RS'], $loan->purpose);
            }

            return $loan;
        });

        return response()->json(['data' => $loans]);
    }

    /**
     * Export all active loans to Excel
     */
    public function exportExcel(Request $request)
    {
        $loans = Loan::with(['borrower'])
            ->where('status', '!=', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        $loans->each(function (Loan $loan) {
            $loan->makeVisible(['admin_fee']);
        });

        $export = new \App\Exports\Excel\LoanOperationExcelExport();
        return $export->download($loans, $request);
    }

    private function portfolioSnapshot(Loan $loan, Carbon $referenceDate): array
    {
        $transactionsAtDate = $loan->transactions ?? collect();

        $principalPaid = $transactionsAtDate->sum(function ($transaction) {
            return (float) ($transaction->principal_paid ?? 0)
                + (float) ($transaction->prepayment_paid ?? 0)
                + (float) ($transaction->paid_off_amount ?? 0)
                - (float) ($transaction->withdrawn_prepayment ?? 0);
        });

        $outstanding = max(0, $loan->getBasePrincipalForOS() - $principalPaid);
        if ($outstanding <= 0.01) {
            return ['outstanding' => 0.0, 'overdue_amount' => 0.0, 'aging' => 0];
        }

        $scheduledPaid = $transactionsAtDate->sum(function ($transaction) {
            return (float) ($transaction->fee_paid ?? 0)
                + (float) ($transaction->interest_paid ?? 0)
                + (float) ($transaction->principal_paid ?? 0)
                + (float) ($transaction->prepayment_paid ?? 0)
                + (float) ($transaction->paid_off_amount ?? 0)
                - (float) ($transaction->withdrawn_prepayment ?? 0);
        });

        $cumulativeDue = 0.0;
        $cumulativePrincipalDue = 0.0;
        $earliestArrearDate = null;
        $earliestPrincipalArrearDate = null;

        foreach ($loan->payments as $payment) {
            if (($payment->payment_date ?? '') >= $referenceDate->toDateString()) {
                continue;
            }

            $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                + (float) ($payment->interest_amount ?? 0)
                + (float) ($payment->fee_amount ?? 0);
            $cumulativePrincipalDue += (float) ($payment->principal_amount ?? 0);

            if (($cumulativeDue - $scheduledPaid) > 0.01 && $earliestArrearDate === null) {
                $earliestArrearDate = $payment->payment_date;
            }

            if (($cumulativePrincipalDue - $principalPaid) > 0.01 && $earliestPrincipalArrearDate === null) {
                $earliestPrincipalArrearDate = $payment->payment_date;
            }
        }

        $effectiveArrearDate = $earliestArrearDate ?? $earliestPrincipalArrearDate;
        $overdueAmount = max(0, $cumulativeDue - $scheduledPaid);
        $aging = 0;

        if ($effectiveArrearDate) {
            $aging = abs($referenceDate->copy()->startOfDay()->diffInDays(
                Carbon::parse($effectiveArrearDate)->startOfDay()
            ));
        }

        if ($aging <= 0 && $overdueAmount > 0.01) {
            $aging = 1;
        }

        return [
            'outstanding' => $outstanding,
            'overdue_amount' => $overdueAmount,
            'aging' => $loan->currentAging($referenceDate),
        ];
    }
}
