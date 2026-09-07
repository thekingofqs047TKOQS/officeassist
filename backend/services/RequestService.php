<?php
// backend/services/RequestService.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/SLAService.php';

class RequestService {

    public static function generateRequestNumber(): string {
        $db = Database::getInstance();
        $datePrefix = 'REQ-' . date('Ymd') . '-';

        $stmt = $db->prepare("
            SELECT request_number FROM requests
            WHERE request_number LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['prefix' => $datePrefix . '%']);
        $lastReq = $stmt->fetchColumn();

        if ($lastReq) {
            $seq = (int)substr($lastReq, -4) + 1;
        } else {
            $seq = 1;
        }

        return $datePrefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    public static function createRequest(array $user, array $data): array {
        $db = Database::getInstance();

        // Validate destination department
        $deptStmt = $db->prepare("SELECT id, name FROM departments WHERE id = :id AND status = 'ACTIVE'");
        $deptStmt->execute(['id' => $data['department_id']]);
        $dept = $deptStmt->fetch();
        if (!$dept) {
            throw new Exception("Selected department does not exist or is inactive.");
        }

        // Validate category if specified
        if (!empty($data['category_id'])) {
            $catStmt = $db->prepare("SELECT id, name FROM request_categories WHERE id = :id AND department_id = :dept_id AND status = 'ACTIVE'");
            $catStmt->execute(['id' => $data['category_id'], 'dept_id' => $data['department_id']]);
            $cat = $catStmt->fetch();
            if (!$cat) {
                throw new Exception("Selected category is invalid for this department.");
            }
        }

        // Location snapshot
        $locationSnapshot = null;
        $locationId = !empty($data['location_id']) ? (int)$data['location_id'] : ($user['location_id'] ?? null);
        if ($locationId) {
            $locStmt = $db->prepare("SELECT building, floor, room, description FROM locations WHERE id = :id");
            $locStmt->execute(['id' => $locationId]);
            $locData = $locStmt->fetch();
            if ($locData) {
                $locationSnapshot = json_encode($locData);
            }
        }

        $requestNumber = self::generateRequestNumber();
        $rawPriority = !empty($data['priority']) ? $data['priority'] : 'NORMAL';
        $priority = in_array($rawPriority, ['LOW', 'NORMAL', 'HIGH', 'URGENT']) ? $rawPriority : 'NORMAL';

        $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
        $issueTypeText = !empty($data['issue_type_text']) ? trim($data['issue_type_text']) : null;

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO requests (
                    request_number, requester_id, department_id, category_id, issue_type_text,
                    title, description, location_id, location_snapshot, assistance_required, priority, status
                ) VALUES (
                    :request_number, :requester_id, :department_id, :category_id, :issue_type_text,
                    :title, :description, :location_id, :location_snapshot, :assistance_required, :priority, 'NEW'
                )
            ");

            $stmt->execute([
                'request_number' => $requestNumber,
                'requester_id' => $user['id'],
                'department_id' => $data['department_id'],
                'category_id' => $categoryId,
                'issue_type_text' => $issueTypeText,
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'location_id' => $locationId,
                'location_snapshot' => $locationSnapshot,
                'assistance_required' => isset($data['assistance_required']) ? trim($data['assistance_required']) : null,
                'priority' => $priority
            ]);

            $requestId = (int)$db->lastInsertId();

            // Insert initial status history
            $histStmt = $db->prepare("
                INSERT INTO request_status_history (request_id, changed_by, old_status, new_status, comment)
                VALUES (:request_id, :changed_by, NULL, 'NEW', 'Request submitted by requester')
            ");
            $histStmt->execute([
                'request_id' => $requestId,
                'changed_by' => $user['id']
            ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Notify department staff & managers
        NotificationService::notifyDepartmentStaff(
            (int)$data['department_id'],
            $requestId,
            "New Request: " . $requestNumber,
            "New request '" . trim($data['title']) . "' from " . $user['full_name'] . " submitted to " . $dept['name']
        );

        // Notify requester confirmation
        NotificationService::createNotification(
            $user['id'],
            $requestId,
            "Request Submitted: " . $requestNumber,
            "Your request '" . trim($data['title']) . "' has been submitted to " . $dept['name']
        );

        AuditService::log($user['id'], 'REQUEST_CREATED', 'requests', $requestId, null, ['request_number' => $requestNumber]);

        return self::getRequestById($requestId, $user);
    }

    public static function getRequests(array $user, array $filters = []): array {
        $db = Database::getInstance();
        $params = [];

        $sql = "
            SELECT r.id, r.request_number, r.title, r.priority, r.status, r.created_at, r.updated_at,
                   r.assigned_at, r.started_at, r.resolved_at, r.closed_at,
                   r.requester_id, u_req.full_name AS requester_name, u_req.email AS requester_email, u_req.employee_id AS requester_employee_id,
                   r.department_id, d.name AS department_name, d.code AS department_code,
                   r.category_id, c.name AS category_name,
                   r.assigned_staff_id, u_staff.full_name AS assigned_staff_name,
                   (SELECT COUNT(*) FROM request_attachments WHERE request_id = r.id) AS attachment_count,
                   l.building, l.floor, l.room
            FROM requests r
            JOIN users u_req ON r.requester_id = u_req.id
            JOIN departments d ON r.department_id = d.id
            LEFT JOIN request_categories c ON r.category_id = c.id
            LEFT JOIN users u_staff ON r.assigned_staff_id = u_staff.id
            LEFT JOIN locations l ON r.location_id = l.id
            WHERE 1=1
        ";

        // EXPLICIT RBAC QUERY SCOPING:
        if ($user['role'] === 'SYSTEM_ADMIN') {
            // System Admin sees all requests
        } else {
            $deptStmt = $db->prepare("SELECT department_id FROM department_members WHERE user_id = :user_id");
            $deptStmt->execute(['user_id' => $user['id']]);
            $staffDeptIds = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($user['department_id'])) {
                $staffDeptIds[] = (int)$user['department_id'];
            }
            $staffDeptIds = array_unique(array_filter(array_map('intval', $staffDeptIds)));

            if (!empty($staffDeptIds)) {
                $inClause = implode(',', $staffDeptIds);
                $sql .= " AND (r.department_id IN ($inClause) OR r.requester_id = :user_id)";
                $params['user_id'] = $user['id'];
            } else {
                $sql .= " AND r.requester_id = :user_id";
                $params['user_id'] = $user['id'];
            }
        }

        // Apply filters
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'UNASSIGNED') {
                $sql .= " AND r.status = 'NEW'";
            } else if ($filters['status'] === 'ASSIGNED_TO_ME') {
                $sql .= " AND r.assigned_staff_id = :assigned_me";
                $params['assigned_me'] = $user['id'];
            } else {
                $sql .= " AND r.status = :status";
                $params['status'] = $filters['status'];
            }
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND r.department_id = :dept_id";
            $params['dept_id'] = (int)$filters['department_id'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND r.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.request_number LIKE :search1 OR r.title LIKE :search2 OR u_req.full_name LIKE :search3)";
            $searchVal = '%' . trim($filters['search']) . '%';
            $params['search1'] = $searchVal;
            $params['search2'] = $searchVal;
            $params['search3'] = $searchVal;
        }

        // Count total for pagination
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS count_tbl";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();

        // Pagination
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? min(100, max(1, (int)$filters['limit'])) : 50;
        $offset = ($page - 1) * $limit;

        $sql .= " ORDER BY r.created_at DESC LIMIT $limit OFFSET $offset";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        foreach ($records as &$r) {
            $r['sla'] = SLAService::calculateRequestSLA($r);
        }

        return [
            'items' => $records,
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalRecords / $limit)
            ]
        ];
    }

    public static function getRequestById(int $requestId, array $user): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT r.*,
                   u_req.full_name AS requester_name, u_req.email AS requester_email, u_req.phone AS requester_phone, u_req.employee_id AS requester_employee_id,
                   d.name AS department_name, d.code AS department_code,
                   c.name AS category_name,
                   u_staff.full_name AS assigned_staff_name, u_staff.email AS assigned_staff_email,
                   l.building, l.floor, l.room
            FROM requests r
            JOIN users u_req ON r.requester_id = u_req.id
            JOIN departments d ON r.department_id = d.id
            LEFT JOIN request_categories c ON r.category_id = c.id
            LEFT JOIN users u_staff ON r.assigned_staff_id = u_staff.id
            LEFT JOIN locations l ON r.location_id = l.id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception("Request not found", 404);
        }

        // RBAC Access Verification
        if ($user['role'] !== 'SYSTEM_ADMIN' && $request['requester_id'] !== $user['id']) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            if (!$deptStmt->fetch()) {
                throw new Exception("Unauthorized access to this department request", 403);
            }
        }

        // Fetch comments
        $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
        if (!$isStaffOrAdmin) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            $isStaffOrAdmin = (bool)$deptStmt->fetch();
        }

        $commentSql = "
            SELECT rc.id, rc.comment, rc.is_internal, rc.created_at,
                   u.id AS user_id, u.full_name AS user_name, u.role AS user_role
            FROM request_comments rc
            JOIN users u ON rc.user_id = u.id
            WHERE rc.request_id = :request_id
        ";
        if (!$isStaffOrAdmin) {
            $commentSql .= " AND rc.is_internal = 0";
        }
        $commentSql .= " ORDER BY rc.created_at ASC";

        $commentStmt = $db->prepare($commentSql);
        $commentStmt->execute(['request_id' => $requestId]);
        $request['comments'] = $commentStmt->fetchAll();

        // Fetch attachments
        $attStmt = $db->prepare("
            SELECT ra.id, ra.original_name, ra.mime_type, ra.file_size, ra.created_at,
                   u.full_name AS uploader_name
            FROM request_attachments ra
            JOIN users u ON ra.uploaded_by = u.id
            WHERE ra.request_id = :request_id
            ORDER BY ra.created_at ASC
        ");
        $attStmt->execute(['request_id' => $requestId]);
        $request['attachments'] = $attStmt->fetchAll();

        // Fetch status history
        $histStmt = $db->prepare("
            SELECT rsh.id, rsh.old_status, rsh.new_status, rsh.comment, rsh.created_at,
                   u.full_name AS changed_by_name
            FROM request_status_history rsh
            JOIN users u ON rsh.changed_by = u.id
            WHERE rsh.request_id = :request_id
            ORDER BY rsh.created_at ASC
        ");
        $histStmt->execute(['request_id' => $requestId]);
        $request['status_history'] = $histStmt->fetchAll();

        $request['sla'] = SLAService::calculateRequestSLA($request);

        return $request;
    }

    public static function claimRequest(int $requestId, array $user): array {
        $db = Database::getInstance();
        $request = self::getRequestById($requestId, $user);

        // Verify user is authorized staff/manager for destination department or admin
        $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
        if (!$isStaffOrAdmin) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            $isStaffOrAdmin = (bool)$deptStmt->fetch();
        }
        if (!$isStaffOrAdmin) {
            throw new Exception("You are not authorized to claim requests for this department.", 403);
        }

        // ATOMIC TRANSACTION: Claim request only if unassigned/NEW
        $db->beginTransaction();

        try {
            $updateStmt = $db->prepare("
                UPDATE requests
                SET status = 'ASSIGNED', assigned_staff_id = :staff_id, assigned_at = NOW(), updated_at = NOW()
                WHERE id = :id AND (assigned_staff_id IS NULL OR status = 'NEW')
            ");
            $updateStmt->execute([
                'staff_id' => $user['id'],
                'id' => $requestId
            ]);

            if ($updateStmt->rowCount() === 0) {
                $db->rollBack();
                throw new Exception("This request has already been assigned to or claimed by another staff member.", 409);
            }

            // Record status history
            $histStmt = $db->prepare("
                INSERT INTO request_status_history (request_id, changed_by, old_status, new_status, comment)
                VALUES (:request_id, :changed_by, :old_status, 'ASSIGNED', :comment)
            ");
            $histStmt->execute([
                'request_id' => $requestId,
                'changed_by' => $user['id'],
                'old_status' => $request['status'],
                'comment' => "Request claimed by " . $user['full_name']
            ]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Notify requester
        NotificationService::createNotification(
            (int)$request['requester_id'],
            $requestId,
            "Request Assigned: " . $request['request_number'],
            "Your request has been claimed by " . $user['full_name'] . " (" . $request['department_name'] . ")."
        );

        AuditService::log($user['id'], 'REQUEST_CLAIMED', 'requests', $requestId, ['assigned_staff_id' => null], ['assigned_staff_id' => $user['id']]);

        return self::getRequestById($requestId, $user);
    }

    public static function assignStaff(int $requestId, int $staffUserId, array $user, ?string $comment = null): array {
        $db = Database::getInstance();
        $request = self::getRequestById($requestId, $user);

        // Verify user is department head/manager for destination department or System Admin
        $isManagerOrAdmin = ($user['role'] === 'SYSTEM_ADMIN' || $user['role'] === 'DEPARTMENT_HEAD');
        if (!$isManagerOrAdmin) {
            $mgrStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id AND role_in_department IN ('MANAGER', 'HEAD')");
            $mgrStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            $isManagerOrAdmin = (bool)$mgrStmt->fetch();
        }
        if (!$isManagerOrAdmin) {
            throw new Exception("Only department heads or system administrators can assign staff.", 403);
        }

        // Validate that target staffUserId actually belongs to department_members for THIS department
        $targetStmt = $db->prepare("
            SELECT u.id, u.full_name FROM department_members dm
            JOIN users u ON dm.user_id = u.id
            WHERE dm.user_id = :user_id AND dm.department_id = :dept_id
        ");
        $targetStmt->execute(['user_id' => $staffUserId, 'dept_id' => $request['department_id']]);
        $targetStaff = $targetStmt->fetch();

        if (!$targetStaff && $user['role'] !== 'SYSTEM_ADMIN') {
            throw new Exception("Selected staff member does not belong to the destination department.", 400);
        }

        $oldStatus = $request['status'];
        $newStatus = ($oldStatus === 'NEW') ? 'ASSIGNED' : $oldStatus;

        $db->beginTransaction();

        try {
            $updateStmt = $db->prepare("
                UPDATE requests
                SET status = :status, assigned_staff_id = :staff_id, assigned_at = IF(assigned_at IS NULL, NOW(), assigned_at), updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                'status' => $newStatus,
                'staff_id' => $staffUserId,
                'id' => $requestId
            ]);

            // Record status history
            $histStmt = $db->prepare("
                INSERT INTO request_status_history (request_id, changed_by, old_status, new_status, comment)
                VALUES (:request_id, :changed_by, :old_status, :new_status, :comment)
            ");
            $histStmt->execute([
                'request_id' => $requestId,
                'changed_by' => $user['id'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'comment' => $comment ?: ("Assigned to " . ($targetStaff['full_name'] ?? "Staff #$staffUserId"))
            ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Notify assigned staff
        NotificationService::createNotification(
            $staffUserId,
            $requestId,
            "Request Assigned to You: " . $request['request_number'],
            "Manager " . $user['full_name'] . " assigned request '" . $request['title'] . "' to you."
        );

        // Notify requester
        NotificationService::createNotification(
            (int)$request['requester_id'],
            $requestId,
            "Request Assigned: " . $request['request_number'],
            "Your request has been assigned to " . ($targetStaff['full_name'] ?? 'department staff') . "."
        );

        AuditService::log($user['id'], 'REQUEST_ASSIGNED', 'requests', $requestId, ['assigned_staff_id' => $request['assigned_staff_id']], ['assigned_staff_id' => $staffUserId]);

        return self::getRequestById($requestId, $user);
    }

    public static function getDepartmentStaff(int $departmentId, array $user): array {
        $db = Database::getInstance();

        // Verify authorized staff/manager or system admin
        $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
        if (!$isStaffOrAdmin) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $departmentId]);
            $isStaffOrAdmin = (bool)$deptStmt->fetch();
        }
        if (!$isStaffOrAdmin) {
            throw new Exception("Unauthorized access to department staff directory.", 403);
        }

        $stmt = $db->prepare("
            SELECT u.id, u.employee_id, u.full_name, u.email, dm.role_in_department
            FROM department_members dm
            JOIN users u ON dm.user_id = u.id
            WHERE dm.department_id = :dept_id AND u.status = 'ACTIVE'
            ORDER BY u.full_name ASC
        ");
        $stmt->execute(['dept_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $requestId, string $newStatus, array $user, ?string $comment = null, ?int $assignedStaffId = null): array {
        $request = self::getRequestById($requestId, $user);
        $oldStatus = $request['status'];

        $allowedStatuses = ['NEW', 'ASSIGNED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'CANCELLED'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception("Invalid status '$newStatus'");
        }

        $db = Database::getInstance();
        
        $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
        if (!$isStaffOrAdmin) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            $isStaffOrAdmin = (bool)$deptStmt->fetch();
        }
        $isRequester = ($user['id'] === (int)$request['requester_id']);

        if ($assignedStaffId !== null && !$isStaffOrAdmin) {
            throw new Exception("Only department staff or administrators can assign requests.", 403);
        }

        if (in_array($newStatus, ['ASSIGNED', 'IN_PROGRESS', 'RESOLVED']) && !$isStaffOrAdmin && !($oldStatus === 'RESOLVED' && $newStatus === 'IN_PROGRESS' && $isRequester)) {
            throw new Exception("Only department staff or administrators can perform status transition to '$newStatus'.", 403);
        }

        if ($newStatus === 'CANCELLED' && !$isRequester && !$isStaffOrAdmin) {
            throw new Exception("You are not authorized to cancel this request.", 403);
        }

        if ($oldStatus === 'RESOLVED' && $newStatus === 'IN_PROGRESS' && $isRequester && empty(trim($comment ?? ''))) {
            throw new Exception("Please provide a comment explaining why the issue is not solved.", 400);
        }

        // Validate state machine rules
        $isValidTransition = false;
        if ($oldStatus === $newStatus && $assignedStaffId !== null) {
            $isValidTransition = true;
        } else {
            switch ($oldStatus) {
                case 'NEW':
                    $isValidTransition = in_array($newStatus, ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED']);
                    break;
                case 'ASSIGNED':
                    $isValidTransition = in_array($newStatus, ['ASSIGNED', 'IN_PROGRESS', 'CANCELLED']);
                    break;
                case 'IN_PROGRESS':
                    $isValidTransition = in_array($newStatus, ['RESOLVED', 'CANCELLED']);
                    break;
                case 'RESOLVED':
                    $isValidTransition = in_array($newStatus, ['CLOSED', 'IN_PROGRESS']);
                    break;
                case 'CLOSED':
                case 'CANCELLED':
                    $isValidTransition = false;
                    break;
            }
        }

        if (!$isValidTransition) {
            throw new Exception("Cannot transition request from '$oldStatus' to '$newStatus'", 400);
        }

        $db->beginTransaction();

        try {
            $updates = ["status = :status", "updated_at = NOW()"];
            $params = ['status' => $newStatus, 'id' => $requestId];

            if ($newStatus === 'ASSIGNED' || $assignedStaffId !== null) {
                $targetStaffId = $assignedStaffId ?: $user['id'];
                $updates[] = "assigned_staff_id = :assigned_staff_id";
                $updates[] = "assigned_at = IF(assigned_at IS NULL, NOW(), assigned_at)";
                $params['assigned_staff_id'] = $targetStaffId;
            }

            if ($newStatus === 'IN_PROGRESS') {
                $updates[] = "started_at = IF(started_at IS NULL, NOW(), started_at)";
                if (!$request['assigned_staff_id']) {
                    $updates[] = "assigned_staff_id = :assigned_staff_id";
                    $params['assigned_staff_id'] = $user['id'];
                }
            }

            if ($newStatus === 'RESOLVED') {
                $updates[] = "resolved_at = NOW()";
            }

            if ($newStatus === 'CLOSED') {
                $updates[] = "closed_at = NOW()";
            }

            $sql = "UPDATE requests SET " . implode(", ", $updates) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Record status history
            $histStmt = $db->prepare("
                INSERT INTO request_status_history (request_id, changed_by, old_status, new_status, comment)
                VALUES (:request_id, :changed_by, :old_status, :new_status, :comment)
            ");
            $histStmt->execute([
                'request_id' => $requestId,
                'changed_by' => $user['id'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'comment' => $comment ?: "Status updated to $newStatus"
            ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Notify requester/department
        if ($user['id'] !== $request['requester_id']) {
            $msg = ($newStatus === 'IN_PROGRESS') 
                ? "Your request " . $request['request_number'] . " is now being worked on by " . $request['department_name'] . "."
                : (($newStatus === 'RESOLVED')
                ? "Your " . $request['department_name'] . " request " . $request['request_number'] . " has been marked as resolved. Please confirm if the issue is fixed."
                : "Status changed from $oldStatus to $newStatus");

            NotificationService::createNotification(
                (int)$request['requester_id'],
                $requestId,
                "Request " . $request['request_number'] . " " . ucfirst(strtolower($newStatus)),
                $msg
            );
        } else if ($oldStatus === 'RESOLVED' && $newStatus === 'IN_PROGRESS') {
            // Requester reopened issue as NOT SOLVED -> Notify Department Staff
            NotificationService::notifyDepartmentStaff(
                (int)$request['department_id'],
                $requestId,
                "Issue Reopened: " . $request['request_number'],
                $user['full_name'] . " reported problem not solved: " . $comment
            );
        }

        AuditService::log($user['id'], 'REQUEST_STATUS_UPDATED', 'requests', $requestId, ['status' => $oldStatus], ['status' => $newStatus]);

        return self::getRequestById($requestId, $user);
    }

    public static function addComment(int $requestId, array $user, string $commentText, bool $isInternal = false): array {
        $request = self::getRequestById($requestId, $user);

        if ($isInternal) {
            $isStaffOrAdmin = ($user['role'] === 'SYSTEM_ADMIN');
            if (!$isStaffOrAdmin) {
                $db = Database::getInstance();
                $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
                $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
                $isStaffOrAdmin = (bool)$deptStmt->fetch();
            }
            if (!$isStaffOrAdmin) {
                throw new Exception("Only department staff can add internal notes.", 403);
            }
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO request_comments (request_id, user_id, comment, is_internal)
            VALUES (:request_id, :user_id, :comment, :is_internal)
        ");
        $stmt->execute([
            'request_id' => $requestId,
            'user_id' => $user['id'],
            'comment' => trim($commentText),
            'is_internal' => $isInternal ? 1 : 0
        ]);

        if ($user['id'] === $request['requester_id']) {
            NotificationService::notifyDepartmentStaff(
                (int)$request['department_id'],
                $requestId,
                "New Comment on " . $request['request_number'],
                $user['full_name'] . " commented on request."
            );
        } else if (!$isInternal) {
            NotificationService::createNotification(
                (int)$request['requester_id'],
                $requestId,
                "New Comment on " . $request['request_number'],
                "Department staff commented on your request."
            );
        }

        return self::getRequestById($requestId, $user);
    }

    public static function transferRequest(int $requestId, int $targetStaffId, array $user, ?string $reason = null): array {
        $db = Database::getInstance();
        $request = self::getRequestById($requestId, $user);

        // Verify request belongs to assigned staff, department member, head or system admin
        $isAuthorized = ($user['role'] === 'SYSTEM_ADMIN' || (int)$request['assigned_staff_id'] === $user['id']);
        if (!$isAuthorized) {
            $deptStmt = $db->prepare("SELECT 1 FROM department_members WHERE user_id = :user_id AND department_id = :dept_id");
            $deptStmt->execute(['user_id' => $user['id'], 'dept_id' => $request['department_id']]);
            $isAuthorized = (bool)$deptStmt->fetch();
        }
        if (!$isAuthorized) {
            throw new Exception("You are not authorized to transfer this request.", 403);
        }

        // Verify target staff belongs to SAME destination department
        $targetStmt = $db->prepare("
            SELECT u.id, u.full_name FROM department_members dm
            JOIN users u ON dm.user_id = u.id
            WHERE dm.user_id = :user_id AND dm.department_id = :dept_id AND u.status = 'ACTIVE'
        ");
        $targetStmt->execute(['user_id' => $targetStaffId, 'dept_id' => $request['department_id']]);
        $targetStaff = $targetStmt->fetch();

        if (!$targetStaff && $user['role'] !== 'SYSTEM_ADMIN') {
            throw new Exception("Selected target staff does not belong to the destination department.", 400);
        }

        $oldStaffId = $request['assigned_staff_id'];

        $db->beginTransaction();
        try {
            // Update assigned_staff_id
            $updateStmt = $db->prepare("
                UPDATE requests
                SET assigned_staff_id = :staff_id, status = IF(status = 'NEW', 'ASSIGNED', status), updated_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->execute([
                'staff_id' => $targetStaffId,
                'id' => $requestId
            ]);

            // Insert into request_transfer_history
            $transferStmt = $db->prepare("
                INSERT INTO request_transfer_history (request_id, from_user_id, to_user_id, transferred_by, reason)
                VALUES (:request_id, :from_user_id, :to_user_id, :transferred_by, :reason)
            ");
            $transferStmt->execute([
                'request_id' => $requestId,
                'from_user_id' => $oldStaffId,
                'to_user_id' => $targetStaffId,
                'transferred_by' => $user['id'],
                'reason' => trim($reason ?? '')
            ]);

            // Insert status history
            $histStmt = $db->prepare("
                INSERT INTO request_status_history (request_id, changed_by, old_status, new_status, comment)
                VALUES (:request_id, :changed_by, :old_status, :new_status, :comment)
            ");
            $histStmt->execute([
                'request_id' => $requestId,
                'changed_by' => $user['id'],
                'old_status' => $request['status'],
                'new_status' => ($request['status'] === 'NEW' ? 'ASSIGNED' : $request['status']),
                'comment' => "Transferred to " . ($targetStaff['full_name'] ?? "Staff #$targetStaffId") . ($reason ? ": $reason" : "")
            ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Notify new staff member
        NotificationService::createNotification(
            $targetStaffId,
            $requestId,
            "Request Transferred to You: " . $request['request_number'],
            $user['full_name'] . " transferred request '" . $request['title'] . "' to you."
        );

        // Notify requester
        NotificationService::createNotification(
            (int)$request['requester_id'],
            $requestId,
            "Request Reassigned: " . $request['request_number'],
            "Your request has been reassigned to " . ($targetStaff['full_name'] ?? 'department staff') . "."
        );

        AuditService::log($user['id'], 'REQUEST_TRANSFERRED', 'requests', $requestId, ['assigned_staff_id' => $oldStaffId], ['assigned_staff_id' => $targetStaffId, 'reason' => $reason]);

        return self::getRequestById($requestId, $user);
    }
}
