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
        $currencyParam = $request->query('currency');

        Log::info("Income Statement: from=$fromDate, to=$toDate, currency=$currencyParam");

        try {
            $exchangeRate = (int) (\App\Models\Setting::where('key', 'exchange_rate')->value('value') ?? 4000);

            $currencies = [];
            if ($currencyParam && $currencyParam !== 'all') {
                $currencies = [$currencyParam];
            } else {
                $loanCurrs = DB::table('loans')->whereBetween('start_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();
                $miscCurrs = DB::table('miscellaneous_transactions')->whereBetween('transaction_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();
                $borrowingCurrs = DB::table('borrowings')->whereBetween('borrowing_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();

                $rawCurrs = array_merge(['USD'], $loanCurrs, $miscCurrs, $borrowingCurrs);
                $normalized = [];
                foreach ($rawCurrs as $c) {
                    if (empty($c))
                        continue;
                    $up = strtoupper($c);
                    if (str_starts_with($up, 'USD')) {
                        $normalized[] = 'USD';
                    } elseif (str_starts_with($up, 'KHR')) {
                        $normalized[] = 'KHR';
                    } else {
                        $normalized[] = $c;
                    }
                }
                $currencies = array_values(array_unique($normalized));
            }

            // helper structure to collect totals
            $revenueItems = [
                'interest_income' => ['label' => 'Interest Income', 'amounts' => [], 'total_usd' => 0],
                'penalty_income' => ['label' => 'Penalty / Late Fees', 'amounts' => [], 'total_usd' => 0],
                'admin_fee' => ['label' => 'Admin / Service Fees', 'amounts' => [], 'total_usd' => 0],
                'other_revenue' => ['label' => 'Other Revenue', 'amounts' => [], 'total_usd' => 0],
            ];
            $expenseItems = [
                'salary' => ['label' => 'Salary Expense', 'amounts' => [], 'total_usd' => 0],
                'allowance' => ['label' => 'Allowance Expense', 'amounts' => [], 'total_usd' => 0],
                'bonus' => ['label' => 'Bonus Expense', 'amounts' => [], 'total_usd' => 0],
                'total_payroll' => ['label' => 'Total Payroll Expense', 'amounts' => [], 'total_usd' => 0],
                'borrowing_interest' => ['label' => 'Borrowing Interest Expense', 'amounts' => [], 'total_usd' => 0],
                'other_expenses' => ['label' => 'Other Expenses', 'amounts' => [], 'total_usd' => 0],
            ];

            $totalRevenue = [];
            $totalExpenses = [];
            $netIncome = [];
            $grandTotalRevenueUSD = 0;
            $grandTotalExpensesUSD = 0;

            foreach ($currencies as $curr) {
                // Initialize totals
                $totalRevenue[$curr] = 0;
                $totalExpenses[$curr] = 0;

                // ── REVENUE ──────────────────────────────────────────────
                $interest = (double) DB::table('repayment_transactions')
                    ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
                    ->where('loans.currency', 'LIKE', $curr . '%')
                    ->whereBetween('repayment_transactions.transaction_date', [$fromDate, $toDate])
                    ->sum('repayment_transactions.interest_paid');
                $revenueItems['interest_income']['amounts'][$curr] = $interest;
                $totalRevenue[$curr] += $interest;
                $revenueItems['interest_income']['total_usd'] += ($curr === 'USD' ? $interest : $interest / $exchangeRate);

                $penalty = (double) DB::table('repayment_transactions')
                    ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
                    ->where('loans.currency', 'LIKE', $curr . '%')
                    ->whereBetween('repayment_transactions.transaction_date', [$fromDate, $toDate])
                    ->sum('repayment_transactions.penalty_paid');
                $revenueItems['penalty_income']['amounts'][$curr] = $penalty;
                $totalRevenue[$curr] += $penalty;
                $revenueItems['penalty_income']['total_usd'] += ($curr === 'USD' ? $penalty : $penalty / $exchangeRate);

                $admin = (double) DB::table('loans')
                    ->where('loans.currency', 'LIKE', $curr . '%')
                    ->whereBetween('loans.start_date', [$fromDate, $toDate])
                    ->sum('loans.admin_fee');
                $revenueItems['admin_fee']['amounts'][$curr] = $admin;
                $totalRevenue[$curr] += $admin;
                $revenueItems['admin_fee']['total_usd'] += ($curr === 'USD' ? $admin : $admin / $exchangeRate);

                $otherRev = (double) DB::table('miscellaneous_transactions')
                    ->where('type', 'revenue')
                    ->where('currency', 'LIKE', $curr . '%')
                    ->whereBetween('transaction_date', [$fromDate, $toDate])
                    ->sum('amount');
                $revenueItems['other_revenue']['amounts'][$curr] = $otherRev;
                $totalRevenue[$curr] += $otherRev;
                $revenueItems['other_revenue']['total_usd'] += ($curr === 'USD' ? $otherRev : $otherRev / $exchangeRate);

                // ── EXPENSES ─────────────────────────────────────────────
                if ($curr === 'USD') {
                    $pQuery = DB::table('payrolls')->whereBetween('payment_date', [$fromDate, $toDate]);
                    $expenseItems['salary']['amounts'][$curr] = (double) $pQuery->sum('salary');
                    $expenseItems['allowance']['amounts'][$curr] = (double) (clone $pQuery)->sum('allowance');
                    $expenseItems['bonus']['amounts'][$curr] = (double) (clone $pQuery)->sum('bonus');
                    $tPayroll = (double) (clone $pQuery)->sum('total_payable');
                    $expenseItems['total_payroll']['amounts'][$curr] = $tPayroll;
                    $totalExpenses[$curr] += $tPayroll;

                    $expenseItems['salary']['total_usd'] += $expenseItems['salary']['amounts'][$curr];
                    $expenseItems['allowance']['total_usd'] += $expenseItems['allowance']['amounts'][$curr];
                    $expenseItems['bonus']['total_usd'] += $expenseItems['bonus']['amounts'][$curr];
                    $expenseItems['total_payroll']['total_usd'] += $tPayroll;
                } else {
                    $expenseItems['salary']['amounts'][$curr] = 0;
                    $expenseItems['allowance']['amounts'][$curr] = 0;
                    $expenseItems['bonus']['amounts'][$curr] = 0;
                    $expenseItems['total_payroll']['amounts'][$curr] = 0;
                }

                $borrInt = (double) DB::table('borrowing_repayments')
                    ->join('borrowings', 'borrowing_repayments.borrowing_id', '=', 'borrowings.id')
                    ->where('borrowings.currency', 'LIKE', $curr . '%')
                    ->whereBetween('borrowing_repayments.payment_date', [$fromDate, $toDate])
                    ->sum('borrowing_repayments.interest_paid');
                $expenseItems['borrowing_interest']['amounts'][$curr] = $borrInt;
                $totalExpenses[$curr] += $borrInt;
                $expenseItems['borrowing_interest']['total_usd'] += ($curr === 'USD' ? $borrInt : $borrInt / $exchangeRate);

                $otherExp = (double) DB::table('miscellaneous_transactions')
                    ->where('type', 'expense')
                    ->where('currency', 'LIKE', $curr . '%')
                    ->whereBetween('transaction_date', [$fromDate, $toDate])
                    ->sum('amount');
                $expenseItems['other_expenses']['amounts'][$curr] = $otherExp;
                $totalExpenses[$curr] += $otherExp;
                $expenseItems['other_expenses']['total_usd'] += ($curr === 'USD' ? $otherExp : $otherExp / $exchangeRate);

                $netIncome[$curr] = $totalRevenue[$curr] - $totalExpenses[$curr];

                $grandTotalRevenueUSD += ($curr === 'USD' ? $totalRevenue[$curr] : $totalRevenue[$curr] / $exchangeRate);
                $grandTotalExpensesUSD += ($curr === 'USD' ? $totalExpenses[$curr] : $totalExpenses[$curr] / $exchangeRate);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['from_date' => $fromDate, 'to_date' => $toDate],
                    'currencies' => $currencies,
                    'exchange_rate' => $exchangeRate,
                    'revenue' => array_values($revenueItems),
                    'total_revenue' => $totalRevenue,
                    'grand_total_revenue_usd' => $grandTotalRevenueUSD,
                    'expenses' => array_values($expenseItems),
                    'total_expenses' => $totalExpenses,
                    'grand_total_expenses_usd' => $grandTotalExpensesUSD,
                    'net_income' => $netIncome,
                    'grand_net_income_usd' => $grandTotalRevenueUSD - $grandTotalExpensesUSD,
                ],
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
