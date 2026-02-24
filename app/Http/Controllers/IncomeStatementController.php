<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeStatementController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currency = $request->query('currency');

        Log::info("Income Statement: from=$fromDate, to=$toDate, currency=$currency");

        try {
            // ── REVENUE ──────────────────────────────────────────────

            // 1. Interest Income (from repayment transactions)
            $interestQuery = DB::table('repayment_transactions')
                ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id');

            if ($fromDate && $toDate) {
                $interestQuery->whereBetween('repayment_transactions.transaction_date', [$fromDate, $toDate]);
            }
            if ($currency && $currency !== 'all') {
                $interestQuery->where('loans.currency', 'LIKE', $currency . '%');
            }

            $interestIncome = (double) $interestQuery->sum('repayment_transactions.interest_paid');

            // 2. Penalty / Late-Fee Income
            $penaltyQuery = DB::table('repayment_transactions')
                ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id');

            if ($fromDate && $toDate) {
                $penaltyQuery->whereBetween('repayment_transactions.transaction_date', [$fromDate, $toDate]);
            }
            if ($currency && $currency !== 'all') {
                $penaltyQuery->where('loans.currency', 'LIKE', $currency . '%');
            }

            $penaltyIncome = (double) $penaltyQuery->sum('repayment_transactions.penalty_paid');

            // 3. Admin Fee Income (from loans disbursed in the period)
            $adminFeeQuery = DB::table('loans');

            if ($fromDate && $toDate) {
                $adminFeeQuery->whereBetween('loans.start_date', [$fromDate, $toDate]);
            }
            if ($currency && $currency !== 'all') {
                $adminFeeQuery->where('loans.currency', 'LIKE', $currency . '%');
            }

            $adminFeeIncome = (double) $adminFeeQuery->sum('loans.admin_fee');

            // 6. Other Revenue (Miscellaneous)
            $otherRevenueQuery = DB::table('miscellaneous_transactions')
                ->where('type', 'revenue');
            if ($fromDate && $toDate) {
                $otherRevenueQuery->whereBetween('transaction_date', [$fromDate, $toDate]);
            }
            if ($currency && $currency !== 'all') {
                $otherRevenueQuery->where('currency', $currency);
            }
            $otherRevenue = (double) $otherRevenueQuery->sum('amount');

            $totalRevenue = $interestIncome + $penaltyIncome + $adminFeeIncome + $otherRevenue;

            // ── EXPENSES ─────────────────────────────────────────────

            // 4. Salary & Payroll Expense
            // ... (existing code remains same) ...
            $payrollQuery = DB::table('payrolls');

            if ($fromDate && $toDate) {
                $payrollQuery->whereBetween('payrolls.payment_date', [$fromDate, $toDate]);
            }

            $salaryExpense = (double) $payrollQuery->sum('payrolls.salary');
            $allowanceExpense = (double) (clone $payrollQuery)->sum('payrolls.allowance');
            $bonusExpense = (double) (clone $payrollQuery)->sum('payrolls.bonus');

            // Re-build for total_payable
            $payrollTotalQuery = DB::table('payrolls');
            if ($fromDate && $toDate) {
                $payrollTotalQuery->whereBetween('payrolls.payment_date', [$fromDate, $toDate]);
            }
            $totalPayrollExpense = (double) $payrollTotalQuery->sum('payrolls.total_payable');

            // 5. Borrowing Interest Expense
            $borrowingIntQuery = DB::table('borrowing_repayments');

            if ($fromDate && $toDate) {
                $borrowingIntQuery->whereBetween('borrowing_repayments.payment_date', [$fromDate, $toDate]);
            }

            $borrowingInterestExpense = (double) $borrowingIntQuery->sum('borrowing_repayments.interest_paid');

            // 7. Other Expenses (Miscellaneous)
            $otherExpenseQuery = DB::table('miscellaneous_transactions')
                ->where('type', 'expense');
            if ($fromDate && $toDate) {
                $otherExpenseQuery->whereBetween('transaction_date', [$fromDate, $toDate]);
            }
            if ($currency && $currency !== 'all') {
                $otherExpenseQuery->where('currency', $currency);
            }
            $otherExpensesItem = (double) $otherExpenseQuery->sum('amount');

            $totalExpenses = $totalPayrollExpense + $borrowingInterestExpense + $otherExpensesItem;

            // ── NET INCOME ───────────────────────────────────────────
            $netIncome = $totalRevenue - $totalExpenses;

            // Build line-item response
            $data = [
                'period' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'currency' => $currency ?? 'all',
                ],
                'revenue' => [
                    ['label' => 'Interest Income', 'amount' => $interestIncome],
                    ['label' => 'Penalty / Late Fees', 'amount' => $penaltyIncome],
                    ['label' => 'Admin / Service Fees', 'amount' => $adminFeeIncome],
                    ['label' => 'Other Revenue', 'amount' => $otherRevenue],
                ],
                'total_revenue' => $totalRevenue,
                'expenses' => [
                    ['label' => 'Salary Expense', 'amount' => $salaryExpense],
                    ['label' => 'Allowance Expense', 'amount' => $allowanceExpense],
                    ['label' => 'Bonus Expense', 'amount' => $bonusExpense],
                    ['label' => 'Total Payroll Expense', 'amount' => $totalPayrollExpense],
                    ['label' => 'Borrowing Interest Expense', 'amount' => $borrowingInterestExpense],
                    ['label' => 'Other Expenses', 'amount' => $otherExpensesItem],
                ],
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Income Statement Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ]);
        }
    }
}
