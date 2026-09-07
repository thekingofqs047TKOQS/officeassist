<?php
// backend/controllers/CategoryController.php

require_once __DIR__ . '/../services/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../services/AuditService.php';

class CategoryController {

    public static function create(): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN', 'DEPARTMENT_MANAGER']);

        $body = json_decode(file_get_contents('php://input'), true);
        $deptId = isset($body['department_id']) ? (int)$body['department_id'] : 0;
        $name = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');
        $guidanceNotes = trim($body['guidance_notes'] ?? '');

        if (!$deptId || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Department ID and Category name are required.']);
            return;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO request_categories (department_id, name, description, guidance_notes, status)
                VALUES (:dept_id, :name, :description, :guidance_notes, 'ACTIVE')
            ");
            $stmt->execute([
                'dept_id' => $deptId,
                'name' => $name,
                'description' => $description,
                'guidance_notes' => $guidanceNotes
            ]);

            $catId = (int)$db->lastInsertId();
            AuditService::log($user['id'], 'CATEGORY_CREATED', 'request_categories', $catId, null, ['name' => $name]);

            echo json_encode([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => ['id' => $catId, 'department_id' => $deptId, 'name' => $name, 'description' => $description, 'guidance_notes' => $guidanceNotes]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function update(int $id): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN', 'DEPARTMENT_MANAGER']);

        $body = json_decode(file_get_contents('php://input'), true);

        try {
            $db = Database::getInstance();
            $catStmt = $db->prepare("SELECT * FROM request_categories WHERE id = :id");
            $catStmt->execute(['id' => $id]);
            $cat = $catStmt->fetch();

            if (!$cat) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Category not found']);
                return;
            }

            $updates = [];
            $params = ['id' => $id];

            if (isset($body['name'])) { $updates[] = "name = :name"; $params['name'] = trim($body['name']); }
            if (isset($body['description'])) { $updates[] = "description = :description"; $params['description'] = trim($body['description']); }
            if (isset($body['guidance_notes'])) { $updates[] = "guidance_notes = :guidance_notes"; $params['guidance_notes'] = trim($body['guidance_notes']); }
            if (isset($body['status'])) { $updates[] = "status = :status"; $params['status'] = trim($body['status']); }

            if (!empty($updates)) {
                $sql = "UPDATE request_categories SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            AuditService::log($user['id'], 'CATEGORY_UPDATED', 'request_categories', $id, ['status' => $cat['status']], ['status' => $body['status'] ?? $cat['status']]);

            echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
