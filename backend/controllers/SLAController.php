<?php
// backend/controllers/SLAController.php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../services/SLAService.php';

class SLAController {

    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;

        try {
            $slas = SLAService::getSLAConfigurations($deptId);
            echo json_encode(['success' => true, 'data' => $slas]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function save(): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);

        try {
            $slas = SLAService::saveSLAConfiguration($body, $user);
            echo json_encode(['success' => true, 'message' => 'SLA configuration updated successfully', 'data' => $slas]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
