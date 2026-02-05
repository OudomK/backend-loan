<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Mock Request Parameters (Adjust these to match the user's likely filter)
$fromDate = '2026-02-01'; // Assuming Feb 2026 based on screenshot context
$toDate = '2026-02-28';
$currency = null; // 'USD', 'KHR', or null

echo "Testing Interest Report Logic...\n";
echo "From: $fromDate, To: $toDate\n\n";

// 1. Check if there are ANY loans disbured in this period
$disbursedLoans = DB::table('loans')
    ->whereBetween('start_date', [$fromDate, $toDate])
    ->count();
echo "Loans Disbursed in Interval: $disbursedLoans\n";

// 2. Check if there are ANY transactions in this period
$transactions = DB::table('repayment_transactions')
    ->whereBetween('transaction_date', [$fromDate, $toDate])
    ->count();
echo "Transactions in Interval: $transactions\n";

// 3. Run the Main Controller Query Logic
$query = DB::table('loans')
    ->selectRaw('
        loans.id as loan_id,
        loans.loan_code,
        customers.last_name, 
        loans.start_date,
        loans.admin_fee
    ')
    ->join('customers', 'loans.customer_id', '=', 'customers.id')
    ->leftJoin('branches', 'loans.branch_id', '=', 'branches.id')
    ->leftJoin('loan_products', 'loans.product_id', '=', 'loan_products.id');

// Filter Block from Controller
$query->where(function ($q) use ($fromDate, $toDate) {
    // A: Has Transactions
    $q->whereExists(function ($sub) use ($fromDate, $toDate) {
        $sub->select(DB::raw(1))
            ->from('repayment_transactions')
            ->whereColumn('repayment_transactions.loan_id', 'loans.id')
            ->whereBetween('transaction_date', [$fromDate, $toDate]);
    });

    // B: OR Disbursed in Range
    if ($fromDate && $toDate) {
        $q->orWhereBetween('loans.start_date', [$fromDate, $toDate]);
    }
});

if ($currency) {
    $query->where('loans.currency', $currency);
}

// Inspect SQL
// echo "SQL: " . $query->toSql() . "\n";
// echo "Bindings: " . json_encode($query->getBindings()) . "\n\n";

$results = $query->get();

echo "Total Results Returned: " . $results->count() . "\n";

if ($results->count() > 0) {
    echo "First 3 Rows:\n";
    foreach ($results->take(3) as $row) {
        echo "- Loan: {$row->loan_code}, Date: {$row->start_date}, Fee: {$row->admin_fee}\n";
    }
} else {
    echo "NO DATA FOUND.\n";
    // Debug: Why?
    // Let's remove the WHERE clause and see bounds of existing data
    $minDate = DB::table('loans')->min('start_date');
    $maxDate = DB::table('loans')->max('start_date');
    echo "\nDebug Info:\n";
    echo "Min Loan Start Date: $minDate\n";
    echo "Max Loan Start Date: $maxDate\n";
}
