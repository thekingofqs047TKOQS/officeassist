<?php
// backend/public/index.php

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../routes/api.php';

// Handle CORS headers
handleCors();

// Default JSON response header unless streaming binary
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/download') === false) {
    header('Content-Type: application/json; charset=utf-8');
}

// Global Exception & Error Handler
set_exception_handler(function (Throwable $e) {
    $code = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 500;
    http_response_code($code);
    
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'SERVER_ERROR'
    ];

    $appConfig = require __DIR__ . '/../config/app.php';
    if ($appConfig['debug']) {
        $response['trace'] = $e->getTraceAsString();
        $response['file'] = $e->getFile() . ':' . $e->getLine();
    }

    echo json_encode($response);
    exit(0);
});

// Route Request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

routeRequest($method, $uri);
