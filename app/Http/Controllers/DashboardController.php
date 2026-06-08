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
        $inactiveCustomers = $totalCustomers - $activeCustomers;

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

        foreach ($portfolioLoans as $loan) {
            /** @var \App\Models\Loan $loan */
            $snapshot = $this->portfolioSnapshot($loan, $referenceDate);
            $currentOS = $snapshot['outstanding'];
            if ($currentOS <= 0.01) {
                continue;
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

        // Portfolio Quality Classification
        $portfolioQuality = $this->calculatePortfolioQuality($referenceDate, $exchangeRate);

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

    private function calculatePortfolioQuality(Carbon $referenceDate, int $exchangeRate)
    {
        $loans = Loan::where('status', 'active')->get();

        $classification = [
            'standard' => 0,
            'special_mention' => 0,
            'substandard' => 0,
            'doubtful' => 0,
            'loss' => 0,
        ];

        foreach ($loans as $loan) {
            /** @var \App\Models\Loan $loan */
            // Find earliest overdue payment
            $earliestOverdue = $loan->payments()
                ->where('payment_date', '<', $referenceDate->toDateString())
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                ->orderBy('payment_date', 'asc')
                ->first();

            $loanPrincipalPaid = $loan->payments()->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));
            $loanOutstanding = $loan->amount - $loanPrincipalPaid;

            // Convert to USD if KHR
            if (str_starts_with($loan->currency, 'KHR')) {
                $loanOutstanding = $loanOutstanding / $exchangeRate;
            }

            if (!$earliestOverdue) {
                // No overdue = Standard
                $classification['standard'] += $loanOutstanding;
            } else {
                $daysOverdue = $referenceDate->diffInDays(Carbon::parse($earliestOverdue->payment_date));

                if ($daysOverdue <= 30) {
                    $classification['standard'] += $loanOutstanding;
                } elseif ($daysOverdue <= 89) {
                    $classification['special_mention'] += $loanOutstanding;
                } elseif ($daysOverdue <= 179) {
                    $classification['substandard'] += $loanOutstanding;
                } elseif ($daysOverdue <= 359) {
                    $classification['doubtful'] += $loanOutstanding;
                } else {
                    $classification['loss'] += $loanOutstanding;
                }
            }
        }

        // Round all values
        foreach ($classification as $key => $value) {
            $classification[$key] = round($value, 2);
        }

        return $classification;
    }
}
