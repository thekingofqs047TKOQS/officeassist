<?php
// backend/services/NotificationService.php

require_once __DIR__ . '/Database.php';

class NotificationService {

    public static function createNotification(int $userId, ?int $requestId, string $title, string $message, string $type = 'REQUEST_UPDATE'): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, request_id, title, message, type)
            VALUES (:user_id, :request_id, :title, :message, :type)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'request_id' => $requestId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);
    }

    public static function notifyDepartmentStaff(int $departmentId, ?int $requestId, string $title, string $message): void {
        $db = Database::getInstance();
        // Fetch all staff and heads assigned to this department in department_members
        $stmt = $db->prepare("
            SELECT user_id FROM department_members
            WHERE department_id = :department_id
        ");
        $stmt->execute(['department_id' => $departmentId]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($members as $staffId) {
            self::createNotification((int)$staffId, $requestId, $title, $message, 'DEPARTMENT_REQUEST');
        }
    }

    public static function getUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 50): array {
        $db = Database::getInstance();
        $sql = "
            SELECT id, request_id, title, message, type, is_read, read_at, created_at
            FROM notifications
            WHERE user_id = :user_id AND deleted_at IS NULL
        ";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function markAsRead(int $notificationId, int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public static function markAllAsRead(int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = :user_id AND is_read = 0 AND deleted_at IS NULL
        ");
        return $stmt->execute(['user_id' => $userId]);
    }

    public static function deleteNotification(int $notificationId, int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE notifications
            SET deleted_at = NOW()
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public static function deleteAllReadNotifications(int $userId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE notifications
            SET deleted_at = NOW()
            WHERE user_id = :user_id AND is_read = 1 AND deleted_at IS NULL
        ");
        return $stmt->execute(['user_id' => $userId]);
    }
}
