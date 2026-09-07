<?php
// backend/controllers/LocationController.php

require_once __DIR__ . '/../services/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../services/AuditService.php';

class LocationController {

    public static function index(): void {
        AuthMiddleware::authenticate();
        $db = Database::getInstance();
        
        $sql = "SELECT id, building, floor, room, description, status FROM locations WHERE 1=1";
        if (!isset($_GET['include_inactive'])) {
            $sql .= " AND status = 'ACTIVE'";
        }
        $sql .= " ORDER BY building ASC, floor ASC, room ASC";

        $stmt = $db->query($sql);
        $locations = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $locations
        ]);
    }

    public static function create(): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);
        $building = trim($body['building'] ?? '');
        $floor = trim($body['floor'] ?? '');
        $room = trim($body['room'] ?? '');
        $description = trim($body['description'] ?? '');

        if (empty($building) || empty($floor) || empty($room)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Building, Floor, and Room are required.']);
            return;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO locations (building, floor, room, description, status)
                VALUES (:building, :floor, :room, :description, 'ACTIVE')
            ");
            $stmt->execute([
                'building' => $building,
                'floor' => $floor,
                'room' => $room,
                'description' => $description
            ]);

            $locId = (int)$db->lastInsertId();
            AuditService::log($user['id'], 'LOCATION_CREATED', 'locations', $locId, null, ['building' => $building, 'room' => $room]);

            echo json_encode([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => ['id' => $locId, 'building' => $building, 'floor' => $floor, 'room' => $room, 'description' => $description]
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
            $locStmt = $db->prepare("SELECT * FROM locations WHERE id = :id");
            $locStmt->execute(['id' => $id]);
            $loc = $locStmt->fetch();

            if (!$loc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Location not found']);
                return;
            }

            $updates = [];
            $params = ['id' => $id];

            if (isset($body['building'])) { $updates[] = "building = :building"; $params['building'] = trim($body['building']); }
            if (isset($body['floor'])) { $updates[] = "floor = :floor"; $params['floor'] = trim($body['floor']); }
            if (isset($body['room'])) { $updates[] = "room = :room"; $params['room'] = trim($body['room']); }
            if (isset($body['description'])) { $updates[] = "description = :description"; $params['description'] = trim($body['description']); }
            if (isset($body['status'])) { $updates[] = "status = :status"; $params['status'] = trim($body['status']); }

            if (!empty($updates)) {
                $sql = "UPDATE locations SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            AuditService::log($user['id'], 'LOCATION_UPDATED', 'locations', $id, ['status' => $loc['status']], ['status' => $body['status'] ?? $loc['status']]);

            echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
