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
        $refDate = $reportDateStr ? Carbon::parse($reportDateStr) : Carbon::today();
        $refDateStr = $refDate->toDateString();

        // Query loans disbursed on or before the report date
        $query = Loan::with(['officer'])
            ->where('start_date', '<=', $refDateStr);

        // Calculate paid principal and earliest arrear date dynamically up to the report date
        $query->addSelect([
            'total_principal_paid' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, LEAST(principal_amount, total_paid - interest_amount)))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<=', $refDateStr),

            // A scheduled payment expected BEFORE the ref date that wasn't fully paid
            'earliest_arrear_date' => \App\Models\Payment::select('payment_date')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                ->orderBy('payment_date', 'asc')
                ->limit(1),
        ]);

        $loans = $query->get();

        $groupedData = [];

        foreach ($loans as $loan) {
            $principalPaid = $loan->total_principal_paid ?? 0;
            $outstanding = $loan->amount - $principalPaid;

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
            $agingDays = 0;
            if ($loan->earliest_arrear_date) {
                $agingDays = $refDate->diffInDays(Carbon::parse($loan->earliest_arrear_date));
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
