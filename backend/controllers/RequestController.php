<?php
// backend/controllers/RequestController.php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/RequestService.php';
require_once __DIR__ . '/../services/UploadService.php';

class RequestController {

    public static function create(): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot submit service requests.']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($body['department_id']) || empty($body['title']) || empty($body['description'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Department, Title, and Description are required.'
            ]);
            return;
        }

        try {
            $request = RequestService::createRequest($user, $body);

            // Handle optional file attachment uploaded along with initial request
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                UploadService::processUpload($_FILES['attachment'], $request['id'], $user['id']);
                $request = RequestService::getRequestById($request['id'], $user);
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Request submitted successfully',
                'data' => $request
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public static function index(): void {
        $user = AuthMiddleware::authenticate();
        
        $filters = [
            'status' => $_GET['status'] ?? null,
            'department_id' => isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null,
            'priority' => $_GET['priority'] ?? null,
            'search' => $_GET['search'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 50
        ];

        try {
            $result = RequestService::getRequests($user, $filters);
            echo json_encode([
                'success' => true,
                'data' => $result['items'],
                'pagination' => $result['pagination']
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public static function show(int $id): void {
        $user = AuthMiddleware::authenticate();

        try {
            $request = RequestService::getRequestById($id, $user);
            echo json_encode([
                'success' => true,
                'data' => $request
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 || $e->getCode() === 404 ? $e->getCode() : 400;
            http_response_code($code);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public static function claim(int $id): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot claim service requests.']);
            return;
        }

        try {
            $request = RequestService::claimRequest($id, $user);
            echo json_encode([
                'success' => true,
                'message' => 'Request successfully claimed',
                'data' => $request
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 409 ? 409 : ($e->getCode() === 403 ? 403 : 400);
            http_response_code($code);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode() === 409 ? 'CONCURRENCY_CONFLICT' : 'CLAIM_FAILED'
            ]);
        }
    }

    public static function assign(int $id): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot assign service requests.']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $staffUserId = isset($body['assigned_staff_id']) ? (int)$body['assigned_staff_id'] : 0;
        $comment = isset($body['comment']) ? trim($body['comment']) : null;

        if (!$staffUserId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid staff user ID is required.']);
            return;
        }

        try {
            $request = RequestService::assignStaff($id, $staffUserId, $user, $comment);
            echo json_encode([
                'success' => true,
                'message' => 'Request staff assignment updated',
                'data' => $request
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function updateStatus(int $id): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot modify request status.']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $newStatus = trim($body['status'] ?? '');
        $comment = isset($body['comment']) ? trim($body['comment']) : null;
        $assignedStaffId = isset($body['assigned_staff_id']) ? (int)$body['assigned_staff_id'] : null;

        if (empty($newStatus)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status field is required.']);
            return;
        }

        try {
            $request = RequestService::updateStatus($id, $newStatus, $user, $comment, $assignedStaffId);
            echo json_encode([
                'success' => true,
                'message' => "Request status updated to $newStatus",
                'data' => $request
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function addComment(int $id): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot participate in request discussions.']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $commentText = trim($body['comment'] ?? '');
        $isInternal = !empty($body['is_internal']);

        if (empty($commentText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comment text cannot be empty.']);
            return;
        }

        try {
            $request = RequestService::addComment($id, $user, $commentText, $isInternal);
            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => $request
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function uploadAttachment(int $id): void {
        $user = AuthMiddleware::authenticate();

        if (!isset($_FILES['attachment'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No attachment file provided.']);
            return;
        }

        try {
            RequestService::getRequestById($id, $user);
            
            $uploaded = UploadService::processUpload($_FILES['attachment'], $id, $user['id']);
            $updatedRequest = RequestService::getRequestById($id, $user);

            echo json_encode([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => [
                    'attachment' => $uploaded,
                    'request' => $updatedRequest
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function downloadAttachment(int $requestId, int $attachmentId): void {
        $user = AuthMiddleware::authenticate();

        try {
            RequestService::getRequestById($requestId, $user);
            UploadService::streamAttachment($attachmentId, $requestId);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 404;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function transfer(int $id): void {
        $user = AuthMiddleware::authenticate();

        if ($user['role'] === 'SYSTEM_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'System Administrators cannot transfer requests.']);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($body['target_staff_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'target_staff_id is required.']);
            return;
        }

        try {
            $updated = RequestService::transferRequest($id, (int)$body['target_staff_id'], $user, $body['reason'] ?? null);
            echo json_encode([
                'success' => true,
                'message' => 'Request transferred successfully',
                'data' => $updated
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
