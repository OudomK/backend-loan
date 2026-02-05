<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\LoanCalculator;

$calculator = new LoanCalculator();

$principal = 1000;
$rate = 10; // 10% (System treats as Monthly)
$duration = 12; // 12 months
$startDate = '2026-02-04';
$currency = 'USD';

// 'negotiable' defaults to 'fixed_monthly' for the initial view
$schedule = $calculator->calculateLoanWithDates(
    $principal,
    $rate,
    $duration,
    'fixed_monthly',
    $startDate,
    $currency
);

echo "Loan: $$principal, Duration: $duration months, Rate: $rate% (Monthly Calculation)\n";
echo "Method: Negotiable (Defaults to Fixed Monthly)\n\n";
echo str_pad("No", 4) . str_pad("Date", 12) . str_pad("Principal", 12) . str_pad("Interest", 12) . str_pad("Total", 12) . str_pad("Balance", 12) . "\n";
echo str_repeat("-", 64) . "\n";

$totalPrincipal = 0;
$totalInterest = 0;

foreach ($schedule as $row) {
    echo str_pad($row['period'], 4);
    echo str_pad($row['date'], 12);
    echo str_pad(number_format($row['principal'], 2), 12);
    echo str_pad(number_format($row['interest'], 2), 12);
    echo str_pad(number_format($row['payment'], 2), 12);
    echo str_pad(number_format($row['balance'], 2), 12);
    echo "\n";

    $totalPrincipal += $row['principal'];
    $totalInterest += $row['interest'];
}

echo str_repeat("-", 64) . "\n";
echo str_pad("TOT", 16) . str_pad(number_format($totalPrincipal, 2), 12) . str_pad(number_format($totalInterest, 2), 12) . str_pad(number_format($totalPrincipal + $totalInterest, 2), 12) . "\n";
