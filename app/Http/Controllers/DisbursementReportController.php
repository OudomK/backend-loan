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
        $officerQuery = LoanOfficer::query();
        if ($officerId && $officerId !== 'all') {
            $officerQuery->where('id', $officerId);
        }
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
            $feeOS = 0; // Admin fee or similar

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
            $feeCollected = 0; // Placeholder
            $penaltyCollected = $transactions->sum('penalty_paid');
            $recovery = $transactions->where('repayment_type', 'Recovery')->sum('amount_paid');
            $paidOffCollected = $transactions->where('repayment_type', 'Pay Off')->sum('amount_paid');

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
                    $aging = $toDate->diffInDays(Carbon::parse($earliestOverdue->payment_date));

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
                'co_name' => $officer->name,

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

                // Write-Off (Placeholders for now)
                'wo_cur_count' => 0,
                'wo_cur_principal' => 0,
                'wo_cur_interest' => 0,
                'wo_cur_fee' => 0,
                'wo_ytd_count' => 0,
                'wo_ytd_principal' => 0,
                'wo_ytd_interest' => 0,
                'wo_ytd_fee' => 0,

                // Due (Placeholders)
                'principal_due' => 0,
                'interest_due' => 0,
                'fee_due' => 0,
                'total_arrears' => $par1Amount, // Simple mapping
                'repayment_rate' => 0,
            ];
        }

        return response()->json($reportData);
    }
}
