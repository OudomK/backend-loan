<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanOfficer;
use App\Models\RepaymentTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DisbursementReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $officerId = $request->query('officer_id');

        $toDate = $toDateStr ? Carbon::parse($toDateStr)->endOfDay() : Carbon::today()->endOfDay();
        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr)->startOfDay() : $toDate->copy()->startOfMonth();

        $toDateStr = $toDate->toDateString();
        $fromDateStr = $fromDate->toDateString();

        // 1. Get Combinations of Officer and Currency
        $combinations = Loan::with('officer.employee')
            ->select('loan_officer_id', 'currency')
            ->whereNotNull('loan_officer_id')
            ->when($officerId && $officerId !== 'all', function ($q) use ($officerId) {
                $q->where('loan_officer_id', $officerId);
            })
            ->groupBy('loan_officer_id', 'currency')
            ->get();

        $reportData = [];

        foreach ($combinations as $combo) {
            $officer = $combo->officer;
            $currency = $combo->currency;
            if (!$officer || !$currency) continue;

            // --- Disbursement Section ---
            $disbQuery = Loan::query()->where('loan_officer_id', $officer->id)->where('currency', $currency);

            $oldDisb = (clone $disbQuery)->where('start_date', '<', $fromDateStr);
            $newDisb = (clone $disbQuery)->whereBetween('start_date', [$fromDateStr, $toDateStr]);

            $noDisbOld = $oldDisb->count();
            $noDisbNew = $newDisb->count();
            $amtDisbOld = $oldDisb->sum('amount');
            $amtDisbNew = $newDisb->sum('amount');

            // --- Portfolio Size Section ---
            // Approximate portfolio state as of to_date using transactions recorded on/before to_date.
            $portfolioLoans = Loan::with([
                'payments' => function ($query) {
                    $query->orderBy('payment_date', 'asc');
                },
                'transactions' => function ($query) use ($toDateStr) {
                    $query->where('transaction_date', '<=', $toDateStr);
                },
            ])
                ->where('loan_officer_id', $officer->id)
                ->where('currency', $currency)
                ->where('start_date', '<=', $toDateStr)
                ->where('status', '!=', 'pending')
                ->where(function ($query) use ($toDateStr) {
                    $query->whereNull('written_off_at')
                        ->orWhereDate('written_off_at', '>', $toDateStr);
                })
                ->get();

            $loanOS = 0;
            $interestOS = 0;
            $feeOS = 0;
            $activeBorrowerIds = [];

            // --- PAR Section ---
            $par1Count = 0;
            $par1Amount = 0;
            $par1_29Count = 0;
            $par1_29Amount = 0;
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

                $currentLoanOS = max(0, (float) $loan->amount - $principalPaidAtEnd);
                if ($currentLoanOS <= 0.01) {
                    continue;
                }

                $loanOS += $currentLoanOS;
                $activeBorrowerIds[] = $loan->borrower_id;

                $paymentsUpToDate = $loan->payments->filter(function ($payment) use ($toDateStr) {
                    return $payment->payment_date <= $toDateStr;
                });

                $interestCollectedAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                    return (float) ($transaction->interest_paid ?? 0);
                });
                $feeCollectedAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                    return (float) ($transaction->fee_paid ?? 0);
                });

                $interestScheduledAtEnd = $paymentsUpToDate->sum(function ($payment) {
                    return (float) ($payment->interest_amount ?? 0);
                });
                $feeScheduledAtEnd = $paymentsUpToDate->sum(function ($payment) {
                    return (float) ($payment->fee_amount ?? 0);
                });

                $interestOS += max(0, $interestScheduledAtEnd - $interestCollectedAtEnd);
                $feeOS += max(0, $feeScheduledAtEnd - $feeCollectedAtEnd);

                $scheduledPaidAtEnd = $transactionsAtEnd->sum(function ($transaction) {
                    return (float) ($transaction->fee_paid ?? 0)
                        + (float) ($transaction->interest_paid ?? 0)
                        + (float) ($transaction->principal_paid ?? 0)
                        + (float) ($transaction->paid_off_amount ?? 0)
                        - (float) ($transaction->withdrawn_prepayment ?? 0);
                });

                $cumulativeDue = 0.0;
                $earliestOverdueDate = null;

                foreach ($loan->payments as $payment) {
                    if ($payment->payment_date >= $toDateStr) {
                        continue;
                    }

                    $cumulativeDue += (float) ($payment->principal_amount ?? 0)
                        + (float) ($payment->interest_amount ?? 0)
                        + (float) ($payment->fee_amount ?? 0);

                    if (($cumulativeDue - $scheduledPaidAtEnd) > 0.01) {
                        $earliestOverdueDate = $payment->payment_date;
                        break;
                    }
                }
                $aging = $loan->agingAt($toDate, $earliestOverdueDate, $earliestOverdueDate !== null);
                if ($aging <= 0) {
                    continue;
                }

                if ($aging >= 1) {
                    $par1Count++;
                    $par1Amount += $currentLoanOS;
                }
                if ($aging >= 1 && $aging <= 29) {
                    $par1_29Count++;
                    $par1_29Amount += $currentLoanOS;
                }
                if ($aging >= 30) {
                    $par30Count++;
                    $par30Amount += $currentLoanOS;
                }
            }

            $totalClient = collect($activeBorrowerIds)->unique()->count();

            // --- Portfolio Mutation Section ---
            $transactions = RepaymentTransaction::query()->whereHas('loan', function ($q) use ($officer, $currency) {
                $q->where('loan_officer_id', $officer->id)->where('currency', $currency);
            })->whereBetween('transaction_date', [$fromDateStr, $toDateStr])->get();

            $principalCollected = $transactions->sum('principal_paid');
            $interestCollected = $transactions->sum('interest_paid');
            $feeCollected = $transactions->sum('fee_paid');
            $penaltyCollected = $transactions->sum('penalty_paid');
            $recovery = $transactions->where('repayment_type', 'Recovery')->sum('amount_paid');
            // Paid-off Coll. = principal collected on Pay Off (not full amount_paid)
            $paidOffCollected = $transactions->where('repayment_type', 'Pay Off')->sum(function ($transaction) {
                return (float) ($transaction->principal_paid ?? 0)
                    + (float) ($transaction->paid_off_amount ?? 0);
            });

            // --- Due Section (Payments scheduled within the period) ---
            $duePayments = \App\Models\Payment::whereHas('loan', function ($q) use ($officer, $currency) {
                $q->where('loan_officer_id', $officer->id)->where('currency', $currency);
            })->whereBetween('payment_date', [$fromDateStr, $toDateStr])->get();

            $principalDue = $duePayments->sum('principal_amount');
            $interestDue  = $duePayments->sum('interest_amount');
            $feeDue       = 0; // no fee column on payments table

            // --- Write-Off Section ---
            $woCurLoans = Loan::query()->where('loan_officer_id', $officer->id)
                ->where('currency', $currency)
                ->whereNotNull('written_off_at')
                ->whereBetween('written_off_at', [$fromDateStr, $toDateStr])
                ->get();

            $yearStart = Carbon::parse($toDateStr)->startOfYear()->toDateString();
            $woYtdLoans = Loan::query()->where('loan_officer_id', $officer->id)
                ->where('currency', $currency)
                ->whereNotNull('written_off_at')
                ->whereBetween('written_off_at', [$yearStart, $toDateStr])
                ->get();

            // --- Repayment Rate ---
            $repaymentRate = $principalDue > 0
                ? round(($principalCollected / $principalDue) * 100, 2)
                : 0;

            $reportData[] = [
                'co_code' => str_pad($officer->id, 4, '0', STR_PAD_LEFT),
                'co_name' => $officer->employee ? $officer->employee->name : $officer->name,
                'currency' => $currency,

                // No. Disb
                'no_disb_old' => $noDisbOld,
                'no_disb_new' => $noDisbNew,
                'no_disb_total' => $noDisbOld + $noDisbNew,

                // Disb. Amount
                'amt_disb_old' => $amtDisbOld,
                'amt_disb_new' => $amtDisbNew,
                'amt_disb_total' => $amtDisbOld + $amtDisbNew,

                // Portfolio Size
                'total_client' => $totalClient,
                'loan_os' => $loanOS,
                'interest_os' => $interestOS,
                'fee_os' => $feeOS,

                // Portfolio Mutation
                'principal_collected' => $principalCollected,
                'interest_collected' => $interestCollected,
                'fee_collected' => $feeCollected,
                'penalty_collected' => $penaltyCollected,
                'paid_off_collected' => $paidOffCollected,
                'recovery' => $recovery,

                // PAR
                'par1_count' => $par1Count,
                'par1_amount' => $par1Amount,
                'par1_percent' => $loanOS > 0 ? ($par1Amount / $loanOS) * 100 : 0,

                'par1_29_count' => $par1_29Count,
                'par1_29_amount' => $par1_29Amount,
                'par1_29_percent' => $loanOS > 0 ? ($par1_29Amount / $loanOS) * 100 : 0,

                'par30_count' => $par30Count,
                'par30_amount' => $par30Amount,
                'par30_percent' => $loanOS > 0 ? ($par30Amount / $loanOS) * 100 : 0,

                // Write-Off Current (within period)
                'wo_cur_count'     => $woCurLoans->count(),
                'wo_cur_principal' => $woCurLoans->sum('write_off_balance'),
                'wo_cur_interest'  => 0,
                'wo_cur_fee'       => 0,

                // Write-Off YTD (year-to-date)
                'wo_ytd_count'     => $woYtdLoans->count(),
                'wo_ytd_principal' => $woYtdLoans->sum('write_off_balance'),
                'wo_ytd_interest'  => 0,
                'wo_ytd_fee'       => 0,

                // Due (scheduled payments within period)
                'principal_due'  => $principalDue,
                'interest_due'   => $interestDue,
                'fee_due'        => $feeDue,
                'total_arrears'  => $par1Amount,
                'repayment_rate' => $repaymentRate,
            ];
        }

        return response()->json($reportData);
    }

    public function exportExcel(Request $request)
    {
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $officerId = $request->query('officer_id');

        // Reuse the index logic to get the same exact array
        $response = $this->index($request);
        $data = $response->getData(true); // Convert JSON response back to array

        $officerName = 'ALL';
        if ($officerId && $officerId !== 'all') {
            $officer = LoanOfficer::find($officerId);
            if ($officer) {
                $officerName = $officer->name;
            }
        }

        $export = new \App\Exports\Excel\DisbursementExcelExport();
        return $export->download($data, $request, $fromDateStr, $toDateStr, $officerName);
    }
}
