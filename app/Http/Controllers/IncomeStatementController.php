<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeStatementController extends Controller
{
    private function recognizedAdminFeeAmount(object $loan): float
    {
        $feeType = strtolower(trim((string) ($loan->admin_fee_type ?? 'one_time')));
        $adminFeeRate = (float) ($loan->admin_fee ?? 0);

        if ($adminFeeRate <= 0 || $feeType === 'monthly') {
            return 0.0;
        }

        $loanAmount = (float) ($loan->amount ?? 0);
        $disbursedAmount = (float) ($loan->disbursed_amount ?? $loanAmount);

        if (in_array($feeType, ['deducted_upfront', 'capitalized_upfront'], true)) {
            $storedDifference = round(abs($loanAmount - $disbursedAmount), 2);
            if ($storedDifference > 0) {
                return $storedDifference;
            }
        }

        $baseAmount = $loanAmount;
        if ($feeType === 'capitalized_upfront' && $loanAmount > 0) {
            $baseAmount = $loanAmount / (1 + ($adminFeeRate / 100));
        }

        return round($baseAmount * ($adminFeeRate / 100), 2);
    }

    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currencyParam = $request->query('currency');

        Log::info("Income Statement: from=$fromDate, to=$toDate, currency=$currencyParam");

        try {
            $exchangeRate = (float) (\App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
                ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
                ?? 4000);
            $exchangeRate = max(1, $exchangeRate);

            $currencies = [];
            if ($currencyParam && $currencyParam !== 'all') {
                $currencies = [$currencyParam];
            } else {
                $loanCurrs = DB::table('loans')->whereNull('deleted_at')->whereBetween('start_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();
                $miscCurrs = DB::table('miscellaneous_transactions')->whereNull('deleted_at')->whereBetween('transaction_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();
                $borrowingCurrs = DB::table('borrowings')->whereNull('deleted_at')->whereBetween('borrowing_date', [$fromDate, $toDate])->pluck('currency')->unique()->toArray();

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

            // ── REVENUE: Fully dynamic from revenue_categories table ──
            $revenueItems = [];

            $dbRevenueCategories = \App\Models\RevenueCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($dbRevenueCategories as $cat) {
                $key = 'rev_cat_' . $cat->id;
                $revenueItems[$key] = [
                    'label' => $cat->name,
                    'amounts' => [],
                    'total_usd' => 0,
                    '_category_id' => $cat->id,
                    '_category_name' => $cat->name,
                    '_category_slug' => $cat->slug,
                ];
            }

            // ── EXPENSES: Core hardcoded + dynamic from expense_categories ──
            $expenseItems = [
                'salary' => ['label' => 'Salary Expense', 'amounts' => [], 'total_usd' => 0],
                'borrowing_interest' => ['label' => 'Borrowing Interest Expense', 'amounts' => [], 'total_usd' => 0],
                'staff_benefit' => ['label' => 'Staff Benefit Expense', 'amounts' => [], 'total_usd' => 0],
            ];

            // Dynamically load active expense categories from the database
            $dbCategories = \App\Models\ExpenseCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            foreach ($dbCategories as $cat) {
                $key = 'cat_' . $cat->id;
                $expenseItems[$key] = [
                    'label' => $cat->name,
                    'amounts' => [],
                    'total_usd' => 0,
                    '_category_id' => $cat->id,
                    '_category_name' => $cat->name,
                ];
            }

            // Always add "Other Expense" as a catch-all at the end
            $expenseItems['other_expenses'] = ['label' => 'Other Expense', 'amounts' => [], 'total_usd' => 0];

            $totalRevenue = [];
            $totalExpenses = [];
            $netIncome = [];
            $grandTotalRevenueUSD = 0;
            $grandTotalExpensesUSD = 0;

            foreach ($currencies as $curr) {
                $sumMiscExpense = function (array $categories) use ($curr, $fromDate, $toDate) {
                    return (double) DB::table('miscellaneous_transactions')
                        ->where('type', 'expense')
                        ->whereNull('deleted_at')
                        ->where('currency', 'LIKE', $curr . '%')
                        ->whereBetween('transaction_date', [$fromDate, $toDate])
                        ->where(function ($q) use ($categories) {
                            foreach ($categories as $category) {
                                $q->orWhereRaw('LOWER(category) = ?', [strtolower($category)]);
                            }
                        })
                        ->sum('amount');
                };

                // Initialize totals
                $totalRevenue[$curr] = 0;
                $totalExpenses[$curr] = 0;

                // ── REVENUE: Fully dynamic ──────────────────────────────
                foreach ($revenueItems as $key => &$item) {
                    $catName = $item['_category_name'];
                    $catId = $item['_category_id'];
                    $catSlug = $item['_category_slug'] ?? null;
                    $amount = 0;

                    // Determine source based on category slug or fallback to name keyword match
                    $lowerCatName = strtolower($catName);

                    if ($catSlug === 'interest_income' || str_contains($lowerCatName, 'interest')) {
                        // Interest Income: from repayment transactions
                        $amount = (double) DB::table('repayment_transactions')
                            ->join('loans', 'repayment_transactions.loan_id', '=', 'loans.id')
                            ->whereNull('repayment_transactions.deleted_at')
                            ->whereNull('loans.deleted_at')
                            ->where('loans.currency', 'LIKE', $curr . '%')
                            ->whereBetween('repayment_transactions.transaction_date', [$fromDate, $toDate])
                            ->sum('repayment_transactions.interest_paid');

                    } elseif ($catSlug === 'admin_fee' || str_contains($lowerCatName, 'admin')) {
                        // Admin Fee: calculated from loans
                        $amount = DB::table('loans')
                            ->select(['amount', 'disbursed_amount', 'admin_fee', 'admin_fee_type'])
                            ->whereNull('loans.deleted_at')
                            ->where('loans.currency', 'LIKE', $curr . '%')
                            ->whereBetween('loans.start_date', [$fromDate, $toDate])
                            ->get()
                            ->sum(fn($loan) => $this->recognizedAdminFeeAmount($loan));

                    } elseif ($catSlug === 'other_revenue' || str_contains($lowerCatName, 'other')) {
                        // Other Revenue: catch-all from miscellaneous_transactions
                        $amount = (double) DB::table('miscellaneous_transactions')
                            ->where('type', 'revenue')
                            ->whereNull('deleted_at')
                            ->where('currency', 'LIKE', $curr . '%')
                            ->whereBetween('transaction_date', [$fromDate, $toDate])
                            ->sum('amount');

                    } else {
                        // Default: query from revenues table by category_id
                        $amount = (double) DB::table('revenues')
                            ->where('revenue_category_id', $catId)
                            ->whereNull('deleted_at')
                            ->where('currency', 'LIKE', $curr . '%')
                            ->whereBetween('transaction_date', [$fromDate, $toDate])
                            ->sum('amount');
                    }

                    $item['amounts'][$curr] = $amount;
                    $totalRevenue[$curr] += $amount;
                    $item['total_usd'] += ($curr === 'USD' ? $amount : $amount / $exchangeRate);
                }
                unset($item);

                // ── EXPENSES ─────────────────────────────────────────────
                if ($curr === 'USD') {
                    $pQuery = DB::table('payrolls')->whereNull('deleted_at')->whereBetween('payment_date', [$fromDate, $toDate]);

                    // Salary Expense = Base Salary - Deduction
                    $salary = (double) (clone $pQuery)->sum('salary') - (double) (clone $pQuery)->sum('deduction');
                    $staffBenefit = (double) (clone $pQuery)->sum('allowance') + (double) (clone $pQuery)->sum('bonus');

                    $expenseItems['salary']['amounts'][$curr] = $salary;
                    $expenseItems['staff_benefit']['amounts'][$curr] = $staffBenefit;

                    // Add explicitly to total expenses so it perfectly matches the UI items
                    $totalExpenses[$curr] += ($salary + $staffBenefit);

                    $expenseItems['salary']['total_usd'] += $expenseItems['salary']['amounts'][$curr];
                    $expenseItems['staff_benefit']['total_usd'] += $expenseItems['staff_benefit']['amounts'][$curr];
                } else {
                    $expenseItems['salary']['amounts'][$curr] = 0;
                    $expenseItems['staff_benefit']['amounts'][$curr] = 0;
                }

                $borrInt = (double) DB::table('borrowing_repayments')
                    ->join('borrowings', 'borrowing_repayments.borrowing_id', '=', 'borrowings.id')
                    ->whereNull('borrowing_repayments.deleted_at')
                    ->whereNull('borrowings.deleted_at')
                    ->where('borrowings.currency', 'LIKE', $curr . '%')
                    ->whereBetween('borrowing_repayments.payment_date', [$fromDate, $toDate])
                    ->sum('borrowing_repayments.interest_paid');
                $expenseItems['borrowing_interest']['amounts'][$curr] = $borrInt;
                $totalExpenses[$curr] += $borrInt;
                $expenseItems['borrowing_interest']['total_usd'] += ($curr === 'USD' ? $borrInt : $borrInt / $exchangeRate);

                // Dynamic: query each DB expense category by ID (new) and name (legacy)
                $matchedCategoryNames = [];
                foreach ($expenseItems as $key => &$item) {
                    if (!isset($item['_category_name']) || !isset($item['_category_id'])) {
                        continue;
                    }
                    $catName = $item['_category_name'];
                    $catId = $item['_category_id'];

                    // 1. From new `expenses` table (reliable, uses ID)
                    $amountExpenses = (double) DB::table('expenses')
                        ->where('expense_category_id', $catId)
                        ->whereNull('deleted_at')
                        ->where('currency', 'LIKE', $curr . '%')
                        ->whereBetween('transaction_date', [$fromDate, $toDate])
                        ->sum('amount');

                    // 2. From legacy `miscellaneous_transactions` table (brittle, uses string name)
                    $amountMisc = (double) DB::table('miscellaneous_transactions')
                        ->where('type', 'expense')
                        ->whereNull('deleted_at')
                        ->where('currency', 'LIKE', $curr . '%')
                        ->whereBetween('transaction_date', [$fromDate, $toDate])
                        ->whereRaw('LOWER(category) = ?', [strtolower($catName)])
                        ->sum('amount');

                    $amount = $amountExpenses + $amountMisc;

                    $item['amounts'][$curr] = $amount;
                    $totalExpenses[$curr] += $amount;
                    $item['total_usd'] += ($curr === 'USD' ? $amount : $amount / $exchangeRate);
                    $matchedCategoryNames[] = strtolower($catName);
                }
                unset($item);

                // "Other Expense" catch-all: sum all expense transactions not matched above
                $otherExpense = (double) DB::table('miscellaneous_transactions')
                    ->where('type', 'expense')
                    ->whereNull('deleted_at')
                    ->where('currency', 'LIKE', $curr . '%')
                    ->whereBetween('transaction_date', [$fromDate, $toDate])
                    ->when(!empty($matchedCategoryNames), function ($q) use ($matchedCategoryNames) {
                        $q->whereNotIn(DB::raw('LOWER(category)'), $matchedCategoryNames);
                    })
                    ->sum('amount');
                $expenseItems['other_expenses']['amounts'][$curr] = $otherExpense;
                $totalExpenses[$curr] += $otherExpense;
                $expenseItems['other_expenses']['total_usd'] += ($curr === 'USD' ? $otherExpense : $otherExpense / $exchangeRate);

                $netIncome[$curr] = $totalRevenue[$curr] - $totalExpenses[$curr];

                $grandTotalRevenueUSD += ($curr === 'USD' ? $totalRevenue[$curr] : $totalRevenue[$curr] / $exchangeRate);
                $grandTotalExpensesUSD += ($curr === 'USD' ? $totalExpenses[$curr] : $totalExpenses[$curr] / $exchangeRate);
            }

            // Strip internal metadata before sending response
            $cleanedRevenueItems = array_map(function ($item) {
                unset($item['_category_id'], $item['_category_name'], $item['_category_slug']);
                return $item;
            }, $revenueItems);

            $cleanedExpenseItems = array_map(function ($item) {
                unset($item['_category_id'], $item['_category_name']);
                return $item;
            }, $expenseItems);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['from_date' => $fromDate, 'to_date' => $toDate],
                    'currencies' => $currencies,
                    'exchange_rate' => $exchangeRate,
                    'revenue' => array_values($cleanedRevenueItems),
                    'total_revenue' => $totalRevenue,
                    'grand_total_revenue_usd' => $grandTotalRevenueUSD,
                    'expenses' => array_values($cleanedExpenseItems),
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
