<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $referenceDate = Carbon::today();
        $exchangeRate = (float) (\App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000);
        $exchangeRate = max(1, $exchangeRate);

        // Customer Stats
        $totalCustomers = Borrower::count();

        $activeCustomers = Borrower::whereHas('loans', function ($q) {
            $q->where('status', 'active');
        })->count();

        $inactiveCustomers = Borrower::whereHas('loans', function ($q) {
            $q->where('status', '!=', 'pending');
        })->whereDoesntHave('loans', function ($q) {
            $q->where('status', 'active');
        })->count();

        // Loan Amount Stats (convert KHR to USD)
        // Note: DB stores currency as 'USD ($)' and 'KHR (៛)'
        $disbursedUSD = Loan::where('status', 'active')->where('currency', 'LIKE', 'USD%')->sum('amount');
        $disbursedKHR = Loan::where('status', 'active')->where('currency', 'LIKE', 'KHR%')->sum('amount');
        $disbursedAmount = $disbursedUSD + ($disbursedKHR / $exchangeRate);

        $portfolioLoans = Loan::with([
            'payments' => function ($query) {
                $query->orderBy('payment_date', 'asc');
            },
            'transactions' => function ($query) use ($referenceDate) {
                $query->where('transaction_date', '<=', $referenceDate->toDateString());
            },
        ])->where('status', 'active')->get();

        $outstandingUSD = 0.0;
        $outstandingKHR = 0.0;
        $overdueUSD = 0.0;
        $overdueKHR = 0.0;
        $parAmount = 0.0;

        $portfolioQuality = [
            'standard' => 0,
            'special_mention' => 0,
            'substandard' => 0,
            'doubtful' => 0,
            'loss' => 0,
        ];

        foreach ($portfolioLoans as $loan) {
            /** @var \App\Models\Loan $loan */
            $snapshot = $this->portfolioSnapshot($loan, $referenceDate);
            $currentOS = $snapshot['outstanding'];
            if ($currentOS <= 0.01) {
                continue;
            }

            $convertedOS = str_starts_with((string) $loan->currency, 'KHR') ? $currentOS / $exchangeRate : $currentOS;
            $aging = $snapshot['aging'];

            if ($aging < 30) {
                $portfolioQuality['standard'] += $convertedOS;
            } elseif ($aging <= 89) {
                $portfolioQuality['special_mention'] += $convertedOS;
            } elseif ($aging <= 179) {
                $portfolioQuality['substandard'] += $convertedOS;
            } elseif ($aging <= 359) {
                $portfolioQuality['doubtful'] += $convertedOS;
            } else {
                $portfolioQuality['loss'] += $convertedOS;
            }

            if (str_starts_with((string) $loan->currency, 'KHR')) {
                $outstandingKHR += $currentOS;
                $overdueKHR += $snapshot['overdue_amount'];
                if ($snapshot['aging'] >= 30) {
                    $parAmount += $currentOS / $exchangeRate;
                }
            } else {
                $outstandingUSD += $currentOS;
                $overdueUSD += $snapshot['overdue_amount'];
                if ($snapshot['aging'] >= 30) {
                    $parAmount += $currentOS;
                }
            }
        }

        $outstandingAmount = $outstandingUSD + ($outstandingKHR / $exchangeRate);
        $overdueAmount = $overdueUSD + ($overdueKHR / $exchangeRate);
        $parRatio = $outstandingAmount > 0 ? round(($parAmount / $outstandingAmount) * 100, 2) : 0;

        // Round portfolio quality values
        foreach ($portfolioQuality as $key => $value) {
            $portfolioQuality[$key] = round($value, 2);
        }

        return response()->json([
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
            'disbursed_amount' => round($disbursedAmount, 2),
            'outstanding_amount' => round($outstandingAmount, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'par_amount' => round($parAmount, 2),
            'par_ratio' => $parRatio,
            'portfolio_quality' => $portfolioQuality,
        ]);
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

        $outstanding = max(0, (float) $loan->amount - $principalPaid);
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
            'aging' => $aging,
        ];
    }
}
