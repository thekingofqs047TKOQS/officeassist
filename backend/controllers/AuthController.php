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
}
