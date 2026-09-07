<?php
// backend/services/ReportService.php

require_once __DIR__ . '/Database.php';

class ReportService {

    public static function getRequestVolumeReport(array $filters, array $user): array {
        $db = Database::getInstance();
        $params = [];

        $sql = "
            SELECT DATE(r.created_at) AS date_key, COUNT(*) AS request_count,
                   SUM(IF(r.status IN ('RESOLVED', 'CLOSED'), 1, 0)) AS resolved_count,
                   SUM(IF(r.status = 'CANCELLED', 1, 0)) AS cancelled_count
            FROM requests r
            WHERE 1=1
        ";

        // Scoping
        if ($user['role'] !== 'SYSTEM_ADMIN') {
            $deptStmt = $db->prepare("SELECT department_id FROM department_members WHERE user_id = :user_id");
            $deptStmt->execute(['user_id' => $user['id']]);
            $staffDeptIds = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($staffDeptIds)) {
                $inClause = implode(',', array_map('intval', $staffDeptIds));
                $sql .= " AND r.department_id IN ($inClause)";
            } else {
                $sql .= " AND r.requester_id = :user_id";
                $params['user_id'] = $user['id'];
            }
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(r.created_at) >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(r.created_at) <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $sql .= " GROUP BY DATE(r.created_at) ORDER BY date_key ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getDepartmentPerformanceReport(array $filters, array $user): array {
        $db = Database::getInstance();
        $params = [];

        $sql = "
            SELECT d.id AS department_id, d.name AS department_name, d.code,
                   COUNT(r.id) AS total_received,
                   SUM(IF(r.status IN ('RESOLVED', 'CLOSED'), 1, 0)) AS total_resolved,
                   SUM(IF(r.status IN ('NEW', 'ASSIGNED', 'IN_PROGRESS'), 1, 0)) AS total_open,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, r.created_at, r.started_at)), 1) AS avg_response_minutes,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, r.created_at, r.resolved_at)), 1) AS avg_resolution_minutes
            FROM departments d
            LEFT JOIN requests r ON d.id = r.department_id
            WHERE d.status = 'ACTIVE'
        ";

        if ($user['role'] !== 'SYSTEM_ADMIN') {
            $deptStmt = $db->prepare("SELECT department_id FROM department_members WHERE user_id = :user_id");
            $deptStmt->execute(['user_id' => $user['id']]);
            $staffDeptIds = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($staffDeptIds)) {
                $inClause = implode(',', array_map('intval', $staffDeptIds));
                $sql .= " AND d.id IN ($inClause)";
            } else {
                throw new Exception("Unauthorized access to department reports", 403);
            }
        }

        $sql .= " GROUP BY d.id, d.name, d.code ORDER BY total_received DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getStaffPerformanceReport(array $filters, array $user): array {
        $db = Database::getInstance();
        $params = [];

        $sql = "
            SELECT u.id AS staff_id, u.employee_id, u.full_name AS staff_name, d.name AS department_name,
                   COUNT(r.id) AS assigned_total,
                   SUM(IF(r.status IN ('RESOLVED', 'CLOSED'), 1, 0)) AS resolved_total,
                   SUM(IF(r.status IN ('ASSIGNED', 'IN_PROGRESS'), 1, 0)) AS active_workload,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, r.created_at, r.resolved_at)), 1) AS avg_resolution_minutes
            FROM department_members dm
            JOIN users u ON dm.user_id = u.id
            JOIN departments d ON dm.department_id = d.id
            LEFT JOIN requests r ON r.assigned_staff_id = u.id AND r.department_id = dm.department_id
            WHERE u.status = 'ACTIVE'
        ";

        if ($user['role'] !== 'SYSTEM_ADMIN') {
            $deptStmt = $db->prepare("SELECT department_id FROM department_members WHERE user_id = :user_id");
            $deptStmt->execute(['user_id' => $user['id']]);
            $staffDeptIds = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($staffDeptIds)) {
                $inClause = implode(',', array_map('intval', $staffDeptIds));
                $sql .= " AND dm.department_id IN ($inClause)";
            } else {
                throw new Exception("Unauthorized access to staff workload reports", 403);
            }
        }

        $sql .= " GROUP BY u.id, u.employee_id, u.full_name, d.name ORDER BY resolved_total DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function streamCSVExport(array $data, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if (!empty($data)) {
            // Write CSV headers
            fputcsv($output, array_keys($data[0]));
            // Write CSV rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit(0);
    }
}
