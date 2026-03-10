<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Loan;
use Illuminate\Support\Facades\Log;

class InterestIncomeReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currency = $request->query('currency');

        Log::info("Interest Income Report: from=$fromDate, to=$toDate, currency=$currency");

        try {
            // Use borrowers table (has data)
            $query = DB::table('loans')
                ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
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
                    'borrowers.customer_code',
                    'borrowers.first_name',
                    'borrowers.last_name',
                    'loan_products.name as product_name',
                ]);

            $query->leftJoin('loan_products', 'loans.product_id', '=', 'loan_products.id');

            // Add collateral type subquery (first collateral's type)
            $query->addSelect([
                DB::raw("(SELECT type FROM collaterals WHERE collaterals.loan_id = loans.id ORDER BY collaterals.id ASC LIMIT 1) as collateral_type"),
            ]);

            // Add date filters if provided
            if ($fromDate && $toDate) {
                $query->whereBetween('loans.start_date', [$fromDate, $toDate]);
            }

            // Add currency filter
            if ($currency && $currency !== 'all') {
                $query->where('loans.currency', 'LIKE', $currency . '%');
            }

            // Add transaction data with subqueries
            if ($fromDate && $toDate) {
                $query->addSelect([
                    DB::raw("(SELECT SUM(interest_paid) FROM repayment_transactions WHERE loan_id = loans.id AND transaction_date BETWEEN '$fromDate' AND '$toDate') as interest_collected"),
                    DB::raw("(SELECT SUM(penalty_paid) FROM repayment_transactions WHERE loan_id = loans.id AND transaction_date BETWEEN '$fromDate' AND '$toDate') as penalty_collected")
                ]);
            }

            $query->orderBy('loans.start_date');
            $loans = $query->get();

            Log::info("Found " . $loans->count() . " loans");

            $data = $loans->map(function ($loan) use ($fromDate, $toDate) {
                $interestCollected = (double) ($loan->interest_collected ?? 0);
                $penaltyCollected = (double) ($loan->penalty_collected ?? 0);
                $adminFee = (double) ($loan->admin_fee ?? 0);

                // Include admin fee only if disbursed in the period
                $includeAdminFee = false;
                if ($fromDate && $toDate && $loan->disb_date) {
                    $disbDate = substr($loan->disb_date, 0, 10);
                    $includeAdminFee = ($disbDate >= $fromDate && $disbDate <= $toDate);
                }

                $totalFee = $penaltyCollected + ($includeAdminFee ? $adminFee : 0);
                $totalCollected = $interestCollected + $totalFee;

                // Dynamic collateral type from DB
                $collateralType = $loan->collateral_type ?? '';

                // Product name from DB
                $productName = $loan->product_name ?? 'General Loan';

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
                    'product_name' => $productName,
                    'collateral_type' => $collateralType,
                    'loan_cycle' => $loan->loan_cycle ?? 1,
                    'interest_paid' => $interestCollected,
                    'fee_paid' => $totalFee,
                    'total' => $totalCollected,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("Interest Income Report Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ]);
        }
    }
}
