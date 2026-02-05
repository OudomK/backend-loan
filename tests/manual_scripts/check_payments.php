<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Loan Collection Data ===\n\n";

// Check payments table
$paymentsCount = DB::table('payments')->count();
echo "Total payments: $paymentsCount\n\n";

if ($paymentsCount > 0) {
    echo "Sample payments:\n";
    $payments = DB::table('payments')
        ->select('id', 'loan_id', 'payment_date', 'principal_amount', 'interest_amount')
        ->limit(5)
        ->get();

    foreach ($payments as $p) {
        echo "  Payment #{$p->id} | Date: {$p->payment_date} | Principal: {$p->principal_amount} | Interest: {$p->interest_amount}\n";
    }
} else {
    echo "❌ No payments found in database!\n";
    echo "\n💡 Payments are scheduled installments for loans.\n";
    echo "   They should be created when a loan is disbursed.\n";
}

echo "\n=== END ===\n";
