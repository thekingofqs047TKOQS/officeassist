<?php
// backend/services/JWTService.php

class JWTService {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generateToken(array $user): string {
        $config = require __DIR__ . '/../config/app.php';
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $now = time();
        $payload = json_encode([
            'iss' => $config['name'],
            'sub' => $user['id'],
            'employee_id' => $user['employee_id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'],
            'iat' => $now,
            'exp' => $now + $config['jwt_ttl'],
            'jti' => bin2hex(random_bytes(16))
        ]);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $config['jwt_secret'], true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verifyToken(string $token): ?array {
        $config = require __DIR__ . '/../config/app.php';
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $config['jwt_secret'], true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function setAuthCookie(string $token): void {
        $config = require __DIR__ . '/../config/app.php';
        setcookie('officeassist_token', $token, [
            'expires' => time() + $config['jwt_ttl'],
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => false // set to true in production HTTPS
        ]);
    }

    public static function clearAuthCookie(): void {
        setcookie('officeassist_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => false
        ]);
    }
}
