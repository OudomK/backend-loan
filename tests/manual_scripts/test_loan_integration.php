<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\LoanController;
use Illuminate\Http\Request;
use App\Models\Borrower;
use App\Models\Loan;

echo "=== Testing Loan Controller Balloon Integration ===\n\n";

// 1. Create a dummy borrower if needed (or find one)
$borrower = Borrower::first();
if (!$borrower) {
    echo "Creating dummy borrower...\n";
    $borrower = Borrower::create([
        'code' => 'TEST-001',
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'Male',
        'dob' => '1990-01-01',
        'status' => 'active',
        // Add other required fields if any, checking schema might be needed but assuming minimal for now
    ]);
}
echo "Using Borrower: {$borrower->id}\n";

// 2. Mock Request Data for Balloon Loan
$requestData = [
    'borrower_id' => $borrower->id,
    'amount' => 5000,
    'interest_rate' => 12,
    'duration_months' => 6,
    'start_date' => '2026-03-01',
    'repayment_method' => 'Balloon',
    'currency' => 'USD ($)',
    'status' => 'active',
    'loan_code' => 'BL-TEST-' . rand(100, 999),
];

echo "Creating Loan with 'Balloon' method...\n";

// We can't easily instantiate controller with dependency injection in raw script without container help usually,
// but we can resolve it from app.
$controller = $app->make(LoanController::class);

// Create Request object
$request = Request::create('/api/loans', 'POST', $requestData);

// Call store
try {
    $response = $controller->store($request);
    $content = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 201) {
        echo "✅ Loan Created Successfully! ID: " . $content['id'] . "\n";

        // Verify Payments
        $loanId = $content['id'];
        $payments = Loan::find($loanId)->payments;

        echo "Generated Payments: " . $payments->count() . "\n";
        echo "Schedule:\n";
        foreach ($payments as $p) {
            echo "  #{$p->payment_number} | {$p->payment_date} | P: {$p->principal_amount} | I: {$p->interest_amount} | T: {$p->total_paid}\n";
        }

        // Check if last payment is actually Balloon
        $last = $payments->last();
        if ($last->principal_amount >= 5000) {
            echo "🎈 BALLOON PAYMENT CONFIRMED! (Principal: {$last->principal_amount})\n";
        } else {
            echo "❌ WARNING: Last payment principal looks too small for Balloon!\n";
        }

    } else {
        echo "❌ Failed to create loan. Status: " . $response->getStatusCode() . "\n";
        print_r($content);
    }

} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "\n=== END ===\n";
