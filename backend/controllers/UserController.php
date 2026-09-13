<?php
// backend/controllers/UserController.php

require_once __DIR__ . '/../services/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../services/AuditService.php';

class UserController {

    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($user, ['SYSTEM_ADMIN', 'DEPARTMENT_MANAGER']);

        $db = Database::getInstance();
        $params = [];
        $sql = "
            SELECT u.id, u.employee_id, u.full_name, u.email, u.phone, u.role, u.status,
                   u.department_id, d.name AS department_name, u.location_id, u.created_at
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE 1=1
        ";

        if (!empty($_GET['search'])) {
            $sql .= " AND (u.employee_id LIKE :search1 OR u.full_name LIKE :search2 OR u.email LIKE :search3)";
            $sVal = '%' . trim($_GET['search']) . '%';
            $params['search1'] = $sVal;
            $params['search2'] = $sVal;
            $params['search3'] = $sVal;
        }

        if (!empty($_GET['role'])) {
            $sql .= " AND u.role = :role";
            $params['role'] = $_GET['role'];
        }

        if (!empty($_GET['status'])) {
            $sql .= " AND u.status = :status";
            $params['status'] = $_GET['status'];
        }

        $sql .= " ORDER BY u.full_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Attach service memberships
        foreach ($users as &$u) {
            $memStmt = $db->prepare("
                SELECT dm.department_id, d.name AS department_name, dm.role_in_department
                FROM department_members dm
                JOIN departments d ON dm.department_id = d.id
                WHERE dm.user_id = :user_id
            ");
            $memStmt->execute(['user_id' => $u['id']]);
            $u['service_memberships'] = $memStmt->fetchAll();
        }

        echo json_encode(['success' => true, 'data' => $users]);
    }

    public static function create(): void {
        $currentUser = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($currentUser, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);
        $employeeId = trim($body['employee_id'] ?? '');
        $fullName = trim($body['full_name'] ?? '');
        $email = trim($body['email'] ?? '');
        $phone = trim($body['phone'] ?? '');
        $role = trim($body['role'] ?? 'EMPLOYEE');
        $departmentId = isset($body['department_id']) ? (int)$body['department_id'] : null;
        $locationId = isset($body['location_id']) ? (int)$body['location_id'] : null;
        $appConfig = require __DIR__ . '/../config/app.php';
        $defaultPassword = $appConfig['default_password'] ?? 'password123';
        $password = !empty($body['password']) ? trim($body['password']) : $defaultPassword;

        if (empty($employeeId) || empty($fullName) || empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID, Full Name, and Email are required.']);
            return;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO users (employee_id, full_name, email, phone, password_hash, department_id, location_id, role, status, must_change_password)
                VALUES (:employee_id, :full_name, :email, :phone, :password_hash, :department_id, :location_id, :role, 'ACTIVE', 1)
            ");

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt->execute([
                'employee_id' => $employeeId,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => $passwordHash,
                'department_id' => $departmentId,
                'location_id' => $locationId,
                'role' => $role
            ]);

            $newUserId = (int)$db->lastInsertId();

            // Service memberships if provided
            if (isset($body['service_department_id']) && !empty($body['service_department_id'])) {
                $serviceRole = $body['service_role'] ?? (($role === 'DEPARTMENT_HEAD' || $role === 'DEPARTMENT_MANAGER') ? 'MANAGER' : 'STAFF');
                $memStmt = $db->prepare("INSERT INTO department_members (department_id, user_id, role_in_department) VALUES (:dept_id, :user_id, :role_in_dept)");
                $memStmt->execute(['dept_id' => (int)$body['service_department_id'], 'user_id' => $newUserId, 'role_in_dept' => $serviceRole]);
            }

            AuditService::log($currentUser['id'], 'USER_CREATED', 'users', $newUserId, null, ['employee_id' => $employeeId, 'role' => $role]);

            echo json_encode([
                'success' => true,
                'message' => 'User account created successfully',
                'data' => ['id' => $newUserId, 'employee_id' => $employeeId, 'full_name' => $fullName, 'email' => $email, 'role' => $role]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function update(int $id): void {
        $currentUser = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($currentUser, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true);

        try {
            $db = Database::getInstance();
            $userStmt = $db->prepare("SELECT * FROM users WHERE id = :id");
            $userStmt->execute(['id' => $id]);
            $user = $userStmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }

            $updates = [];
            $params = ['id' => $id];

            if (isset($body['full_name'])) { $updates[] = "full_name = :full_name"; $params['full_name'] = trim($body['full_name']); }
            if (isset($body['email'])) { $updates[] = "email = :email"; $params['email'] = trim($body['email']); }
            if (isset($body['phone'])) { $updates[] = "phone = :phone"; $params['phone'] = trim($body['phone']); }
            if (isset($body['role'])) { $updates[] = "role = :role"; $params['role'] = trim($body['role']); }
            if (isset($body['status'])) { $updates[] = "status = :status"; $params['status'] = trim($body['status']); }
            if (array_key_exists('department_id', $body)) {
                $newDeptId = $body['department_id'] ? (int)$body['department_id'] : null;
                if ($newDeptId !== null) {
                    $chkDept = $db->prepare("SELECT id FROM departments WHERE id = :id AND status = 'ACTIVE'");
                    $chkDept->execute(['id' => $newDeptId]);
                    if (!$chkDept->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Selected department does not exist or is inactive.']);
                        return;
                    }
                }

                $updates[] = "department_id = :department_id";
                $params['department_id'] = $newDeptId;

                // Sync department_members for consistent HOD & department staffing relationships
                $effectiveRole = $body['role'] ?? $user['role'];
                if ($newDeptId !== null) {
                    if (in_array($effectiveRole, ['DEPARTMENT_HEAD', 'DEPARTMENT_MANAGER'])) {
                        $oldHod = $db->prepare("UPDATE users SET role = 'EMPLOYEE' WHERE department_id = :dept_id AND id != :id AND role IN ('DEPARTMENT_HEAD', 'DEPARTMENT_MANAGER')");
                        $oldHod->execute(['dept_id' => $newDeptId, 'id' => $id]);

                        $oldHodMem = $db->prepare("UPDATE department_members SET role_in_department = 'STAFF' WHERE department_id = :dept_id AND user_id != :id AND role_in_department IN ('HEAD', 'MANAGER')");
                        $oldHodMem->execute(['dept_id' => $newDeptId, 'id' => $id]);
                    }

                    $delMem = $db->prepare("DELETE FROM department_members WHERE user_id = :user_id");
                    $delMem->execute(['user_id' => $id]);

                    $serviceRole = in_array($effectiveRole, ['DEPARTMENT_HEAD', 'DEPARTMENT_MANAGER']) ? 'MANAGER' : 'STAFF';
                    $insMem = $db->prepare("INSERT INTO department_members (department_id, user_id, role_in_department) VALUES (:dept_id, :user_id, :role_in_dept)");
                    $insMem->execute(['dept_id' => $newDeptId, 'user_id' => $id, 'role_in_dept' => $serviceRole]);
                } else {
                    $delMem = $db->prepare("DELETE FROM department_members WHERE user_id = :user_id");
                    $delMem->execute(['user_id' => $id]);
                }
            }
            if (array_key_exists('location_id', $body)) { $updates[] = "location_id = :location_id"; $params['location_id'] = $body['location_id'] ? (int)$body['location_id'] : null; }

            if (!empty($body['password'])) {
                $updates[] = "password_hash = :password_hash";
                $params['password_hash'] = password_hash(trim($body['password']), PASSWORD_BCRYPT);
            }

            if (!empty($updates)) {
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            // Update service memberships if explicitly provided
            if (isset($body['service_department_id'])) {
                $delMem = $db->prepare("DELETE FROM department_members WHERE user_id = :user_id");
                $delMem->execute(['user_id' => $id]);

                if (!empty($body['service_department_id'])) {
                    $serviceRole = $body['service_role'] ?? ((in_array($body['role'] ?? $user['role'], ['DEPARTMENT_HEAD', 'DEPARTMENT_MANAGER'])) ? 'MANAGER' : 'STAFF');
                    $memStmt = $db->prepare("INSERT INTO department_members (department_id, user_id, role_in_department) VALUES (:dept_id, :user_id, :role_in_dept)");
                    $memStmt->execute(['dept_id' => (int)$body['service_department_id'], 'user_id' => $id, 'role_in_dept' => $serviceRole]);
                }
            }

            AuditService::log($currentUser['id'], 'USER_UPDATED', 'users', $id, ['status' => $user['status']], ['status' => $body['status'] ?? $user['status']]);

            echo json_encode(['success' => true, 'message' => 'User account updated successfully']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function resetPassword(int $id): void {
        $currentUser = AuthMiddleware::authenticate();
        RBACMiddleware::requireRole($currentUser, ['SYSTEM_ADMIN']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $appConfig = require __DIR__ . '/../config/app.php';
        $defaultPassword = $appConfig['default_password'] ?? 'password123';
        $newPassword = !empty($body['new_password']) ? trim($body['new_password']) : $defaultPassword;

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id");
            $stmt->execute([
                'hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'id' => $id
            ]);

            AuditService::log($currentUser['id'], 'USER_PASSWORD_RESET', 'users', $id, null, ['reset_by' => $currentUser['id']]);

            echo json_encode(['success' => true, 'message' => 'User password reset to default successfully']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
