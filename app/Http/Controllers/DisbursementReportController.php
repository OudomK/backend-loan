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

        // 1. Get Officers
        $officerQuery = LoanOfficer::with('employee');
        if ($officerId && $officerId !== 'all') {
            $officerQuery->where('id', $officerId);
        }
        // Only include officers that actually have loans
        $officerQuery->whereHas('loans');
        $officers = $officerQuery->get();

        $reportData = [];

        foreach ($officers as $officer) {
            // --- Disbursement Section ---
            $disbQuery = Loan::where('loan_officer_id', $officer->id);

            $oldDisb = (clone $disbQuery)->where('start_date', '<', $fromDateStr);
            $newDisb = (clone $disbQuery)->whereBetween('start_date', [$fromDateStr, $toDateStr]);

            $noDisbOld = $oldDisb->count();
            $noDisbNew = $newDisb->count();
            $amtDisbOld = $oldDisb->sum('amount');
            $amtDisbNew = $newDisb->sum('amount');

            // --- Portfolio Size Section ---
            // Active loans as of to_date
            $activeLoans = Loan::with(['payments', 'borrower'])
                ->where('loan_officer_id', $officer->id)
                ->where('start_date', '<=', $toDateStr)
                ->where('status', 'active') // Or checked if it was active at that time
                ->get();

            $totalClient = $activeLoans->unique('borrower_id')->count();
            $loanOS = 0;
            $interestOS = 0;
            // Fee OS = per loan: total_fee (amount * admin_fee%) - fee already collected; then sum
            $feeOS = 0;
            foreach ($activeLoans as $loan) {
                $totalFee = $loan->amount * ((float) ($loan->admin_fee ?? 0) / 100);
                $feeCollectedForLoan = RepaymentTransaction::where('loan_id', $loan->id)->sum('fee_paid');
                $feeOS += max(0, $totalFee - $feeCollectedForLoan);
            }

            foreach ($activeLoans as $loan) {
                foreach ($loan->payments as $p) {
                    // Only count payments that were due or scheduled
                    // Remaining = (Principal + Interest) - Paid
                    $paidInterest = min($p->total_paid, $p->interest_amount);
                    $paidPrincipal = max(0, $p->total_paid - $p->interest_amount);

                    $loanOS += max(0, $p->principal_amount - $paidPrincipal);
                    $interestOS += max(0, $p->interest_amount - $paidInterest);
                }
            }

            // --- Portfolio Mutation Section ---
            $transactions = RepaymentTransaction::whereHas('loan', function ($q) use ($officer) {
                $q->where('loan_officer_id', $officer->id);
            })->whereBetween('transaction_date', [$fromDateStr, $toDateStr])->get();

            $principalCollected = $transactions->sum('principal_paid');
            $interestCollected = $transactions->sum('interest_paid');
            $feeCollected = $transactions->sum('fee_paid');
            $penaltyCollected = $transactions->sum('penalty_paid');
            $recovery = $transactions->where('repayment_type', 'Recovery')->sum('amount_paid');
            // Paid-off Coll. = principal collected on Pay Off (not full amount_paid)
            $paidOffCollected = $transactions->where('repayment_type', 'Pay Off')->sum('principal_paid');

            // --- Due Section (Payments scheduled within the period) ---
            $duePayments = \App\Models\Payment::whereHas('loan', function ($q) use ($officer) {
                $q->where('loan_officer_id', $officer->id);
            })->whereBetween('payment_date', [$fromDateStr, $toDateStr])->get();

            $principalDue = $duePayments->sum('principal_amount');
            $interestDue  = $duePayments->sum('interest_amount');
            $feeDue       = 0; // no fee column on payments table

            // --- Write-Off Section ---
            $woCurLoans = Loan::where('loan_officer_id', $officer->id)
                ->whereNotNull('written_off_at')
                ->whereBetween('written_off_at', [$fromDateStr, $toDateStr])
                ->get();

            $yearStart = \Carbon\Carbon::parse($toDateStr)->startOfYear()->toDateString();
            $woYtdLoans = Loan::where('loan_officer_id', $officer->id)
                ->whereNotNull('written_off_at')
                ->whereBetween('written_off_at', [$yearStart, $toDateStr])
                ->get();

            // --- Repayment Rate ---
            $repaymentRate = $principalDue > 0
                ? round(($principalCollected / $principalDue) * 100, 2)
                : 0;

            // --- PAR Section ---
            $par1Count = 0;
            $par1Amount = 0;
            $par1_29Count = 0;
            $par1_29Amount = 0;
            $par30Count = 0;
            $par30Amount = 0;

            foreach ($activeLoans as $loan) {
                $earliestOverdue = $loan->payments
                    ->where('payment_date', '<', $toDateStr)
                    ->filter(function ($p) {
                        return $p->total_paid < ($p->principal_amount + $p->interest_amount - 0.01);
                    })
                    ->sortBy('payment_date')
                    ->first();

                if ($earliestOverdue) {
                    $aging = abs($toDate->diffInDays(Carbon::parse($earliestOverdue->payment_date)));

                    // Calc current loan OS for PAR amount
                    $currentLoanOS = 0;
                    foreach ($loan->payments as $p) {
                        $paidPrincipal = max(0, $p->total_paid - $p->interest_amount);
                        $currentLoanOS += max(0, $p->principal_amount - $paidPrincipal);
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
            }

            $reportData[] = [
                'co_code' => str_pad($officer->id, 4, '0', STR_PAD_LEFT),
                'co_name' => $officer->employee ? $officer->employee->name : $officer->name,

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
}
