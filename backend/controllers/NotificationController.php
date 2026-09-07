<?php
// backend/controllers/NotificationController.php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/NotificationService.php';

class NotificationController {

    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        $unreadOnly = !empty($_GET['unread_only']);

        $notifications = NotificationService::getUserNotifications($user['id'], $unreadOnly);

        echo json_encode([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public static function markAsRead(int $id): void {
        $user = AuthMiddleware::authenticate();

        NotificationService::markAsRead($id, $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public static function markAllAsRead(): void {
        $user = AuthMiddleware::authenticate();

        NotificationService::markAllAsRead($user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    public static function delete(int $id): void {
        $user = AuthMiddleware::authenticate();

        NotificationService::deleteNotification($id, $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    public static function deleteAllRead(): void {
        $user = AuthMiddleware::authenticate();

        NotificationService::deleteAllReadNotifications($user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Read notifications deleted'
        ]);
    }
}
