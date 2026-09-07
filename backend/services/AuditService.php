<?php
// backend/services/AuditService.php

require_once __DIR__ . '/Database.php';

class AuditService {
    public static function log(?int $userId, string $action, string $entityType, ?int $entityId = null, ?array $oldValues = null, ?array $newValues = null): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)
            ");

            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'System', 0, 255)
            ]);
        } catch (Exception $e) {
            // Error logging failure should not break primary application transaction
            error_log("AuditLog Error: " . $e->getMessage());
        }
    }
}
