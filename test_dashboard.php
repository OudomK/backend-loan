<?php
$controller = new \App\Http\Controllers\DashboardController;
$request = new \Illuminate\Http\Request;
$response = $controller->getStats($request);
echo json_encode($response->getData(), JSON_PRETTY_PRINT);
