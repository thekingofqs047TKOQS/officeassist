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

        return $user;
    }
}
