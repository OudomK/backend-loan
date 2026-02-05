<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Loan Collection with Date Range ===\n\n";

// Test with the same dates user selected
$fromDate = '2025-01-01';
$toDate = '2026-04-02';  // Assuming MM/DD/YYYY format from dialog

echo "Filter: FROM $fromDate TO $toDate\n\n";

$payments = DB::table('payments')
    ->join('loans', 'payments.loan_id', '=', 'loans.id')
    ->whereBetween('payments.payment_date', [$fromDate, $toDate])
    ->select(
        'payments.payment_date',
        'loans.loan_code',
        'loans.currency',
        'payments.principal_amount',
        'payments.interest_amount'
    )
    ->orderBy('payments.payment_date')
    ->get();

echo "Total payments found: " . $payments->count() . "\n\n";

// Group by currency
$byCurrency = [];
foreach ($payments as $p) {
    $curr = $p->currency;
    if (!isset($byCurrency[$curr])) {
        $byCurrency[$curr] = [];
    }
    $byCurrency[$curr][] = $p;
}

echo "Grouped by Currency:\n";
foreach ($byCurrency as $curr => $items) {
    echo "  - '$curr': " . count($items) . " payments\n";
}

echo "\nSample from each currency:\n";
foreach ($byCurrency as $curr => $items) {
    echo "\n  $curr (first 3):\n";
    foreach (array_slice($items, 0, 3) as $p) {
        echo "    {$p->payment_date} | {$p->loan_code} | P: {$p->principal_amount}\n";
    }
}

echo "\n=== END ===\n";
