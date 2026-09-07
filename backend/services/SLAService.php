<?php
// backend/services/SLAService.php

require_once __DIR__ . '/Database.php';

class SLAService {

    public static function getSLAConfigurations(?int $departmentId = null): array {
        $db = Database::getInstance();
        $sql = "
            SELECT s.*, d.name AS department_name
            FROM sla_configurations s
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.status = 'ACTIVE'
        ";
        $params = [];
        if ($departmentId !== null) {
            $sql .= " AND (s.department_id = :dept_id OR s.department_id IS NULL)";
            $params['dept_id'] = $departmentId;
        }
        $sql .= " ORDER BY s.department_id DESC, FIELD(s.priority, 'URGENT', 'HIGH', 'NORMAL', 'LOW')";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function saveSLAConfiguration(array $data, array $adminUser): array {
        $db = Database::getInstance();
        $deptId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $priority = strtoupper(trim($data['priority'] ?? 'NORMAL'));
        $respMins = max(1, (int)($data['first_response_target_minutes'] ?? 60));
        $resolMins = max(1, (int)($data['resolution_target_minutes'] ?? 480));

        if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'URGENT'])) {
            throw new Exception("Invalid priority level");
        }

        $stmt = $db->prepare("
            INSERT INTO sla_configurations (department_id, priority, first_response_target_minutes, resolution_target_minutes, status)
            VALUES (:dept_id, :priority, :resp_mins, :resol_mins, 'ACTIVE')
            ON DUPLICATE KEY UPDATE
                first_response_target_minutes = VALUES(first_response_target_minutes),
                resolution_target_minutes = VALUES(resolution_target_minutes),
                status = 'ACTIVE',
                updated_at = NOW()
        ");

        $stmt->execute([
            'dept_id' => $deptId,
            'priority' => $priority,
            'resp_mins' => $respMins,
            'resol_mins' => $resolMins
        ]);

        AuditService::log($adminUser['id'], 'SLA_CONFIG_UPDATED', 'sla_configurations', (int)$db->lastInsertId(), null, [
            'department_id' => $deptId,
            'priority' => $priority,
            'first_response_target_minutes' => $respMins,
            'resolution_target_minutes' => $resolMins
        ]);

        return self::getSLAConfigurations($deptId);
    }

    public static function calculateRequestSLA(array $request): array {
        $db = Database::getInstance();

        // 1. Fetch matching SLA target (Department-specific first, then global default)
        $slaStmt = $db->prepare("
            SELECT first_response_target_minutes, resolution_target_minutes
            FROM sla_configurations
            WHERE status = 'ACTIVE'
              AND priority = :priority
              AND (department_id = :dept_id OR department_id IS NULL)
            ORDER BY department_id DESC
            LIMIT 1
        ");
        $slaStmt->execute([
            'priority' => $request['priority'],
            'dept_id' => $request['department_id']
        ]);
        $slaConfig = $slaStmt->fetch();

        // Fallbacks if unconfigured
        $defaultResp = ['URGENT' => 15, 'HIGH' => 60, 'NORMAL' => 240, 'LOW' => 480];
        $defaultResol = ['URGENT' => 240, 'HIGH' => 480, 'NORMAL' => 2880, 'LOW' => 4320];

        $respMins = $slaConfig ? (int)$slaConfig['first_response_target_minutes'] : ($defaultResp[$request['priority']] ?? 240);
        $resolMins = $slaConfig ? (int)$slaConfig['resolution_target_minutes'] : ($defaultResol[$request['priority']] ?? 2880);

        $createdAt = strtotime($request['created_at']);
        $now = time();

        $responseDeadline = $createdAt + ($respMins * 60);
        $resolutionDeadline = $createdAt + ($resolMins * 60);

        // First response status
        $firstResponseTime = $request['started_at'] ? strtotime($request['started_at']) : ($request['assigned_at'] ? strtotime($request['assigned_at']) : null);
        $responseSLAState = 'ON_TRACK';
        if ($firstResponseTime) {
            $responseSLAState = ($firstResponseTime <= $responseDeadline) ? 'COMPLETED' : 'BREACHED';
        } else {
            if ($now > $responseDeadline) {
                $responseSLAState = 'BREACHED';
            } else if ($now >= $responseDeadline - 1800) { // 30 mins remaining
                $responseSLAState = 'DUE_SOON';
            }
        }

        // Resolution status
        $resolutionTime = $request['resolved_at'] ? strtotime($request['resolved_at']) : ($request['closed_at'] ? strtotime($request['closed_at']) : null);
        $resolutionSLAState = 'ON_TRACK';
        if ($resolutionTime) {
            $resolutionSLAState = ($resolutionTime <= $resolutionDeadline) ? 'COMPLETED' : 'BREACHED';
        } else {
            if ($now > $resolutionDeadline) {
                $resolutionSLAState = 'BREACHED';
            } else if ($now >= $resolutionDeadline - 3600) { // 1 hr remaining
                $resolutionSLAState = 'DUE_SOON';
            }
        }

        // Overall state
        $overallState = 'ON_TRACK';
        if ($request['status'] === 'CLOSED' || $request['status'] === 'RESOLVED') {
            $overallState = ($resolutionSLAState === 'BREACHED' || $responseSLAState === 'BREACHED') ? 'BREACHED' : 'COMPLETED';
        } else if ($resolutionSLAState === 'BREACHED' || $responseSLAState === 'BREACHED') {
            $overallState = 'BREACHED';
        } else if ($resolutionSLAState === 'DUE_SOON' || $responseSLAState === 'DUE_SOON') {
            $overallState = 'DUE_SOON';
        }

        $timeRemainingSecs = max(0, $resolutionDeadline - $now);

        return [
            'response_target_minutes' => $respMins,
            'resolution_target_minutes' => $resolMins,
            'response_deadline' => date('Y-m-d H:i:s', $responseDeadline),
            'resolution_deadline' => date('Y-m-d H:i:s', $resolutionDeadline),
            'response_sla_state' => $responseSLAState,
            'resolution_sla_state' => $resolutionSLAState,
            'overall_sla_state' => $overallState,
            'time_remaining_minutes' => ceil($timeRemainingSecs / 60)
        ];
    }
}
