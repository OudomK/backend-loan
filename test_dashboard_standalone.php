<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new \App\Http\Controllers\DashboardController;
$request = new \Illuminate\Http\Request;
$response = $controller->getStats($request);
echo json_encode($response->getData(), JSON_PRETTY_PRINT);
