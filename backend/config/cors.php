<?php
// backend/config/cors.php

function handleCors() {
    $appConfig = require __DIR__ . '/app.php';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $appConfig['allowed_origins'])) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: http://localhost:5173");
    }

    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit(0);
    }
}
