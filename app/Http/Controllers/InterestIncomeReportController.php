<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterestIncomeReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDateInput = $request->query('from_date');
        $toDateInput = $request->query('to_date');
        $currency = $request->query('currency', 'all');

        $fromDate = $fromDateInput
            ? Carbon::parse($fromDateInput)->startOfDay()
            : Carbon::today()->startOfMonth();
        $toDate = $toDateInput
            ? Carbon::parse($toDateInput)->endOfDay()
            : Carbon::today()->endOfDay();

        $fromDateStr = $fromDate->toDateString();
        $toDateStr = $toDate->toDateString();
        $fromDateTime = $fromDate->toDateTimeString();
        $toDateTime = $toDate->toDateTimeString();

        Log::info("Interest Income Report: from=$fromDateStr, to=$toDateStr, currency=$currency");

        try {
            $query = DB::table('loans')
                ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
                ->leftJoin('loan_products', 'loans.product_id', '=', 'loan_products.id')
                ->select([
                    'loans.id',
                    'loans.loan_code',
                    'loans.start_date as disb_date',
                    'loans.amount as loan_amount',
                    'loans.currency',
                    'loans.interest_rate',
                    'loans.duration_months as term',
                    'loans.payment_frequency',
                    'loans.repayment_method',
                    'loans.loan_cycle',
                    'loans.admin_fee',
                    'loans.admin_fee_type',
                    'borrowers.customer_code',
                    'borrowers.first_name',
                    'borrowers.last_name',
                    'loan_products.name as product_name',
                ])
                ->where('loans.status', '!=', 'pending')
                ->whereNull('loans.deleted_at');

            $query->addSelect([
                DB::raw("(SELECT type FROM collaterals WHERE collaterals.loan_id = loans.id ORDER BY collaterals.id ASC LIMIT 1) as collateral_type"),
                'interest_collected' => DB::table('repayment_transactions')
                    ->selectRaw('COALESCE(SUM(interest_paid), 0)')
                    ->whereColumn('repayment_transactions.loan_id', 'loans.id')
                    ->whereNull('repayment_transactions.deleted_at')
                    ->whereBetween('repayment_transactions.transaction_date', [$fromDateTime, $toDateTime]),
                'fee_collected' => DB::table('repayment_transactions')
                    ->selectRaw('COALESCE(SUM(fee_paid), 0)')
                    ->whereColumn('repayment_transactions.loan_id', 'loans.id')
                    ->whereNull('repayment_transactions.deleted_at')
                    ->whereBetween('repayment_transactions.transaction_date', [$fromDateTime, $toDateTime]),
                'penalty_collected' => DB::table('repayment_transactions')
                    ->selectRaw('COALESCE(SUM(penalty_paid), 0)')
                    ->whereColumn('repayment_transactions.loan_id', 'loans.id')
                    ->whereNull('repayment_transactions.deleted_at')
                    ->whereBetween('repayment_transactions.transaction_date', [$fromDateTime, $toDateTime]),
            ]);

            if ($currency && $currency !== 'all') {
                $query->where('loans.currency', 'LIKE', $currency . '%');
            }

            // Standard period logic:
            // include any loan with income activity in the selected period,
            // plus newly disbursed one-time admin fees recognized in the period.
            $query->where(function ($reportQuery) use ($fromDateStr, $toDateStr, $fromDateTime, $toDateTime) {
                $reportQuery->whereExists(function ($txnQuery) use ($fromDateTime, $toDateTime) {
                    $txnQuery->selectRaw('1')
                        ->from('repayment_transactions')
                        ->whereColumn('repayment_transactions.loan_id', 'loans.id')
                        ->whereNull('repayment_transactions.deleted_at')
                        ->whereBetween('repayment_transactions.transaction_date', [$fromDateTime, $toDateTime])
                        ->where(function ($incomeQuery) {
                            $incomeQuery->where('repayment_transactions.interest_paid', '>', 0)
                                ->orWhere('repayment_transactions.fee_paid', '>', 0)
                                ->orWhere('repayment_transactions.penalty_paid', '>', 0);
                        });
                })->orWhere(function ($disbursedQuery) use ($fromDateStr, $toDateStr) {
                    $disbursedQuery->whereBetween('loans.start_date', [$fromDateStr, $toDateStr])
                        ->where(function ($feeTypeQuery) {
                            $feeTypeQuery->whereNull('loans.admin_fee_type')
                                ->orWhere('loans.admin_fee_type', 'one_time');
                        })
                        ->where('loans.admin_fee', '>', 0);
                });
            });

            $loans = $query
                ->orderBy('loans.currency')
                ->orderBy('loans.start_date')
                ->orderBy('loans.loan_code')
                ->get();

            $data = $loans->map(function ($loan) use ($fromDateStr, $toDateStr) {
                $interestCollected = (double) ($loan->interest_collected ?? 0);
                $scheduledFeeCollected = (double) ($loan->fee_collected ?? 0);
                $penaltyCollected = (double) ($loan->penalty_collected ?? 0);

                $adminFeeIncome = 0.0;
                if (
                    $loan->disb_date &&
                    substr((string) $loan->disb_date, 0, 10) >= $fromDateStr &&
                    substr((string) $loan->disb_date, 0, 10) <= $toDateStr &&
                    (($loan->admin_fee_type ?? 'one_time') === 'one_time')
                ) {
                    // admin_fee is stored as a percentage in this system.
                    $adminFeeIncome = ((double) ($loan->loan_amount ?? 0)) * ((double) ($loan->admin_fee ?? 0)) / 100;
                }

                $totalFee = $scheduledFeeCollected + $penaltyCollected + $adminFeeIncome;
                $totalCollected = $interestCollected + $totalFee;

                return [
                    'disb_date' => $loan->disb_date,
                    'loan_code' => $loan->loan_code ?? 'N/A',
                    'customer_code' => $loan->customer_code ?? 'N/A',
                    'customer_name' => trim(($loan->last_name ?? '') . ' ' . ($loan->first_name ?? '')) ?: 'Unknown',
                    'loan_amount' => (double) ($loan->loan_amount ?? 0),
                    'currency' => $loan->currency ?? 'USD',
                    'interest_rate' => (double) ($loan->interest_rate ?? 0),
                    'term' => $loan->term ?? 0,
                    'payment_frequency' => $loan->payment_frequency ?? 'Monthly',
                    'repayment_method' => $loan->repayment_method ?? 'N/A',
                    'product_name' => $loan->product_name ?? 'General Loan',
                    'collateral_type' => $loan->collateral_type ?? '',
                    'loan_cycle' => $loan->loan_cycle ?? 1,
                    'interest_paid' => $interestCollected,
                    'transaction_fee_paid' => $scheduledFeeCollected,
                    'penalty_paid' => $penaltyCollected,
                    'admin_fee_paid' => $adminFeeIncome,
                    'fee_paid' => $totalFee,
                    'total' => $totalCollected,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error("Interest Income Report Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ]);
        }
    }
}
