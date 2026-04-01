<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoanOutstandingParReportController extends Controller
{
    public function index(Request $request)
    {
        // Support either 'date' or 'report_date'
        $reportDateStr = $request->query('date') ?? $request->query('report_date');
        $refDate = $reportDateStr ? Carbon::parse($reportDateStr)->endOfDay() : Carbon::today()->endOfDay();
        $refDateStr = $refDate->toDateString();
        $refDateTime = $refDate->toDateTimeString();

        $loans = Loan::with([
            'payments' => function ($query) {
                $query->orderBy('payment_date', 'asc');
            },
            'transactions' => function ($query) use ($refDateTime) {
                $query->where('transaction_date', '<=', $refDateTime);
            },
        ])
            ->where('start_date', '<=', $refDateStr)
            ->where('status', '!=', 'pending')
            ->where(function ($query) use ($refDateStr) {
                $query->whereNull('written_off_at')
                    ->orWhereDate('written_off_at', '>', $refDateStr);
            })
            ->get();

        $groupedData = [];

        foreach ($loans as $loan) {
            $transactionsAtDate = $loan->transactions;

            $principalPaid = $transactionsAtDate->sum(function ($transaction) {
                return (float) ($transaction->principal_paid ?? 0)
                    + (float) ($transaction->prepayment_paid ?? 0)
                    + (float) ($transaction->paid_off_amount ?? 0)
                    - (float) ($transaction->withdrawn_prepayment ?? 0);
            });

            $outstanding = max(0, (float) $loan->amount - $principalPaid);

            // If fully paid off by this exact date, skip
            if ($outstanding <= 0.01) {
                continue;
            }

            // Since there is no 'Branch' feature yet, we aggregate all loans into a single record.
            $coName = 'Main Branch';

            if (!isset($groupedData[$coName])) {
                $groupedData[$coName] = [
                    'co_name' => $coName,
                    'usd_loan_os' => 0.0,
                    'khr_loan_os' => 0.0,
                    'total_loan_os' => 0.0,
                    'par_usd_amount' => 0.0,
                    'par_khr_amount' => 0.0,
                    'par_total_amount' => 0.0,
                    'npl_amount' => 0.0,
                    'active_loan_count' => 0,
                    'par1_count' => 0,
                    'par_lte_30_count' => 0,
                    'par_gt_30_count' => 0,
                ];
            }

            $group = &$groupedData[$coName];
            $currency = strtoupper($loan->currency);

            $group['active_loan_count'] += 1;

            // Standardizing a fixed exchange rate for total USD conversion projection (e.g. 4000 KHR = 1 USD). 
            // In a full production env, this might join against a daily exchange rates table.
            $exchangeRate = 4000;
            $convertedToUsd = 0;

            if (str_contains($currency, 'USD')) {
                $group['usd_loan_os'] += $outstanding;
                $convertedToUsd = $outstanding;
            } elseif (str_contains($currency, 'KHR')) {
                $group['khr_loan_os'] += $outstanding;
                $convertedToUsd = $outstanding / $exchangeRate;
            }

            $group['total_loan_os'] += $convertedToUsd;

            // PAR & NPL Calculation
            $scheduledPaidAtDate = $transactionsAtDate->sum(function ($transaction) {
                return (float) ($transaction->fee_paid ?? 0)
                    + (float) ($transaction->interest_paid ?? 0)
                    + (float) ($transaction->principal_paid ?? 0)
                    + (float) ($transaction->paid_off_amount ?? 0);
            });

            $earliestArrearDate = null;
            $cumulativeDue = 0.0;

            foreach ($loan->payments as $payment) {
                if ($payment->payment_date >= $refDateStr) {
                    continue;
                }

                $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                    + (float) ($payment->interest_amount ?? 0)
                    + (float) ($payment->fee_amount ?? 0);

                if (($cumulativeDue - $scheduledPaidAtDate) > 0.01) {
                    $earliestArrearDate = $payment->payment_date;
                    break;
                }
            }

            $agingDays = 0;
            if ($earliestArrearDate) {
                $agingDays = $refDate->copy()->startOfDay()->diffInDays(
                    Carbon::parse($earliestArrearDate)->startOfDay()
                );
            }

            if ($agingDays > 0) {
                if (str_contains($currency, 'USD')) {
                    $group['par_usd_amount'] += $outstanding;
                } elseif (str_contains($currency, 'KHR')) {
                    $group['par_khr_amount'] += $outstanding;
                }

                $group['par_total_amount'] += $convertedToUsd;

                // Buckets
                if ($agingDays == 1) {
                    $group['par1_count'] += 1;
                } elseif ($agingDays <= 30) {
                    $group['par_lte_30_count'] += 1;
                } else {
                    $group['par_gt_30_count'] += 1;
                    $group['npl_amount'] += $convertedToUsd; // standard NPL > 30 days
                }
            }
        }

        $result = [];
        foreach ($groupedData as $group) {
            $totalOs = $group['total_loan_os'];

            $group['par_percent'] = $totalOs > 0 ? ($group['par_total_amount'] / $totalOs) * 100 : 0.0;
            $group['npl_percent'] = $totalOs > 0 ? ($group['npl_amount'] / $totalOs) * 100 : 0.0;

            $result[] = $group;
        }

        // Sort by CO Name alphabetically as a nice default
        usort($result, function ($a, $b) {
            return strcmp($a['co_name'], $b['co_name']);
        });

        return response()->json($result);
    }
}
