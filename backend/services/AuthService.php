<?php
// backend/services/AuthService.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JWTService.php';
require_once __DIR__ . '/AuditService.php';

class AuthService {

    public static function login(string $usernameOrEmail, string $password): array {
        $db = Database::getInstance();
        $identifier = trim($usernameOrEmail);

        $stmt = $db->prepare("
            SELECT u.*, d.name AS department_name, l.building, l.floor, l.room
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.email = :email OR u.employee_id = :employee_id
            LIMIT 1
        ");
        $stmt->execute([
            'email' => $identifier,
            'employee_id' => $identifier
        ]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("Invalid credentials");
        }

        if ($user['status'] !== 'ACTIVE') {
            throw new Exception("Account is currently " . strtolower($user['status']));
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception("Invalid credentials");
        }

        // Update last login timestamp
        $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $updateStmt->execute(['id' => $user['id']]);

        // Generate JWT token
        $token = JWTService::generateToken($user);

        // Audit log login
        AuditService::log($user['id'], 'USER_LOGIN', 'users', $user['id']);

        // Fetch department memberships (for staff/managers)
        $memberStmt = $db->prepare("
            SELECT dm.department_id, d.name AS department_name, dm.role_in_department
            FROM department_members dm
            JOIN departments d ON dm.department_id = d.id
            WHERE dm.user_id = :user_id
        ");
        $memberStmt->execute(['user_id' => $user['id']]);
        $memberships = $memberStmt->fetchAll();

        unset($user['password_hash']);
        $user['service_memberships'] = $memberships;

        return [
            'token' => $token,
            'user' => $user
        ];
    }

    public static function getUserById(int $userId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.employee_id, u.full_name, u.email, u.phone, u.role, u.status,
                   u.department_id, d.name AS department_name, d.code AS department_code,
                   u.location_id, l.building, l.floor, l.room, u.profile_photo, u.last_login_at, u.created_at
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user) {
            $memberStmt = $db->prepare("
                SELECT dm.department_id, d.name AS department_name, d.code AS department_code, dm.role_in_department
                FROM department_members dm
                JOIN departments d ON dm.department_id = d.id
                WHERE dm.user_id = :user_id
            ");
            $memberStmt->execute(['user_id' => $userId]);
            $user['service_memberships'] = $memberStmt->fetchAll();
        }

        return $user ?: null;
    }
}
