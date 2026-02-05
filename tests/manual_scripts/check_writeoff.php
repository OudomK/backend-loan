<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Write-off Data ===\n\n";

// Count written-off loans
$writtenOffCount = DB::table('loans')
    ->whereNotNull('written_off_at')
    ->count();

echo "Written-off loans: $writtenOffCount\n\n";

if ($writtenOffCount > 0) {
    echo "Sample written-off loans:\n";
    $loans = DB::table('loans')
        ->whereNotNull('written_off_at')
        ->select('loan_code', 'written_off_at', 'amount', 'write_off_reason')
        ->limit(5)
        ->get();

    foreach ($loans as $loan) {
        echo "  - {$loan->loan_code} | Written off: {$loan->written_off_at} | Amount: {$loan->amount}\n";
    }
} else {
    echo "❌ No loans have been written off yet!\n";
    echo "\nTo write-off a loan, you need to:\n";
    echo "  1. Mark loan status as 'written_off'\n";
    echo "  2. Set 'written_off_at' date\n";
    echo "  3. Optionally set 'write_off_reason' and other fields\n";
}

echo "\n=== END ===\n";
