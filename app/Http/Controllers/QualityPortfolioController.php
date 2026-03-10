<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanOfficer;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RepaymentTransaction;

class QualityPortfolioController extends Controller
{
    public function index(Request $request)
    {
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $toDate = $toDateStr ? Carbon::parse($toDateStr) : Carbon::today();
        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr) : $toDate->copy()->startOfMonth();

        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();

        // 1. Get all combinations of Loan Officer and Product from existing loans
        $combinations = Loan::with(['officer', 'product'])
            ->select('loan_officer_id', 'product_id')
            ->whereNotNull('loan_officer_id')
            ->groupBy('loan_officer_id', 'product_id')
            ->get();

        $reportData = [];

        foreach ($combinations as $combo) {
            $officer = $combo->officer;
            $product = $combo->product;

            if (!$officer)
                continue;

            try {
                // Get loans for this officer and product
                $baseQuery = Loan::where('loan_officer_id', $officer->id)
                    ->where('product_id', $combo->product_id);

                if ($currency !== 'all') {
                    $baseQuery->where('currency', $currency);
                }

                // --- Skip if no loans exist for this combo in the selected currency ---
                if ((clone $baseQuery)->count() === 0)
                    continue;

                // --- 1. No. Disb & Disb. Amount ---
                $oldDisb = (clone $baseQuery)->where('start_date', '<', $fromDateStr)->where('status', '!=', 'pending');
                $newDisb = (clone $baseQuery)->whereBetween('start_date', [$fromDateStr, $toDateStr]);

                $oldDisbCount = $oldDisb->count();
                $newDisbCount = $newDisb->count();
                $oldDisbAmount = $oldDisb->sum('amount') ?? 0;
                $newDisbAmount = $newDisb->sum('amount') ?? 0;

                // --- 2. Portfolio Size (End of Period) ---
                $activeAtEnd = (clone $baseQuery)->where('start_date', '<=', $toDateStr)
                    ->where(function ($q) {
                        $q->where('status', 'active')
                            ->orWhere('status', 'completed')
                            ->orWhere('status', 'paid_off');
                    });

                $totalClient = $activeAtEnd->count();

                $loanOS = 0;
                $interestOS = 0;

                $activeLoans = $activeAtEnd->get();
                foreach ($activeLoans as $loan) {
                    $totalPrincipalPaid = DB::table('payments')
                        ->where('loan_id', $loan->id)
                        ->where('payment_date', '<=', $toDateStr)
                        ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));

                    $loanOS += max(0, $loan->amount - ($totalPrincipalPaid ?? 0));

                    $interestOS += DB::table('payments')
                        ->where('loan_id', $loan->id)
                        ->where('payment_date', '<=', $toDateStr)
                        ->sum(DB::raw('GREATEST(0, interest_amount - total_paid)'));
                }

                // --- 3. Portfolio Mutation (This Period) ---
                $transactions = RepaymentTransaction::whereHas('loan', function ($q) use ($officer, $combo) {
                    $q->where('loan_officer_id', $officer->id)
                        ->where('product_id', $combo->product_id);
                })->whereBetween('transaction_date', [$fromDateStr, $toDateStr])->get();

                $collectedPrincipal = $transactions->sum('principal_paid') ?? 0;
                $collectedInterest = $transactions->sum('interest_paid') ?? 0;
                $penaltyCollected = $transactions->sum('penalty_paid') ?? 0;
                $paidOffCollected = $transactions->where('repayment_type', 'Pay Off')->sum('amount_paid') ?? 0;
                $recovery = $transactions->where('repayment_type', 'Recovery')->sum('amount_paid') ?? 0;

                $principalDue = DB::table('payments')
                    ->whereIn('loan_id', (clone $baseQuery)->pluck('id'))
                    ->whereBetween('payment_date', [$fromDateStr, $toDateStr])
                    ->sum('principal_amount') ?? 0;

                $interestDue = DB::table('payments')
                    ->whereIn('loan_id', (clone $baseQuery)->pluck('id'))
                    ->whereBetween('payment_date', [$fromDateStr, $toDateStr])
                    ->sum('interest_amount') ?? 0;

                // --- Write-Offs ---
                $woMonth = (clone $baseQuery)->whereNotNull('written_off_at')->whereBetween('written_off_at', [$fromDateStr, $toDateStr]);
                $noWoMonth = $woMonth->count();
                $principalWoMonth = $woMonth->sum('write_off_balance') ?? 0;

                $startOfYear = Carbon::parse($toDateStr)->startOfYear()->toDateString();
                $woYtd = (clone $baseQuery)->whereNotNull('written_off_at')->whereBetween('written_off_at', [$startOfYear, $toDateStr]);
                $noWoYtd = $woYtd->count();
                $principalWoYtd = $woYtd->sum('write_off_balance') ?? 0;

                // --- 4. PAR ---
                $par1Count = 0;
                $par1Amount = 0;
                $par30Count = 0;
                $par30Amount = 0;

                foreach ($activeLoans as $loan) {
                    $earliestArrear = DB::table('payments')
                        ->where('loan_id', $loan->id)
                        ->where('payment_date', '<', $toDateStr)
                        ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                        ->orderBy('payment_date', 'asc')
                        ->first();

                    if ($earliestArrear) {
                        $aging = Carbon::parse($toDateStr)->diffInDays(Carbon::parse($earliestArrear->payment_date));
                        $principalPaidAtEnd = DB::table('payments')
                            ->where('loan_id', $loan->id)
                            ->where('payment_date', '<=', $toDateStr)
                            ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));
                        $currentOS = max(0, $loan->amount - ($principalPaidAtEnd ?? 0));

                        if ($aging >= 1) {
                            $par1Count++;
                            $par1Amount += $currentOS;
                        }
                        if ($aging >= 30) {
                            $par30Count++;
                            $par30Amount += $currentOS;
                        }
                    }
                }

                $reportData[] = [
                    'co_code' => $officer->id,
                    'co_name' => $officer->name,
                    'product_name' => $product ? $product->name : 'General Loan',
                    'no_disb_old' => $oldDisbCount,
                    'no_disb_new' => $newDisbCount,
                    'no_disb_total' => $oldDisbCount + $newDisbCount,
                    'disb_amount_old' => $oldDisbAmount,
                    'disb_amount_new' => $newDisbAmount,
                    'disb_amount_total' => $oldDisbAmount + $newDisbAmount,
                    'disb_amount_extra' => $newDisbAmount,
                    'total_client' => $totalClient,
                    'loan_os' => $loanOS,
                    'interest_os' => $interestOS,
                    'fee_os' => 0,
                    'no_of_client' => $totalClient,
                    'principal_collected' => $collectedPrincipal,
                    'interest_collected' => $collectedInterest,
                    'fee_collected' => 0,
                    'penalty_collected' => $penaltyCollected,
                    'paid_off_collected' => $paidOffCollected,
                    'recovery' => $recovery,
                    'principal_due' => $principalDue,
                    'interest_due' => $interestDue,
                    'fee_due' => 0,
                    'total_arrears' => max(0, ($principalDue + $interestDue) - ($collectedPrincipal + $collectedInterest)),
                    'repayment_rate' => $principalDue > 0 ? (($collectedPrincipal / $principalDue) * 100) : 100,
                    'no_par_1' => $par1Count,
                    'amount_par_1' => $par1Amount,
                    'percent_par_1' => $loanOS > 0 ? ($par1Amount / $loanOS * 100) : 0,
                    'no_par_1_29' => $par1Count - $par30Count,
                    'amount_par_1_29' => $par1Amount - $par30Amount,
                    'percent_par_1_29' => $loanOS > 0 ? (($par1Amount - $par30Amount) / $loanOS * 100) : 0,
                    'no_par_30' => $par30Count,
                    'amount_par_30' => $par30Amount,
                    'percent_par_30' => $loanOS > 0 ? ($par30Amount / $loanOS * 100) : 0,
                    'no_wo_month' => $noWoMonth,
                    'principal_wo_month' => $principalWoMonth,
                    'interest_wo_month' => 0,
                    'fee_wo_month' => 0,
                    'no_wo_ytd' => $noWoYtd,
                    'principal_wo_ytd' => $principalWoYtd,
                    'interest_wo_ytd' => 0,
                    'fee_wo_ytd' => 0,
                ];
            } catch (\Exception $e) {
                Log::error("QualityPortfolio Error for CO {$officer->id} Product {$combo->product_id}: " . $e->getMessage());
            }
        }

        return response()->json($reportData);
    }
}
