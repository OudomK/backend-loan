<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Simple Currency Count Test ===\n\n";

$request = new \Illuminate\Http\Request([
    'from_date' => '2025-01-01',
    'to_date' => '2026-04-02',
]);

$controller = new \App\Http\Controllers\LoanCollectionReportController();
$response = $controller->index($request);
$data = json_decode($response->content(), true);

$currencies = [];
foreach ($data['data'] as $item) {
    $curr = $item['currency'];
    $currencies[$curr] = ($currencies[$curr] ?? 0) + 1;
}

echo "API Response Summary:\n";
echo "Total records: " . count($data['data']) . "\n\n";
echo "By Currency:\n";
foreach ($currencies as $curr => $count) {
    echo "  - '$curr': $count records\n";
}

echo "\n=== RESULT ===\n";
if (isset($currencies['USD ($)']) && $currencies['USD ($)'] > 0) {
    echo "✅ USD DATA EXISTS IN API RESPONSE!\n";
    echo "   USD records: " . $currencies['USD ($)'] . "\n";
} else {
    echo "❌ NO USD DATA IN API RESPONSE!\n";
}
