<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
        $disbursedUSD = Loan::where('currency', 'LIKE', 'USD%')->sum('amount');
        $disbursedKHR = Loan::where('currency', 'LIKE', 'KHR%')->sum('amount');
        $disbursedAmount = $disbursedUSD + ($disbursedKHR / $exchangeRate);

        // Outstanding = Total Principal - Total Principal Paid (per currency)
        $paidUSD = Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'USD%'))
            ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));
        $paidKHR = Payment::whereHas('loan', fn($q) => $q->where('currency', 'LIKE', 'KHR%'))
            ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));

        $outstandingUSD = $disbursedUSD - $paidUSD;
        $outstandingKHR = $disbursedKHR - $paidKHR;
        $outstandingAmount = $outstandingUSD + ($outstandingKHR / $exchangeRate);

        // PAR Calculation (Portfolio at Risk)
        $parLoans = Loan::where('status', 'active')
            ->whereHas('payments', function ($q) use ($referenceDate) {
                $q->where('payment_date', '<', $referenceDate->toDateString())
                    ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)');
            })
            ->get();

        $parAmount = 0;
        foreach ($parLoans as $loan) {
            $loanPrincipalPaid = $loan->payments()->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));
            $loanOutstanding = $loan->amount - $loanPrincipalPaid;

            // Convert to USD if KHR
            if (str_starts_with($loan->currency, 'KHR')) {
                $loanOutstanding = $loanOutstanding / $exchangeRate;
            }
            $parAmount += $loanOutstanding;
        }

        $parRatio = $outstandingAmount > 0 ? round(($parAmount / $outstandingAmount) * 100, 2) : 0;

        // Portfolio Quality Classification
        $portfolioQuality = $this->calculatePortfolioQuality($referenceDate, $exchangeRate);

        return response()->json([
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
            'disbursed_amount' => round($disbursedAmount, 2),
            'outstanding_amount' => round($outstandingAmount, 2),
            'par_amount' => round($parAmount, 2),
            'par_ratio' => $parRatio,
            'portfolio_quality' => $portfolioQuality,
        ]);
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
                    $classification['special_mention'] += $loanOutstanding;
                } elseif ($daysOverdue <= 60) {
                    $classification['substandard'] += $loanOutstanding;
                } elseif ($daysOverdue <= 90) {
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
