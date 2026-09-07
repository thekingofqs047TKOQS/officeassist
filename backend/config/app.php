<?php
// backend/config/app.php

return [
    'name' => 'OfficeAssist API',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => getenv('APP_DEBUG') !== 'false',
    'url' => getenv('APP_URL') ?: 'http://127.0.0.1:8000',
    'jwt_secret' => getenv('JWT_SECRET') ?: 'OfficeAssist_Secure_JWT_Secret_Key_2026_x99a77b88c',
    'jwt_ttl' => 28800, // 8 hours in seconds
    'storage_path' => dirname(__DIR__) . '/storage/uploads',
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:8000'
    ]
];
