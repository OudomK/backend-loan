<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;
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

        // Group by officer + product + currency so mixed currencies never collapse
        // into one row when the user selects "all".
        $combinations = Loan::with(['officer', 'product'])
            ->select('loan_officer_id', 'product_id', 'currency')
            ->whereNotNull('loan_officer_id')
            ->when($currency !== 'all', function ($query) use ($currency) {
                $query->where('currency', $currency);
            })
            ->groupBy('loan_officer_id', 'product_id', 'currency')
            ->get();

        $reportData = [];

        foreach ($combinations as $combo) {
            $officer = $combo->officer;
            $product = $combo->product;
            $comboCurrency = $combo->currency;

            if (!$officer || !$comboCurrency)
                continue;

            try {
                // Get loans for this officer/product/currency combination.
                $baseQuery = Loan::query()->where('loan_officer_id', $officer->id)
                    ->where('product_id', $combo->product_id)
                    ->where('currency', $comboCurrency);

                // --- Skip if no loans exist for this combo ---
                if ((clone $baseQuery)->count() === 0)
                    continue;

                // --- 1. No. Disb & Disb. Amount ---
                $oldDisb = (clone $baseQuery)->where('start_date', '<', $fromDateStr)->where('status', '!=', 'pending');
                $newDisb = (clone $baseQuery)->whereBetween('start_date', [$fromDateStr, $toDateStr])->where('status', '!=', 'pending');

                $oldDisbCount = $oldDisb->count();
                $newDisbCount = $newDisb->count();
                $oldDisbAmount = $oldDisb->sum('amount') ?? 0;
                $newDisbAmount = $newDisb->sum('amount') ?? 0;

                // --- 2. Portfolio Size (End of Period) ---
                $portfolioLoans = Loan::with([
                    'payments' => function ($query) {
                        $query->orderBy('payment_date', 'asc');
                    },
                    'transactions' => function ($query) use ($toDateStr) {
                        $query->where('transaction_date', '<=', $toDateStr);
                    },
                ])
                    ->where('loan_officer_id', $officer->id)
                    ->where('product_id', $combo->product_id)
                    ->where('currency', $comboCurrency)
                    ->where('start_date', '<=', $toDateStr)
                    ->where('status', '!=', 'pending')
                    ->where(function ($query) use ($toDateStr) {
                        $query->whereNull('written_off_at')
                            ->orWhereDate('written_off_at', '>', $toDateStr);
                    })
                    ->get();

                $activeBorrowerIds = [];
                $loanOS = 0;
                $interestOS = 0;
                $feeOS = 0;

                // --- 3. Portfolio Mutation (This Period) ---
                $transactions = RepaymentTransaction::query()->whereHas('loan', function ($q) use ($officer, $combo, $comboCurrency) {
                    $q->where('loan_officer_id', $officer->id)
                        ->where('product_id', $combo->product_id)
                        ->where('currency', $comboCurrency);
                })->whereBetween('transaction_date', [$fromDateStr, $toDateStr])->get();

                $collectedPrincipal = $transactions->sum('principal_paid') ?? 0;
                $collectedInterest = $transactions->sum('interest_paid') ?? 0;
                $feeCollected = $transactions->sum('fee_paid') ?? 0;
                $penaltyCollected = $transactions->sum('penalty_paid') ?? 0;
                $paidOffCollected = $transactions->where('repayment_type', 'Pay Off')->sum(function ($transaction) {
                    return (float) ($transaction->principal_paid ?? 0)
                        + (float) ($transaction->paid_off_amount ?? 0);
                }) ?? 0;
                $recovery = $transactions->where('repayment_type', 'Recovery')->sum('amount_paid') ?? 0;

                $loanIds = (clone $baseQuery)
                    ->where('status', '!=', 'pending')
                    ->pluck('id');
                $duePayments = \App\Models\Payment::whereIn('loan_id', $loanIds)
                    ->whereBetween('payment_date', [$fromDateStr, $toDateStr])
                    ->get();

                $principalDue = $duePayments->sum('principal_amount') ?? 0;
                $interestDue = $duePayments->sum('interest_amount') ?? 0;
                $feeDue = $duePayments->sum('fee_amount') ?? 0;

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

                foreach ($portfolioLoans as $loan) {
                    $transactionsAtEnd = $loan->transactions;

                    $principalPaidAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                        return (float) ($transaction->principal_paid ?? 0)
                            + (float) ($transaction->prepayment_paid ?? 0)
                            + (float) ($transaction->paid_off_amount ?? 0)
                            - (float) ($transaction->withdrawn_prepayment ?? 0);
                    });

                    $currentOS = max(0, (float) $loan->amount - $principalPaidAtEnd);
                    if ($currentOS <= 0.01) {
                        continue;
                    }

                    $loanOS += $currentOS;
                    $activeBorrowerIds[] = $loan->borrower_id;

                    $paymentsUpToDate = $loan->payments->filter(function ($payment) use ($toDateStr) {
                        return $payment->payment_date <= $toDateStr;
                    });

                    $interestPaidAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                        return (float) ($transaction->interest_paid ?? 0);
                    });
                    $feePaidAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                        return (float) ($transaction->fee_paid ?? 0);
                    });

                    $interestScheduledAtEnd = $paymentsUpToDate->sum(function ($payment) {
                        return (float) ($payment->interest_amount ?? 0);
                    });
                    $feeScheduledAtEnd = $paymentsUpToDate->sum(function ($payment) {
                        return (float) ($payment->fee_amount ?? 0);
                    });

                    $interestOS += max(0, $interestScheduledAtEnd - $interestPaidAtEnd);
                    $feeOS += max(0, $feeScheduledAtEnd - $feePaidAtEnd);

                    $scheduledPaidAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                        return (float) ($transaction->fee_paid ?? 0)
                            + (float) ($transaction->interest_paid ?? 0)
                            + (float) ($transaction->principal_paid ?? 0)
                            + (float) ($transaction->prepayment_paid ?? 0)
                            + (float) ($transaction->paid_off_amount ?? 0);
                    });

                    $cumulativeDue = 0.0;
                    $cumulativePrincipalDue = 0.0;
                    $earliestOverdueDate = null;
                    $earliestPrincipalArrearDate = null;

                    foreach ($loan->payments as $payment) {
                        if ($payment->payment_date >= $toDateStr) {
                            continue;
                        }

                        $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                            + (float) ($payment->interest_amount ?? 0)
                            + (float) ($payment->fee_amount ?? 0);
                        $cumulativePrincipalDue += (float) ($payment->principal_amount ?? 0);

                        if (($cumulativeDue - $scheduledPaidAtEnd) > 0.01 && $earliestOverdueDate === null) {
                            $earliestOverdueDate = $payment->payment_date;
                        }

                        if (($cumulativePrincipalDue - $principalPaidAtEnd) > 0.01 && $earliestPrincipalArrearDate === null) {
                            $earliestPrincipalArrearDate = $payment->payment_date;
                        }
                    }

                    $effectiveArrearDate = $earliestOverdueDate ?? $earliestPrincipalArrearDate;

                    if (!$effectiveArrearDate) {
                        continue;
                    }

                    $aging = abs($toDate->startOfDay()->diffInDays(Carbon::parse($effectiveArrearDate)->startOfDay()));

                    if ($aging >= 1) {
                        $par1Count++;
                        $par1Amount += $currentOS;
                    }
                    if ($aging >= 30) {
                        $par30Count++;
                        $par30Amount += $currentOS;
                    }
                }

                $totalClient = collect($activeBorrowerIds)->unique()->count();
                $totalArrears = max(0, ($principalDue + $interestDue + $feeDue) - ($collectedPrincipal + $collectedInterest + $feeCollected));

                $reportData[] = [
                    'co_code' => $officer->id,
                    'co_name' => $officer->name,
                    'product_name' => $product ? $product->name : 'General Loan',
                    'currency' => $comboCurrency,
                    'no_disb_old' => $oldDisbCount,
                    'no_disb_new' => $newDisbCount,
                    'no_disb_total' => $oldDisbCount + $newDisbCount,
                    'disb_amount_old' => $oldDisbAmount,
                    'disb_amount_new' => $newDisbAmount,
                    'disb_amount_total' => $oldDisbAmount + $newDisbAmount,
                    'disb_amount_extra' => $newDisbAmount,
                    'borrower_ids' => collect($activeBorrowerIds)->unique()->values()->all(),
                    'total_client' => $totalClient,
                    'loan_os' => $loanOS,
                    'interest_os' => $interestOS,
                    'fee_os' => $feeOS,
                    'no_of_client' => $totalClient,
                    'principal_collected' => $collectedPrincipal,
                    'interest_collected' => $collectedInterest,
                    'fee_collected' => $feeCollected,
                    'penalty_collected' => $penaltyCollected,
                    'paid_off_collected' => $paidOffCollected,
                    'recovery' => $recovery,
                    'principal_due' => $principalDue,
                    'interest_due' => $interestDue,
                    'fee_due' => $feeDue,
                    'total_arrears' => $totalArrears,
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
