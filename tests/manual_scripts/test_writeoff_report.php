<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Write-Off Report API ===\n\n";

// Simulate request
$request = new \Illuminate\Http\Request([
    'from_date' => '2025-11-01',
    'to_date' => '2026-02-04',
    'currency' => 'all'
]);

$controller = new \App\Http\Controllers\WriteOffReportController();
$response = $controller->index($request);

$data = json_decode($response->content(), true);

echo "Response data type: " . gettype($data) . "\n";
echo "Data count: " . count($data) . "\n\n";

if (!empty($data)) {
    echo "Sample data (first item):\n";
    print_r($data[0]);
} else {
    echo "❌ No data returned!\n\n";
    echo "Debugging info:\n";
    echo "Response: " . $response->content() . "\n";
}

echo "\n=== END ===\n";
