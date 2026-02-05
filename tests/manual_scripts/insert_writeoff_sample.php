<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Inserting Write-off Sample Data ===\n\n";

// Get first 2 loans that are NOT written off
$loans = DB::table('loans')
    ->whereNull('written_off_at')
    ->limit(2)
    ->get();

if ($loans->isEmpty()) {
    echo "❌ No loans available to mark as written-off!\n";
    exit;
}

echo "Found {$loans->count()} loans to write-off:\n\n";

$writeOffReasons = [
    'Borrower deceased',
    'Borrower absconded',
    'Business failed - irrecoverable',
    'Natural disaster - total loss',
];

$classifications = [
    'Loss Loan',
    'Doubtful Loan',
];

foreach ($loans as $index => $loan) {
    $reason = $writeOffReasons[array_rand($writeOffReasons)];
    $classification = $classifications[array_rand($classifications)];

    // Calculate write-off date (between 30-90 days ago)
    $daysAgo = rand(30, 90);
    $writeOffDate = date('Y-m-d', strtotime("-$daysAgo days"));

    // Calculate write-off balance (70-90% of original amount)
    $writeOffPercent = rand(70, 90) / 100;
    $writeOffBalance = $loan->amount * $writeOffPercent;

    DB::table('loans')
        ->where('id', $loan->id)
        ->update([
            'status' => 'completed',  // Use valid enum value (no 'written_off' in enum)
            'written_off_at' => $writeOffDate,
            'write_off_balance' => $writeOffBalance,
            'write_off_reason' => $reason,
            'classify_wo' => $classification,
            'updated_at' => now(),
        ]);

    echo "✅ Loan #{$loan->loan_code}\n";
    echo "   - Written off on: $writeOffDate\n";
    echo "   - Original amount: $" . number_format($loan->amount, 2) . "\n";
    echo "   - Write-off balance: $" . number_format($writeOffBalance, 2) . "\n";
    echo "   - Reason: $reason\n";
    echo "   - Classification: $classification\n\n";
}

echo "=== Write-off Complete! ===\n";
echo "\nNow you can:\n";
echo "  1. Export 'Write-Off Report' from frontend\n";
echo "  2. Select date range that includes the write-off dates\n";
echo "  3. You should see {$loans->count()} written-off loans!\n";
