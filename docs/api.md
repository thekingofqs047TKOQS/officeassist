# OfficeAssist REST API Documentation

Base URL: `http://127.0.0.1:8000/api`

## 🔑 Authentication
- `POST /auth/login`: Authenticate with `username` (email) and `password`. Returns JWT token & sets HTTP-only cookie.
- `POST /auth/logout`: Clears authentication cookie & token.
- `GET /auth/me`: Returns current user session details.

## 📋 Requests Workflow
- `GET /requests`: List requests (supports `status`, `department_id`, `priority`, `search`, `page`, `limit`).
- `GET /requests/{id}`: Request details, comments, attachments, timeline, and calculated SLA state.
- `POST /requests`: Create request (multipart/form-data supported for attachment).
- `POST /requests/{id}/claim`: Atomic transaction claim by department staff (`409 Conflict` on race condition).
- `PATCH /requests/{id}/assign`: Assign request to department staff (Manager/Admin).
- `PATCH /requests/{id}/status`: Update status (`IN_PROGRESS`, `RESOLVED`, `CLOSED`). Reopening path supported.
- `POST /requests/{id}/comments`: Post public comment or internal staff note (`is_internal = true`).

## ⏱️ SLA Management
- `GET /sla`: Get SLA configuration rules.
- `POST /sla`: Create or update SLA target rule (Admin only).

## 📊 Analytics & Reporting
- `GET /dashboard/admin`: System-wide metrics (Admin only).
- `GET /dashboard/department`: Department workload stats & staff assignment breakdown.
- `GET /reports/requests`: Request volume report (`?export=csv` supported).
- `GET /reports/departments`: Department throughput & response time report.
- `GET /reports/staff`: Staff workload and resolution metrics.

## ⚙️ Administration
- `GET /users`: List users (Admin/Manager).
- `POST /users`: Create user account (Admin).
- `PUT /users/{id}`: Update user account, role, status, and service memberships (Admin).
- `POST /departments`: Create department (Admin).
- `PUT /departments/{id}`: Update department, code, description, and status (Admin).
- `POST /categories`: Create category (Admin/Manager).
- `PUT /categories/{id}`: Update category and guidance notes (Admin/Manager).
- `POST /locations`: Create location (Admin).
- `PUT /locations/{id}`: Update location (Admin).
