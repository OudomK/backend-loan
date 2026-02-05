<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking USD Loan Payments ===\n\n";

// Find USD loans
$usdLoans = DB::table('loans')
    ->where('currency', 'USD ($)')
    ->select('id', 'loan_code', 'amount', 'start_date')
    ->get();

echo "USD Loans found: " . $usdLoans->count() . "\n\n";

foreach ($usdLoans as $loan) {
    echo "Loan: {$loan->loan_code} (Amount: \${$loan->amount})\n";
    echo "  Start Date: {$loan->start_date}\n";

    $payments = DB::table('payments')
        ->where('loan_id', $loan->id)
        ->orderBy('payment_date')
        ->select('payment_number', 'payment_date', 'principal_amount', 'interest_amount')
        ->get();

    echo "  Payments: " . $payments->count() . "\n";

    if ($payments->count() > 0) {
        echo "  First payment: {$payments->first()->payment_date}\n";
        echo "  Last payment: {$payments->last()->payment_date}\n";

        echo "\n  All payments:\n";
        foreach ($payments as $p) {
            echo "    #{$p->payment_number}: {$p->payment_date} | P: \${$p->principal_amount} | I: \${$p->interest_amount}\n";
        }
    }
    echo "\n";
}

echo "=== Recommended Date Range for Export ===\n";
$firstPayment = DB::table('payments')
    ->join('loans', 'payments.loan_id', '=', 'loans.id')
    ->where('loans.currency', 'USD ($)')
    ->orderBy('payments.payment_date')
    ->select('payments.payment_date')
    ->first();

$lastPayment = DB::table('payments')
    ->join('loans', 'payments.loan_id', '=', 'loans.id')
    ->where('loans.currency', 'USD ($)')
    ->orderBy('payments.payment_date', 'desc')
    ->select('payments.payment_date')
    ->first();

if ($firstPayment && $lastPayment) {
    echo "From: {$firstPayment->payment_date}\n";
    echo "To: {$lastPayment->payment_date}\n";
}

echo "\n=== END ===\n";
