<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Loan;
use Carbon\Carbon;

echo "--- Testing Repayment Data Logic ---\n";

$loans = Loan::with('payments')->where('status', 'active')->take(5)->get();

foreach ($loans as $loan) {
    echo "Loan ID: " . $loan->id . "\n";
    echo "Currency DB Value: '" . $loan->currency . "'\n";

    $symbol = (strpos($loan->currency, 'KHR') !== false) ? '៛' : '$';
    echo "Calculated Symbol: " . $symbol . "\n";

    $nextPayment = $loan->payments()
        ->whereRaw('total_paid < (principal_amount + interest_amount)')
        ->orderBy('payment_date', 'asc')
        ->first();

    if ($nextPayment) {
        $today = Carbon::today();
        $dpd = 0;
        $isOverdue = $nextPayment->payment_date < $today->toDateString();

        if ($isOverdue) {
            $rawDiff = $today->diffInDays(Carbon::parse($nextPayment->payment_date), false); // Check raw
            $dpd = abs($today->diffInDays(Carbon::parse($nextPayment->payment_date)));

            echo "Payment Date: " . $nextPayment->payment_date . "\n";
            echo "Today: " . $today->toDateString() . "\n";
            echo "Raw Diff (false): " . $rawDiff . "\n";
            echo "Calculated DPD (abs): " . $dpd . "\n";
        } else {
            echo "Not Overdue. Next Payment: " . $nextPayment->payment_date . "\n";
        }
    } else {
        echo "No unpaid payments found.\n";
    }
    echo "--------------------------------\n";
}
