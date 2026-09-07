# OfficeAssist System Architecture

## Architecture Overview

OfficeAssist is designed as a decoupled single-page application (React 18 + Vite + Tailwind CSS) communicating with a high-performance REST API backend (PHP 8.2 + PDO + MySQL 8.0).

```text
React 18 SPA (Vite) + Semantic Design Tokens
    │
    ├── Authorization Bearer / HttpOnly SameSite Cookie
    ▼
PHP 8.2 REST API Controller Layer
    │
    ├── RBACMiddleware & AuthMiddleware & RateLimitMiddleware
    ▼
Service Layer (RequestService, SLAService, DashboardService, ReportService)
    │
    ├── PDO Prepared Statements (Emulated prepares disabled)
    ▼
MySQL 8.0 Database
```

## Simplified 3-Role Model (Phase 5.4)

1. **SYSTEM_ADMIN**:
   - Manages organizational user accounts, department definitions, HOD assignments, account statuses (`ACTIVE`, `INACTIVE`, `SUSPENDED`), and system SLA rules.
   - **Strict Security Isolation**: Strictly prohibited from creating requests, claiming requests, assigning staff, updating statuses, or commenting on request threads (`HTTP 403 Forbidden`).

2. **DEPARTMENT_HEAD (HOD)**:
   - Head of a specific service department. Oversees department queue, claims requests personally, assigns/reassigns unassigned requests to department employees, posts public updates, marks requests completed.

3. **EMPLOYEE**:
   - Single role for all normal employees and staff members. Possesses dual capabilities:
     - **Requester**: Submits service requests to other departments.
     - **Service Worker**: Receives, claims, works on, comments on, and completes requests sent to their home department (`u.department_id` or `department_members`).

## Request Ownership & Locking Model

1. **Department Ownership vs. Individual Handler Ownership**:
   - Department Ownership (`department_id`): Routes requests to the appropriate service department queue upon submission (`status = NEW`, `assigned_staff_id = NULL`).
   - Individual Ownership (`assigned_staff_id`): Locked atomically when a staff member claims an unassigned request (`POST /api/requests/{id}/claim`).
   - Ownership Locking: Claimed requests cannot be claimed by other ordinary staff members (`409 Conflict`). UI hides `CLAIM` button on assigned requests and renders handler name (`Assigned to John Doe`).

2. **Communication & Quick Replies**:
   - Public Messages vs. Internal Notes (`is_internal = 1`). Internal notes are strictly hidden from requesters server-side.
   - Staff Quick Reply Presets: Pre-filled response options ("I'm on my way to inspect" / "Request received & investigating") for rapid communication.

3. **User Account Status Management**:
   - Statuses: `ACTIVE`, `INACTIVE`, `SUSPENDED`.
   - `INACTIVE` / `SUSPENDED` accounts are blocked at authentication server-side (`HTTP 401`). Historical requests and audit entries remain preserved.
