<?php
// backend/controllers/ReportController.php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/ReportService.php';

class ReportController {

    public static function requests(): void {
        $user = AuthMiddleware::authenticate();
        $filters = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null
        ];

        try {
            $data = ReportService::getRequestVolumeReport($filters, $user);
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                ReportService::streamCSVExport($data, 'officeassist_request_volume_report.csv');
                return;
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function departments(): void {
        $user = AuthMiddleware::authenticate();
        $filters = [];

        try {
            $data = ReportService::getDepartmentPerformanceReport($filters, $user);
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                ReportService::streamCSVExport($data, 'officeassist_department_performance_report.csv');
                return;
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function staff(): void {
        $user = AuthMiddleware::authenticate();
        $filters = [];

        try {
            $data = ReportService::getStaffPerformanceReport($filters, $user);
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                ReportService::streamCSVExport($data, 'officeassist_staff_performance_report.csv');
                return;
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $code = $e->getCode() === 403 ? 403 : 400;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
