<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Inserting USD Loan with Payments ===\n\n";

// Get a borrower
$borrower = DB::table('borrowers')->first();

if (!$borrower) {
    echo "❌ No borrowers found! Please create a borrower first.\n";
    exit;
}

// Get a loan officer
$officer = DB::table('loan_officers')->first();

// Create new USD loan
$loanCode = 'USD-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
$startDate = '2026-02-01';
$amount = 2000.00;
$interestRate = 12.0;
$durationMonths = 12;

$loanId = DB::table('loans')->insertGetId([
    'loan_code' => $loanCode,
    'borrower_id' => $borrower->id,
    'loan_officer_id' => $officer->id ?? null,
    'amount' => $amount,
    'interest_rate' => $interestRate,
    'duration_months' => $durationMonths,
    'start_date' => $startDate,
    'currency' => 'USD ($)',
    'repayment_method' => 'monthly',
    'status' => 'active',
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "✅ Created Loan: $loanCode (ID: $loanId)\n";
echo "   - Amount: $$amount USD\n";
echo "   - Interest Rate: $interestRate%\n";
echo "   - Duration: $durationMonths months\n";
echo "   - Start Date: $startDate\n\n";

// Calculate monthly payment
$monthlyPrincipal = $amount / $durationMonths;
$monthlyInterest = ($amount * $interestRate / 100) / 12;

echo "Creating payment schedule...\n\n";

// Create 12 monthly payments
for ($i = 1; $i <= $durationMonths; $i++) {
    $paymentDate = date('Y-m-d', strtotime($startDate . " +$i months"));

    DB::table('payments')->insert([
        'loan_id' => $loanId,
        'payment_number' => $i,
        'payment_date' => $paymentDate,
        'principal_amount' => round($monthlyPrincipal, 2),
        'interest_amount' => round($monthlyInterest, 2),
        'penalty_amount' => 0,
        'total_paid' => round($monthlyPrincipal + $monthlyInterest, 2),
        'payment_method' => 'Cash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "  Payment $i: $paymentDate | Principal: $" . round($monthlyPrincipal, 2) . " | Interest: $" . round($monthlyInterest, 2) . "\n";
}

echo "\n=== Insertion Complete! ===\n";
echo "\nSummary:\n";
echo "  - 1 new USD loan created\n";
echo "  - $durationMonths payment schedules created\n";
echo "\nYou can now export Loan Collection Report and see both USD and KHR loans!\n";
