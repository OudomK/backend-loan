<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Write-off Report Query ===\n\n";

// Test basic loan query
$loans = DB::table('loans')
    ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
    ->select([
        'loans.id',
        'loans.loan_code',
        'loans.start_date',
        'borrowers.first_name',
        'borrowers.last_name'
    ])
    ->get();

echo "Total loans found: " . $loans->count() . "\n\n";

if ($loans->count() > 0) {
    echo "Sample loans:\n";
    foreach ($loans->take(5) as $loan) {
        echo "  - {$loan->loan_code} | Disbursed: {$loan->start_date} | {$loan->last_name} {$loan->first_name}\n";
    }
}

echo "\n=== END ===\n";
