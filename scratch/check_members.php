<?php
require_once __DIR__ . '/../backend/services/Database.php';
$db = Database::getInstance();
$stmt = $db->query("SELECT dm.*, u.full_name, u.email FROM department_members dm JOIN users u ON dm.user_id = u.id");
print_r($stmt->fetchAll());
