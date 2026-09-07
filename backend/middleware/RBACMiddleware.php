<?php
// backend/middleware/RBACMiddleware.php

class RBACMiddleware {
    public static function requireRole(array $user, array $allowedRoles): void {
        // Expand role aliases for seamless compatibility with 3-role model (SYSTEM_ADMIN, DEPARTMENT_HEAD, EMPLOYEE)
        if (in_array('DEPARTMENT_MANAGER', $allowedRoles) && !in_array('DEPARTMENT_HEAD', $allowedRoles)) {
            $allowedRoles[] = 'DEPARTMENT_HEAD';
        }
        if (in_array('DEPARTMENT_HEAD', $allowedRoles) && !in_array('DEPARTMENT_MANAGER', $allowedRoles)) {
            $allowedRoles[] = 'DEPARTMENT_MANAGER';
        }
        if (in_array('DEPARTMENT_STAFF', $allowedRoles) && !in_array('EMPLOYEE', $allowedRoles)) {
            $allowedRoles[] = 'EMPLOYEE';
        }
        if (in_array('EMPLOYEE', $allowedRoles) && !in_array('DEPARTMENT_STAFF', $allowedRoles)) {
            $allowedRoles[] = 'DEPARTMENT_STAFF';
        }

        if (!in_array($user['role'], $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden. You do not have permission to perform this action.',
                'code' => 'FORBIDDEN'
            ]);
            exit(0);
        }
    }

    public static function isSystemAdmin(array $user): bool {
        return $user['role'] === 'SYSTEM_ADMIN';
    }

    public static function isDepartmentHead(array $user): bool {
        return $user['role'] === 'DEPARTMENT_HEAD' || $user['role'] === 'DEPARTMENT_MANAGER';
    }

    public static function isEmployee(array $user): bool {
        return $user['role'] === 'EMPLOYEE' || $user['role'] === 'DEPARTMENT_STAFF';
    }
}
