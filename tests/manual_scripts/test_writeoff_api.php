<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Write-Off Collection API ===\n\n";

// Simulate request
$request = new \Illuminate\Http\Request([
    'from_date' => '2025-01-01',
    'to_date' => '2026-02-04',
    'currency' => null
]);

$controller = new \App\Http\Controllers\WriteOffCollectionReportController();
$response = $controller->index($request);

$data = json_decode($response->content(), true);

echo "Response:\n";
echo "- Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
echo "- Data count: " . count($data['data'] ?? []) . "\n\n";

if (!empty($data['data'])) {
    echo "Sample data (first item):\n";
    print_r($data['data'][0]);
}

echo "\n=== END ===\n";
