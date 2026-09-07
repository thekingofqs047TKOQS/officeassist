<?php
// backend/services/DashboardService.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SLAService.php';

class DashboardService {

    public static function getAdminDashboardStats(array $user): array {
        if ($user['role'] !== 'SYSTEM_ADMIN') {
            throw new Exception("Access denied", 403);
        }

        $db = Database::getInstance();

        // 1. Status Counts
        $statusCounts = $db->query("
            SELECT status, COUNT(*) AS count
            FROM requests
            GROUP BY status
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $totalRequests = (int)array_sum($statusCounts);
        $newCount = (int)($statusCounts['NEW'] ?? 0);
        $assignedCount = (int)($statusCounts['ASSIGNED'] ?? 0);
        $inProgressCount = (int)($statusCounts['IN_PROGRESS'] ?? 0);
        $resolvedCount = (int)($statusCounts['RESOLVED'] ?? 0);
        $closedCount = (int)($statusCounts['CLOSED'] ?? 0);
        $cancelledCount = (int)($statusCounts['CANCELLED'] ?? 0);

        // 2. Department Breakdown
        $deptBreakdown = $db->query("
            SELECT d.name AS department_name, d.code, COUNT(r.id) AS total_requests,
                   SUM(IF(r.status IN ('NEW', 'ASSIGNED', 'IN_PROGRESS'), 1, 0)) AS active_requests,
                   SUM(IF(r.status IN ('RESOLVED', 'CLOSED'), 1, 0)) AS resolved_requests
            FROM departments d
            LEFT JOIN requests r ON d.id = r.department_id
            WHERE d.status = 'ACTIVE'
            GROUP BY d.id, d.name, d.code
            ORDER BY total_requests DESC
        ")->fetchAll();

        // 3. Priority Breakdown
        $priorityBreakdown = $db->query("
            SELECT priority, COUNT(*) AS count
            FROM requests
            GROUP BY priority
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        // 4. Performance Metrics (Average Response & Resolution Times in Minutes)
        $avgTimes = $db->query("
            SELECT 
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)), 1) AS avg_response_mins,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)), 1) AS avg_resolution_mins
            FROM requests
            WHERE status IN ('RESOLVED', 'CLOSED')
        ")->fetch();

        // 5. SLA Compliance Calculation
        $allActiveRequests = $db->query("SELECT id, department_id, priority, created_at, started_at, assigned_at, resolved_at, closed_at, status FROM requests")->fetchAll();
        $breachedCount = 0;
        $dueSoonCount = 0;
        $completedCount = 0;

        foreach ($allActiveRequests as $req) {
            $sla = SLAService::calculateRequestSLA($req);
            if ($sla['overall_sla_state'] === 'BREACHED') $breachedCount++;
            if ($sla['overall_sla_state'] === 'DUE_SOON') $dueSoonCount++;
            if ($sla['overall_sla_state'] === 'COMPLETED') $completedCount++;
        }

        $compliancePct = ($totalRequests > 0) ? round((($totalRequests - $breachedCount) / $totalRequests) * 100, 1) : 100.0;

        return [
            'overview' => [
                'total_requests' => $totalRequests,
                'new' => $newCount,
                'assigned' => $assignedCount,
                'in_progress' => $inProgressCount,
                'resolved' => $resolvedCount,
                'closed' => $closedCount,
                'cancelled' => $cancelledCount,
                'sla_breached' => $breachedCount,
                'sla_due_soon' => $dueSoonCount,
                'sla_compliance_pct' => $compliancePct,
                'avg_response_mins' => (float)($avgTimes['avg_response_mins'] ?? 0),
                'avg_resolution_mins' => (float)($avgTimes['avg_resolution_mins'] ?? 0)
            ],
            'department_breakdown' => $deptBreakdown,
            'priority_breakdown' => $priorityBreakdown
        ];
    }

    public static function getDepartmentDashboardStats(int $departmentId, array $user): array {
        $db = Database::getInstance();

        // Verify RBAC access to department stats
        $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
        if (!$isStaffOrAdmin) {
            $stmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $stmt->execute(['user_id' => $user['id'], 'dept_id' => $departmentId]);
            $isStaffOrAdmin = (bool)$stmt->fetch();
        }
        if (!$isStaffOrAdmin) {
            throw new Exception("Unauthorized access to department metrics", 403);
        }

        // 1. Department Overview Metrics
        $deptStmt = $db->prepare("
            SELECT 
                COUNT(*) AS total_requests,
                SUM(IF(status = 'NEW', 1, 0)) AS unassigned_count,
                SUM(IF(assigned_staff_id = ? AND status IN ('ASSIGNED', 'IN_PROGRESS'), 1, 0)) AS assigned_to_me_count,
                SUM(IF(status = 'IN_PROGRESS', 1, 0)) AS in_progress_count,
                SUM(IF(status = 'RESOLVED' AND DATE(resolved_at) = CURDATE(), 1, 0)) AS resolved_today_count,
                SUM(IF(status = 'CLOSED' AND DATE(closed_at) = CURDATE(), 1, 0)) AS closed_today_count
            FROM requests
            WHERE department_id = ?
        ");
        $deptStmt->execute([(int)$user['id'], (int)$departmentId]);
        $metrics = $deptStmt->fetch();

        // 2. Staff Workload Breakdown
        $staffStmt = $db->prepare("
            SELECT u.id AS staff_id, u.full_name AS staff_name, dm.role_in_department,
                   SUM(IF(r.status = 'ASSIGNED', 1, 0)) AS assigned_count,
                   SUM(IF(r.status = 'IN_PROGRESS', 1, 0)) AS in_progress_count,
                   SUM(IF(r.status IN ('RESOLVED', 'CLOSED'), 1, 0)) AS resolved_count
            FROM department_members dm
            JOIN users u ON dm.user_id = u.id
            LEFT JOIN requests r ON r.assigned_staff_id = u.id AND r.department_id = ?
            WHERE dm.department_id = ? AND u.status = 'ACTIVE'
            GROUP BY u.id, u.full_name, dm.role_in_department
            ORDER BY u.full_name ASC
        ");
        $staffStmt->execute([(int)$departmentId, (int)$departmentId]);
        $staffWorkload = $staffStmt->fetchAll();

        // 3. Department SLA breach calculations
        $reqStmt = $db->prepare("SELECT id, department_id, priority, created_at, started_at, assigned_at, resolved_at, closed_at, status FROM requests WHERE department_id = ?");
        $reqStmt->execute([(int)$departmentId]);
        $deptReqs = $reqStmt->fetchAll();

        $breachedCount = 0;
        $dueSoonCount = 0;
        foreach ($deptReqs as $r) {
            $sla = SLAService::calculateRequestSLA($r);
            if ($sla['overall_sla_state'] === 'BREACHED') $breachedCount++;
            if ($sla['overall_sla_state'] === 'DUE_SOON') $dueSoonCount++;
        }

        return [
            'department_id' => $departmentId,
            'metrics' => array_merge($metrics ?: [], [
                'sla_breached' => $breachedCount,
                'sla_due_soon' => $dueSoonCount
            ]),
            'staff_workload' => $staffWorkload
        ];
    }
}
