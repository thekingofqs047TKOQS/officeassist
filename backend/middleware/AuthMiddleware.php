<?php
// backend/middleware/AuthMiddleware.php

require_once __DIR__ . '/../services/JWTService.php';
require_once __DIR__ . '/../services/AuthService.php';

class AuthMiddleware {
    public static function authenticate(): ?array {
        $token = null;

        // 1. Check HTTP-Only Cookie
        if (isset($_COOKIE['officeassist_token']) && !empty($_COOKIE['officeassist_token'])) {
            $token = $_COOKIE['officeassist_token'];
        }

        // 2. Fallback to Authorization Header (Bearer token)
        if (!$token) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required. No token provided.',
                'code' => 'UNAUTHENTICATED'
            ]);
            exit(0);
        }

        $payload = JWTService::verifyToken($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or expired session. Please log in again.',
                'code' => 'TOKEN_EXPIRED'
            ]);
            exit(0);
        }

        $user = AuthService::getUserById((int)$payload['sub']);
        if (!$user || $user['status'] !== 'ACTIVE') {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'User account disabled or not found.',
                'code' => 'ACCOUNT_DISABLED'
            ]);
            exit(0);
        }

        // Prevent users with must_change_password flag from accessing other API resources until password is changed
        if (!empty($user['must_change_password'])) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $uri = rtrim($uri, '/');
            if (strpos($uri, '/api') === 0) {
                $uri = substr($uri, 4);
            }
            $allowedEndpoints = ['/auth/change-password', '/auth/logout', '/auth/me'];
            if (!in_array($uri, $allowedEndpoints)) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Password change required before accessing system resources.',
                    'code' => 'MUST_CHANGE_PASSWORD',
                    'must_change_password' => true
                ]);
                exit(0);
            }
        }

        return $user;
    }
}
