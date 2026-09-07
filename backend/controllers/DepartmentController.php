<?php
// backend/controllers/DepartmentController.php

require_once __DIR__ . '/../services/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../services/RequestService.php';
require_once __DIR__ . '/../services/AuditService.php';

class DepartmentController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $db = Database::getInstance();
        
        $sql = "SELECT id, name, code, description, status, created_at FROM departments WHERE 1=1";
        if (!isset($_GET['include_inactive'])) {
            $sql .= " AND status = 'ACTIVE'";
        }
        $sql .= " ORDER BY name ASC";

        $stmt = $db->query($sql);
        $departments = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $departments
        ]);
    }

    public static function getCategories(int $departmentId): void {
        AuthMiddleware::authenticate();
        $db = Database::getInstance();
        
        $sql = "
            SELECT id, department_id, name, description, guidance_notes, status
            FROM request_categories
            WHERE department_id = :dept_id
        ";
        if (!isset($_GET['include_inactive'])) {
            $sql .= " AND status = 'ACTIVE'";
        }
        $sql .= " ORDER BY name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute(['dept_id' => $departmentId]);
        $categories = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
    }

    public static function getStaff(int $departmentId): void {
        $user = AuthMiddleware::authenticate();
        try {
            $staff = RequestService::getDepartmentStaff($departmentId, $user);
            echo json_encode([
                'success' => true,
                'data' => $staff
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function create(): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);
        $name = trim($body['name'] ?? '');
        $code = strtoupper(trim($body['code'] ?? ''));
        $description = trim($body['description'] ?? '');

        if (empty($name) || empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department name and code are required.']);
            return;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO departments (name, code, description, status) VALUES (:name, :code, :description, 'ACTIVE')");
            $stmt->execute(['name' => $name, 'code' => $code, 'description' => $description]);
            $deptId = (int)$db->lastInsertId();

            AuditService::log($user['id'], 'DEPARTMENT_CREATED', 'departments', $deptId, null, ['name' => $name, 'code' => $code]);

            echo json_encode([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => ['id' => $deptId, 'name' => $name, 'code' => $code, 'description' => $description]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);

        try {
            $db = Database::getInstance();
            $deptStmt = $db->prepare("SELECT * FROM departments WHERE id = :id");
            $deptStmt->execute(['id' => $id]);
            $dept = $deptStmt->fetch();

            if (!$dept) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Department not found']);
                return;
            }

            $updates = [];
            $params = ['id' => $id];

            if (isset($body['name'])) { $updates[] = "name = :name"; $params['name'] = trim($body['name']); }
            if (isset($body['code'])) { $updates[] = "code = :code"; $params['code'] = strtoupper(trim($body['code'])); }
            if (isset($body['description'])) { $updates[] = "description = :description"; $params['description'] = trim($body['description']); }
            if (isset($body['status'])) { $updates[] = "status = :status"; $params['status'] = trim($body['status']); }

            if (!empty($updates)) {
                $sql = "UPDATE departments SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            AuditService::log($user['id'], 'DEPARTMENT_UPDATED', 'departments', $id, ['status' => $dept['status']], ['status' => $body['status'] ?? $dept['status']]);

            echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
