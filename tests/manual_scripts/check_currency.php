<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Currency Distribution ===\n\n";

// Check payments with loans currency
$currencyGroups = DB::table('payments')
    ->join('loans', 'payments.loan_id', '=', 'loans.id')
    ->select('loans.currency', DB::raw('COUNT(*) as count'))
    ->groupBy('loans.currency')
    ->get();

echo "Payments by currency:\n";
foreach ($currencyGroups as $group) {
    echo "  - {$group->currency}: {$group->count} payments\n";
}

// Check sample data
echo "\nSample payment data:\n";
$sample = DB::table('payments')
    ->join('loans', 'payments.loan_id', '=', 'loans.id')
    ->select('payments.id', 'payments.payment_date', 'loans.currency', 'payments.principal_amount', 'payments.interest_amount')
    ->limit(5)
    ->get();

foreach ($sample as $p) {
    echo "  Payment #{$p->id} | {$p->currency} | Principal: {$p->principal_amount} | Interest: {$p->interest_amount}\n";
}

echo "\n=== END ===\n";
