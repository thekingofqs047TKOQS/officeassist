<?php
// backend/controllers/AuthController.php

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/JWTService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {

    public static function login(): void {
        $rawInput = file_get_contents('php://input');
        $body = json_decode($rawInput, true);

        if (!is_array($body)) {
            $body = $_POST;
        }

        $username = trim($body['username'] ?? $body['email'] ?? '');
        $password = trim($body['password'] ?? '');

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Username/Email and Password are required.',
                'raw_received' => $rawInput,
                'parsed_body' => $body
            ]);
            return;
        }

        try {
            $result = AuthService::login($username, $password);
            JWTService::setAuthCookie($result['token']);

            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $result['token'],
                    'user' => $result['user']
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public static function logout(): void {
        JWTService::clearAuthCookie();
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public static function me(): void {
        $user = AuthMiddleware::authenticate();
        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
    }

    public static function changePassword(): void {
        $user = AuthMiddleware::authenticate();
        $rawInput = file_get_contents('php://input');
        $body = json_decode($rawInput, true) ?? [];

        $currentPassword = trim($body['current_password'] ?? '');
        $newPassword = trim($body['new_password'] ?? '');
        $confirmPassword = trim($body['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password, new password, and confirmation password are required.']);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
            return;
        }

        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
            return;
        }

        if ($newPassword === $currentPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'New password must be different from current password.']);
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $userDb = $stmt->fetch();

        if (!$userDb || !password_verify($currentPassword, $userDb['password_hash'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $db->prepare("UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id");
        $upd->execute(['hash' => $newHash, 'id' => $user['id']]);

        AuditService::log($user['id'], 'USER_PASSWORD_CHANGED', 'users', $user['id']);

        $updatedUser = AuthService::getUserById($user['id']);
        $newToken = JWTService::generateToken($updatedUser);
        JWTService::setAuthCookie($newToken);

        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully',
            'data' => [
                'token' => $newToken,
                'user' => $updatedUser
            ]
        ]);
    }
}
