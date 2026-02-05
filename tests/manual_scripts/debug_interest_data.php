<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DEBUGGING INTEREST INCOME REPORT DATA ===\n\n";

// 1. Check total loans
$totalLoans = DB::table('loans')->count();
echo "1. Total Loans in Database: $totalLoans\n\n";

// 2. Check sample loans with dates
echo "2. Sample Loans (first 5):\n";
$sampleLoans = DB::table('loans')
    ->select('id', 'loan_code', 'start_date', 'currency', 'amount', 'borrower_id')
    ->limit(5)
    ->get();
foreach ($sampleLoans as $loan) {
    echo "   - ID: {$loan->id}, Code: {$loan->loan_code}, Date: {$loan->start_date}, Currency: {$loan->currency}, Amount: {$loan->amount}\n";
}
echo "\n";

// 3. Check date range of loans
$dateRange = DB::table('loans')
    ->selectRaw('MIN(start_date) as earliest, MAX(start_date) as latest')
    ->first();
echo "3. Loan Date Range:\n";
echo "   - Earliest: {$dateRange->earliest}\n";
echo "   - Latest: {$dateRange->latest}\n\n";

// 4. Check total repayment transactions
$totalTransactions = DB::table('repayment_transactions')->count();
echo "4. Total Repayment Transactions: $totalTransactions\n\n";

// 5. Check sample transactions
echo "5. Sample Transactions (first 5):\n";
$sampleTxns = DB::table('repayment_transactions')
    ->select('id', 'loan_id', 'transaction_date', 'interest_paid', 'penalty_paid')
    ->limit(5)
    ->get();
foreach ($sampleTxns as $txn) {
    echo "   - ID: {$txn->id}, Loan: {$txn->loan_id}, Date: {$txn->transaction_date}, Interest: {$txn->interest_paid}, Penalty: {$txn->penalty_paid}\n";
}
echo "\n";

// 6. Check transaction date range
if ($totalTransactions > 0) {
    $txnDateRange = DB::table('repayment_transactions')
        ->selectRaw('MIN(transaction_date) as earliest, MAX(transaction_date) as latest')
        ->first();
    echo "6. Transaction Date Range:\n";
    echo "   - Earliest: {$txnDateRange->earliest}\n";
    echo "   - Latest: {$txnDateRange->latest}\n\n";
}

// 7. Test the actual query with a wide date range
echo "7. Testing Interest Income Query (Last 6 months):\n";
$fromDate = date('Y-m-d', strtotime('-6 months'));
$toDate = date('Y-m-d');
echo "   - From: $fromDate\n";
echo "   - To: $toDate\n";

$testResults = DB::table('loans')
    ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
    ->select([
        'loans.id',
        'loans.loan_code',
        'loans.start_date',
        'borrowers.first_name',
        'borrowers.last_name'
    ])
    ->where(function ($q) use ($fromDate, $toDate) {
        $q->whereExists(function ($sub) use ($fromDate, $toDate) {
            $sub->select(DB::raw(1))
                ->from('repayment_transactions')
                ->whereColumn('repayment_transactions.loan_id', 'loans.id')
                ->whereBetween('transaction_date', [$fromDate, $toDate]);
        });

        $q->orWhereBetween('loans.start_date', [$fromDate, $toDate]);
    })
    ->limit(10)
    ->get();

echo "   - Results Found: " . $testResults->count() . "\n";
foreach ($testResults as $result) {
    echo "      * Loan {$result->loan_code} ({$result->start_date}) - {$result->last_name} {$result->first_name}\n";
}

echo "\n=== END DEBUG ===\n";
