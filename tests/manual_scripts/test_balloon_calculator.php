<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BalloonPaymentCalculator;

echo "=== Testing Balloon Payment Calculator ===\n\n";

// Test Case 1: Interest-Only Balloon
echo "TEST 1: Interest-Only Balloon Payment\n";
echo "--------------------------------------\n";
echo "Principal: $10,000\n";
echo "Interest Rate: 12% per year\n";
echo "Duration: 12 months\n";
echo "Start Date: 2026-02-01\n\n";

$loanData1 = [
    'amount' => 10000,
    'interest_rate' => 12,
    'duration_months' => 12,
    'start_date' => '2026-02-01',
];

$schedule1 = BalloonPaymentCalculator::generateSchedule($loanData1, 'interest_only');

echo "Payment Schedule:\n";
$totalPrincipal = 0;
$totalInterest = 0;
foreach ($schedule1 as $payment) {
    $balloon = $payment['is_balloon'] ? ' 🎈 BALLOON!' : '';
    echo sprintf(
        "  #%d - %s | Principal: $%.2f | Interest: $%.2f | Total: $%.2f%s\n",
        $payment['payment_number'],
        $payment['payment_date'],
        $payment['principal_amount'],
        $payment['interest_amount'],
        $payment['total_paid'],
        $balloon
    );
    $totalPrincipal += $payment['principal_amount'];
    $totalInterest += $payment['interest_amount'];
}

echo "\nSummary:\n";
echo "  Total Principal: $" . number_format($totalPrincipal, 2) . "\n";
echo "  Total Interest: $" . number_format($totalInterest, 2) . "\n";
echo "  Grand Total: $" . number_format($totalPrincipal + $totalInterest, 2) . "\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// Test Case 2: Minimal Payment Balloon
echo "TEST 2: Minimal Payment Balloon\n";
echo "--------------------------------------\n";
echo "Principal: $10,000\n";
echo "Interest Rate: 12% per year\n";
echo "Duration: 12 months\n";
echo "Monthly Payment: $150 (auto-calculated)\n";
echo "Start Date: 2026-02-01\n\n";

$schedule2 = BalloonPaymentCalculator::generateSchedule($loanData1, 'minimal_payment');

echo "Payment Schedule:\n";
$totalPrincipal2 = 0;
$totalInterest2 = 0;
foreach ($schedule2 as $payment) {
    $balloon = $payment['is_balloon'] ? ' 🎈 BALLOON!' : '';
    $balance = isset($payment['remaining_balance']) ? " | Balance: $" . number_format($payment['remaining_balance'], 2) : '';
    echo sprintf(
        "  #%d - %s | Principal: $%.2f | Interest: $%.2f | Total: $%.2f%s%s\n",
        $payment['payment_number'],
        $payment['payment_date'],
        $payment['principal_amount'],
        $payment['interest_amount'],
        $payment['total_paid'],
        $balance,
        $balloon
    );
    $totalPrincipal2 += $payment['principal_amount'];
    $totalInterest2 += $payment['interest_amount'];
}

echo "\nSummary:\n";
echo "  Total Principal: $" . number_format($totalPrincipal2, 2) . "\n";
echo "  Total Interest: $" . number_format($totalInterest2, 2) . "\n";
echo "  Grand Total: $" . number_format($totalPrincipal2 + $totalInterest2, 2) . "\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// Test Case 3: Short-term Balloon
echo "TEST 3: Short-term (6 months) Interest-Only\n";
echo "--------------------------------------\n";
echo "Principal: $5,000\n";
echo "Interest Rate: 10% per year\n";
echo "Duration: 6 months\n\n";

$loanData3 = [
    'amount' => 5000,
    'interest_rate' => 10,
    'duration_months' => 6,
    'start_date' => '2026-02-01',
];

$schedule3 = BalloonPaymentCalculator::generateSchedule($loanData3, 'interest_only');

echo "Payment Schedule:\n";
$totalPrincipal3 = 0;
$totalInterest3 = 0;
foreach ($schedule3 as $payment) {
    $balloon = $payment['is_balloon'] ? ' 🎈 BALLOON!' : '';
    echo sprintf(
        "  #%d - %s | Principal: $%.2f | Interest: $%.2f | Total: $%.2f%s\n",
        $payment['payment_number'],
        $payment['payment_date'],
        $payment['principal_amount'],
        $payment['interest_amount'],
        $payment['total_paid'],
        $balloon
    );
    $totalPrincipal3 += $payment['principal_amount'];
    $totalInterest3 += $payment['interest_amount'];
}

echo "\nSummary:\n";
echo "  Total Principal: $" . number_format($totalPrincipal3, 2) . "\n";
echo "  Total Interest: $" . number_format($totalInterest3, 2) . "\n";
echo "  Grand Total: $" . number_format($totalPrincipal3 + $totalInterest3, 2) . "\n";

echo "\n=== All Tests Complete! ===\n";
