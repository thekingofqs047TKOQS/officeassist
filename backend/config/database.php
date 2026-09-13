<?php
// backend/config/database.php

// Auto-load .env file if present in project root or backend folder
$envPaths = [
    __DIR__ . '/../../.env',
    __DIR__ . '/../.env'
];

foreach ($envPaths as $envPath) {
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        break;
    }
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Aiven (and most managed MySQL providers) require an SSL connection.
// DB_SSL_CA points to the CA certificate file bundled with the app.
// If it's not set or the file doesn't exist, SSL options are simply skipped
// (e.g. when connecting to a local/dev database that doesn't need SSL).
$sslCaPath = getenv('DB_SSL_CA') ?: (__DIR__ . '/aiven-ca.pem');

if ($sslCaPath && file_exists($sslCaPath)) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
    // Verify the server certificate against the CA. Set DB_SSL_VERIFY=false
    // only if you hit certificate verification issues you can't resolve.
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = getenv('DB_SSL_VERIFY') !== 'false';
}

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_NAME') ?: 'officeassist',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'charset' => 'utf8mb4',
    'options' => $options,
];