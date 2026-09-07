<?php
// backend/controllers/DashboardController.php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/DashboardService.php';

class DashboardController {

    public static function admin(): void {
        $user = AuthMiddleware::authenticate();
        try {
            $stats = DashboardService::getAdminDashboardStats($user);
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function department(): void {
        $user = AuthMiddleware::authenticate();
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : ($user['service_memberships'][0]['department_id'] ?? 0);

        if (!$deptId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID is required']);
            return;
        }

        try {
            $stats = DashboardService::getDepartmentDashboardStats($deptId, $user);
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
