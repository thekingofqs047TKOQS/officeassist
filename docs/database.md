# OfficeAssist Database Schema Reference

## Core Schema Structure

1. **`users`**:
   - Stores user accounts, bcrypt password hashes, system roles (`EMPLOYEE`, `DEPARTMENT_STAFF`, `DEPARTMENT_MANAGER`, `SYSTEM_ADMIN`), home department (`department_id`), and default location (`location_id`).

2. **`departments`**:
   - Dynamic service departments (IT, Maintenance, Finance, HR, Administration, Security, Legal). `status` column controls whether active for new submissions.

3. **`department_members`**:
   - Multi-department service authorization mapping (`user_id`, `department_id`, `role_in_department`). Decouples employee home department from service handling authorization.

4. **`request_categories`**:
   - Department-scoped categories with `guidance_notes` for user submission tips.

5. **`locations`**:
   - Office physical locations (`building`, `floor`, `room`, `description`).

6. **`requests`**:
   - Primary request table. Contains permanent `location_snapshot` JSON, assistance instructions, priority (`LOW`, `NORMAL`, `HIGH`, `URGENT`), and lifecycle status (`NEW`, `ASSIGNED`, `IN_PROGRESS`, `RESOLVED`, `CLOSED`, `CANCELLED`).

7. **`request_comments`**:
   - Discussion thread. `is_internal = 1` indicates confidential internal staff notes.

8. **`request_attachments`**:
   - Secure file uploads (`original_name`, `stored_name`, `mime_type`, `file_size`).

9. **`sla_configurations`**:
   - Configurable SLA rules (`department_id`, `priority`, `first_response_target_minutes`, `resolution_target_minutes`, `status`).

10. **`audit_logs`**:
    - Complete administrative action audit logging (`user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`).

## Performance Composite Indexes

- `idx_requests_dept_status`: `(department_id, status, created_at)`
- `idx_requests_requester_status`: `(requester_id, status, created_at)`
- `idx_requests_assigned_status`: `(assigned_staff_id, status, created_at)`
- `idx_notifications_user_read`: `(user_id, is_read, created_at)`
