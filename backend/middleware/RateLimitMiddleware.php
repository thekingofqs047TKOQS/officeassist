<?php
// backend/middleware/RateLimitMiddleware.php

class RateLimitMiddleware {
    private static int $windowSeconds = 60;

    public static function enforce(int $maxAllowed = 300, int $window = 60): void {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            return; // Skip rate limiting during dev & automated testing
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = md5("ratelimit_" . $ip);
        
        $tmpDir = sys_get_temp_dir() . '/officeassist_ratelimits';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $file = $tmpDir . '/' . $key . '.json';
        $now = time();

        $data = ['count' => 0, 'reset' => $now + $window];
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $parsed = json_decode($raw, true);
            if (is_array($parsed) && isset($parsed['reset']) && $parsed['reset'] > $now) {
                $data = $parsed;
            }
        }

        $data['count']++;

        if ($data['count'] > $maxAllowed) {
            http_response_code(429);
            header('Retry-After: ' . ($data['reset'] - $now));
            echo json_encode([
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait before retrying.',
                'code' => 'TOO_MANY_REQUESTS'
            ]);
            exit(0);
        }

        @file_put_contents($file, json_encode($data));
    }
}
