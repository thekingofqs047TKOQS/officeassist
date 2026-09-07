<?php
// scratch/setup_db.php

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '20ally041218@TKOQS';

try {
    echo "Connecting to MySQL server on {$host}:{$port}...\n";
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Creating database 'officeassist' if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS officeassist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE officeassist");

    echo "Importing database/schema.sql...\n";
    $schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schemaSql);
    echo "Schema imported successfully!\n";

    echo "Importing database/seeds.sql...\n";
    $seedsSql = file_get_contents(__DIR__ . '/../database/seeds.sql');
    $pdo->exec($seedsSql);
    echo "Seeds imported successfully!\n";

    echo "SUCCESS: Database 'officeassist' has been created and populated.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
