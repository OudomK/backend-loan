<?php
include 'vendor/autoload.php';
$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$loans = App\Models\Loan::where('status', '!=', 'pending')->get();
foreach ($loans as $loan) {
    echo "Code: [{$loan->loan_code}] | ID: {$loan->id}\n";
}
echo "Total: " . $loans->count() . "\n";
