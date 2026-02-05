<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Loan Collection API ===\n\n";

$request = new \Illuminate\Http\Request([
    'from_date' => '2026-02-01',
    'to_date' => '2026-04-30',
]);

$controller = new \App\Http\Controllers\LoanCollectionReportController();
$response = $controller->index($request);

$data = json_decode($response->content(), true);

if ($data['success'] ?? false) {
    echo "Total records: " . count($data['data']) . "\n\n";

    // Group by currency
    $byCurrency = [];
    foreach ($data['data'] as $item) {
        $curr = $item['currency'] ?? 'Unknown';
        if (!isset($byCurrency[$curr])) {
            $byCurrency[$curr] = 0;
        }
        $byCurrency[$curr]++;
    }

    echo "Records by currency:\n";
    foreach ($byCurrency as $curr => $count) {
        echo "  - '$curr': $count records\n";
    }

    echo "\nFirst 3 records:\n";
    foreach (array_slice($data['data'], 0, 3) as $item) {
        echo "  - Currency: '{$item['currency']}' | Loan: {$item['loan_code']} | Principal: {$item['principal']}\n";
    }
} else {
    echo "Error: " . ($data['error'] ?? 'Unknown error');
}

echo "\n=== END ===\n";
