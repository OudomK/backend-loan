<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WriteOffReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateStr = $request->query('from_date');
        $toDateStr = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $toDate = $toDateStr ? Carbon::parse($toDateStr) : Carbon::today();
        $fromDate = $fromDateStr ? Carbon::parse($fromDateStr) : $toDate->copy()->startOfMonth();

        $query = Loan::with(['borrower', 'officer', 'collaterals'])
            ->whereNotNull('written_off_at')
            ->whereBetween('written_off_at', [$fromDate->toDateString(), $toDate->toDateString()]);

        if ($currency !== 'all') {
            $query->where('currency', $currency);
        }

        $loans = $query->get();
        $reportData = [];

        foreach ($loans as $loan) {
            try {
                // Get collected principal and interest
                $collectedPrincipal = DB::table('payments')
                    ->where('loan_id', $loan->id)
                    ->sum(DB::raw('GREATEST(0, total_paid - interest_amount)'));

                $collectedInterest = DB::table('payments')
                    ->where('loan_id', $loan->id)
                    ->sum(DB::raw('total_paid - GREATEST(0, total_paid - interest_amount)'));

                $reportData[] = [
                    'written_off_date' => $loan->written_off_at,
                    'disbursement_date' => $loan->start_date,
                    'loan_code' => $loan->loan_code,
                    'customer_code' => $loan->borrower->customer_code ?? '',
                    'customer_name' => ($loan->borrower->first_name ?? '') . ' ' . ($loan->borrower->last_name ?? ''),
                    'village' => $loan->borrower->village ?? '',
                    'commune' => $loan->borrower->commune ?? '',
                    'district' => $loan->borrower->district ?? '',
                    'province' => $loan->borrower->province ?? '',
                    'amount' => $loan->amount,
                    'currency' => $loan->currency,
                    'rate' => $loan->interest_rate,
                    'monthly_interest_rate' => $loan->interest_rate, // Assuming yearly, usually same label in excel
                    'term' => $loan->duration_months,
                    'tenor' => strtolower($loan->payment_frequency ?? '') === 'monthly' ? 'Months' : 'ដង',
                    'payment_method' => $loan->repayment_method ?? '',
                    'loan_cycle' => $loan->loan_cycle ?? 1,
                    'refinance_fee' => $loan->refinance_fee ?? 0,
                    'admin_fee' => $loan->admin_fee ?? 0,
                    'restructure_fee' => 0,
                    'collateral_type' => $loan->collaterals->isNotEmpty() ? $loan->collaterals->first()->type : '',
                    'co_disburse' => $loan->officer->name ?? '',
                    'co_repay' => $loan->officer->name ?? '',
                    'amount_write_off' => $loan->amount,
                    'write_off_balance' => $loan->write_off_balance ?? 0,
                    'principal_collected' => $collectedPrincipal,
                    'interest_collected' => $collectedInterest,
                    'recovery_amount' => $loan->recovery_amount ?? 0,
                    'maturity_date' => $loan->maturity_date,
                    'write_off_reason' => $loan->write_off_reason ?? '',
                    'status' => $loan->status,
                    'classify_wo' => $loan->classify_wo ?? '',
                ];
            } catch (\Exception $e) {
                Log::error("WriteOffReport Error for Loan {$loan->id}: " . $e->getMessage());
            }
        }

        return response()->json($reportData);
    }
}
