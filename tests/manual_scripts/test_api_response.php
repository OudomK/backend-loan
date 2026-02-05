<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing LoanCollectionReportController API Response ===\n\n";

// Simulate exact request from Frontend
$request = new \Illuminate\Http\Request([
    'from_date' => '2025-01-01',
    'to_date' => '2026-04-02',
]);

$controller = new \App\Http\Controllers\LoanCollectionReportController();
$response = $controller->index($request);

$data = json_decode($response->content(), true);

echo "Response Structure:\n";
echo "- Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "- Data type: " . gettype($data['data']) . "\n";
echo "- Data count: " . count($data['data']) . "\n\n";

// Group by currency to understand what's returned
$byCurrency = [];
foreach ($data['data'] as $item) {
    $curr = $item['currency'] ?? 'Unknown';
    if (!isset($byCurrency[$curr])) {
        $byCurrency[$curr] = [];
    }
    $byCurrency[$curr][] = $item;
}

echo "Breakdown by Currency:\n";
foreach ($byCurrency as $curr => $items) {
    echo "  Currency: '$curr'\n";
    echo "    - Count: " . count($items) . "\n";
    echo "    - Sample (first item):\n";
    $first = $items[0];
    echo "      * date: {$first['date']}\n";
    echo "      * loan_code: {$first['loan_code']}\n";
    echo "      * name: {$first['name']}\n";
    echo "      * principal: {$first['principal']}\n";
    echo "      * currency: {$first['currency']}\n";
    echo "\n";
}

echo "=== Raw JSON (first 500 chars) ===\n";
echo substr($response->content(), 0, 500) . "...\n";

echo "\n=== END ===\n";
