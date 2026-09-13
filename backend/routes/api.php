<?php
// backend/routes/api.php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/DepartmentController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/LocationController.php';
require_once __DIR__ . '/../controllers/RequestController.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';
require_once __DIR__ . '/../middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/SLAController.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../controllers/ReportController.php';

function routeRequest(string $method, string $uri): void {
    // Remove query parameters
    $uri = parse_url($uri, PHP_URL_PATH);
    $uri = rtrim($uri, '/');

    // Remove /api prefix if present
    if (strpos($uri, '/api') === 0) {
        $uri = substr($uri, 4);
    }
    if (empty($uri)) {
        $uri = '/';
    }

    // Auth Routes
    if ($method === 'POST' && $uri === '/auth/login') {
        RateLimitMiddleware::enforce(20, 60);
        AuthController::login();
        return;
    }
    if ($method === 'POST' && $uri === '/auth/logout') {
        AuthController::logout();
        return;
    }
    if ($method === 'GET' && $uri === '/auth/me') {
        AuthController::me();
        return;
    }
    if ($method === 'POST' && $uri === '/auth/change-password') {
        AuthController::changePassword();
        return;
    }

    // SLA Routes
    if ($method === 'GET' && $uri === '/sla') {
        SLAController::index();
        return;
    }
    if (($method === 'POST' || $method === 'PUT') && $uri === '/sla') {
        SLAController::save();
        return;
    }

    // Dashboards
    if ($method === 'GET' && $uri === '/dashboard/admin') {
        DashboardController::admin();
        return;
    }
    if ($method === 'GET' && $uri === '/dashboard/department') {
        DashboardController::department();
        return;
    }

    // Reports
    if ($method === 'GET' && $uri === '/reports/requests') {
        ReportController::requests();
        return;
    }
    if ($method === 'GET' && $uri === '/reports/departments') {
        ReportController::departments();
        return;
    }
    if ($method === 'GET' && $uri === '/reports/staff') {
        ReportController::staff();
        return;
    }

    // Departments & Categories
    if ($method === 'GET' && $uri === '/departments') {
        DepartmentController::index();
        return;
    }
    if ($method === 'POST' && $uri === '/departments') {
        DepartmentController::create();
        return;
    }
    if ($method === 'PUT' && preg_match('#^/departments/(\d+)$#', $uri, $matches)) {
        DepartmentController::update((int)$matches[1]);
        return;
    }
    if ($method === 'GET' && preg_match('#^/departments/(\d+)/categories$#', $uri, $matches)) {
        DepartmentController::getCategories((int)$matches[1]);
        return;
    }
    if ($method === 'GET' && preg_match('#^/departments/(\d+)/staff$#', $uri, $matches)) {
        DepartmentController::getStaff((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && preg_match('#^/departments/(\d+)/assign-hod$#', $uri, $matches)) {
        DepartmentController::assignHod((int)$matches[1]);
        return;
    }

    // Categories Management
    if ($method === 'POST' && $uri === '/categories') {
        CategoryController::create();
        return;
    }
    if ($method === 'PUT' && preg_match('#^/categories/(\d+)$#', $uri, $matches)) {
        CategoryController::update((int)$matches[1]);
        return;
    }

    // Locations
    if ($method === 'GET' && $uri === '/locations') {
        LocationController::index();
        return;
    }
    if ($method === 'POST' && $uri === '/locations') {
        LocationController::create();
        return;
    }
    if ($method === 'PUT' && preg_match('#^/locations/(\d+)$#', $uri, $matches)) {
        LocationController::update((int)$matches[1]);
        return;
    }

    // Requests
    if ($method === 'POST' && $uri === '/requests') {
        RequestController::create();
        return;
    }
    if ($method === 'GET' && $uri === '/requests') {
        RequestController::index();
        return;
    }
    if ($method === 'GET' && preg_match('#^/requests/(\d+)$#', $uri, $matches)) {
        RequestController::show((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && preg_match('#^/requests/(\d+)/claim$#', $uri, $matches)) {
        RequestController::claim((int)$matches[1]);
        return;
    }
    if ($method === 'PATCH' && preg_match('#^/requests/(\d+)/assign$#', $uri, $matches)) {
        RequestController::assign((int)$matches[1]);
        return;
    }
    if ($method === 'PATCH' && preg_match('#^/requests/(\d+)/status$#', $uri, $matches)) {
        RequestController::updateStatus((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && preg_match('#^/requests/(\d+)/comments$#', $uri, $matches)) {
        RequestController::addComment((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && preg_match('#^/requests/(\d+)/attachments$#', $uri, $matches)) {
        RequestController::uploadAttachment((int)$matches[1]);
        return;
    }
    if ($method === 'GET' && preg_match('#^/requests/(\d+)/attachments/(\d+)/download$#', $uri, $matches)) {
        RequestController::downloadAttachment((int)$matches[1], (int)$matches[2]);
        return;
    }

    if ($method === 'POST' && preg_match('#^/requests/(\d+)/transfer$#', $uri, $matches)) {
        RequestController::transfer((int)$matches[1]);
        return;
    }

    // Notifications
    if ($method === 'GET' && $uri === '/notifications') {
        NotificationController::index();
        return;
    }
    if ($method === 'PATCH' && preg_match('#^/notifications/(\d+)/read$#', $uri, $matches)) {
        NotificationController::markAsRead((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && $uri === '/notifications/read-all') {
        NotificationController::markAllAsRead();
        return;
    }
    if ($method === 'DELETE' && $uri === '/notifications/read') {
        NotificationController::deleteAllRead();
        return;
    }
    if ($method === 'DELETE' && preg_match('#^/notifications/(\d+)$#', $uri, $matches)) {
        NotificationController::delete((int)$matches[1]);
        return;
    }

    // Admin Users
    if ($method === 'GET' && $uri === '/users') {
        UserController::index();
        return;
    }
    if ($method === 'POST' && $uri === '/users') {
        UserController::create();
        return;
    }
    if (($method === 'PUT' || $method === 'PATCH') && preg_match('#^/users/(\d+)$#', $uri, $matches)) {
        UserController::update((int)$matches[1]);
        return;
    }
    if ($method === 'POST' && preg_match('#^/users/(\d+)/reset-password$#', $uri, $matches)) {
        UserController::resetPassword((int)$matches[1]);
        return;
    }

    // Fallback 404
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => "Endpoint not found: [$method] $uri",
        'code' => 'NOT_FOUND'
    ]);
}
