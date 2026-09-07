<?php
// backend/services/UploadService.php

require_once __DIR__ . '/Database.php';

class UploadService {
    private static array $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'zip'];
    private static array $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
        'image/gif',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream'
    ];
    private static int $maxSizeBytes = 10485760; // 10MB

    public static function processUpload(array $file, int $requestId, int $userId): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }

        if ($file['size'] > self::$maxSizeBytes) {
            throw new Exception("File size exceeds maximum allowed limit of 10MB.");
        }

        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::$allowedExtensions)) {
            throw new Exception("File type (.$ext) is not permitted.");
        }

        // Verify MIME type safely
        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        } else if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        }

        if (!$mimeType) {
            $mimeType = $file['type'] ?? 'application/octet-stream';
        }

        if (!in_array($mimeType, self::$allowedMimeTypes)) {
            throw new Exception("Invalid file content type ($mimeType).");
        }

        $config = require __DIR__ . '/../config/app.php';
        $storageDir = $config['storage_path'];

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Generate secure random filename
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destinationPath = $storageDir . '/' . $storedName;

        // Support test mock environment (copy) or standard upload (move_uploaded_file)
        if (!move_uploaded_file($file['tmp_name'], $destinationPath) && !copy($file['tmp_name'], $destinationPath)) {
            throw new Exception("Failed to save uploaded file on server.");
        }

        // Save to database
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO request_attachments (request_id, uploaded_by, original_name, stored_name, mime_type, file_size)
            VALUES (:request_id, :uploaded_by, :original_name, :stored_name, :mime_type, :file_size)
        ");
        $stmt->execute([
            'request_id' => $requestId,
            'uploaded_by' => $userId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $file['size']
        ]);

        $attachmentId = (int)$db->lastInsertId();

        return [
            'id' => $attachmentId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $file['size']
        ];
    }

    public static function streamAttachment(int $attachmentId, ?int $requestId = null): void {
        $db = Database::getInstance();
        $sql = "SELECT * FROM request_attachments WHERE id = :id";
        $params = ['id' => $attachmentId];

        if ($requestId !== null) {
            $sql .= " AND request_id = :request_id";
            $params['request_id'] = $requestId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attachment not found']);
            exit(0);
        }

        $config = require __DIR__ . '/../config/app.php';
        $filePath = $config['storage_path'] . '/' . $attachment['stored_name'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File missing from storage']);
            exit(0);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: attachment; filename="' . addslashes($attachment['original_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit(0);
    }
}
