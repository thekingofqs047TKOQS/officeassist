-- ============================================================================
-- OfficeAssist Database Schema (MySQL 8.0+)
-- Engine: InnoDB
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS officeassist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE officeassist;

-- Disable Foreign Key Checks during Schema Creation
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Table: departments
-- Purpose: System-wide departments (IT, HR, Finance, Maintenance, etc.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(30) NOT NULL,
    description TEXT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_department_code (code),
    INDEX idx_departments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: locations
-- Purpose: Office physical locations (Building, Floor, Room)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS locations;
CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    building VARCHAR(100) NOT NULL,
    floor VARCHAR(50) NOT NULL,
    room VARCHAR(50) NOT NULL,
    description TEXT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_building_floor_room (building, floor, room),
    INDEX idx_locations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: users
-- Purpose: System users (Employees, Staff, Managers, Admins)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    department_id BIGINT UNSIGNED NULL, -- Requester's Home Administrative Department
    location_id BIGINT UNSIGNED NULL,   -- User's Primary Desk/Office Location
    role ENUM('EMPLOYEE', 'DEPARTMENT_STAFF', 'DEPARTMENT_MANAGER', 'DEPARTMENT_HEAD', 'SYSTEM_ADMIN') NOT NULL DEFAULT 'EMPLOYEE',
    status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    profile_photo VARCHAR(255) NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_employee_id (employee_id),
    UNIQUE KEY uq_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_department (department_id),
    INDEX idx_users_status (status),

    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_users_location
        FOREIGN KEY (location_id)
        REFERENCES locations (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: department_members
-- Purpose: Department Service Request Handlers/Staffing (Who handles requests sent to a dept)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS department_members;
CREATE TABLE department_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_in_department ENUM('STAFF', 'MANAGER', 'HEAD') NOT NULL DEFAULT 'STAFF',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_dept_member (department_id, user_id),
    INDEX idx_dept_members_user (user_id),

    CONSTRAINT fk_dept_members_department
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_dept_members_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: request_categories
-- Purpose: Request Categories specific to Departments
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS request_categories;
CREATE TABLE request_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    guidance_notes TEXT NULL,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_dept_category_name (department_id, name),
    INDEX idx_categories_dept_status (department_id, status),

    CONSTRAINT fk_categories_department
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: requests
-- Purpose: Core Service Requests submitted by employees to departments
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS requests;
CREATE TABLE requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL,
    requester_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL, -- Destination Department
    category_id BIGINT UNSIGNED NULL,       -- Category under Destination Department (Optional)
    issue_type_text VARCHAR(255) NULL,      -- Custom issue type text if category not selected
    assigned_staff_id BIGINT UNSIGNED NULL, -- Staff currently assigned to handle
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    location_snapshot JSON NULL,            -- Snapshot of Building/Floor/Room at submit time
    assistance_required TEXT NULL,
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') NOT NULL DEFAULT 'NORMAL',
    status ENUM('NEW', 'ASSIGNED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'CANCELLED') NOT NULL DEFAULT 'NEW',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    assigned_at DATETIME NULL,
    started_at DATETIME NULL,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,

    UNIQUE KEY uq_request_number (request_number),
    INDEX idx_requests_requester (requester_id),
    INDEX idx_requests_department_status (department_id, status),
    INDEX idx_requests_assigned_staff (assigned_staff_id),
    INDEX idx_requests_created_at (created_at),

    CONSTRAINT fk_requests_requester
        FOREIGN KEY (requester_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_department
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_category
        FOREIGN KEY (category_id)
        REFERENCES request_categories (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_assigned_staff
        FOREIGN KEY (assigned_staff_id)
        REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_location
        FOREIGN KEY (location_id)
        REFERENCES locations (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: request_attachments
-- Purpose: File attachments associated with requests
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS request_attachments;
CREATE TABLE request_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_attachment_stored_name (stored_name),
    INDEX idx_attachments_request (request_id),

    CONSTRAINT fk_attachments_request
        FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_attachments_uploader
        FOREIGN KEY (uploaded_by)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: request_comments
-- Purpose: Discussion/comment thread on requests
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS request_comments;
CREATE TABLE request_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0, -- 1 = Internal Staff Note, 0 = Public
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_comments_request (request_id, created_at),

    CONSTRAINT fk_comments_request
        FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: request_status_history
-- Purpose: Audit log of status state transitions
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS request_status_history;
CREATE TABLE request_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    old_status ENUM('NEW', 'ASSIGNED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'CANCELLED') NULL,
    new_status ENUM('NEW', 'ASSIGNED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'CANCELLED') NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_history_request (request_id, created_at),

    CONSTRAINT fk_history_request
        FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_history_changed_by
        FOREIGN KEY (changed_by)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: notifications
-- Purpose: User notifications for request updates & assignments
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'SYSTEM',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notifications_user_read (user_id, is_read, created_at),

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notifications_request
        FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: audit_logs
-- Purpose: System administrative and security audit trail
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user_action (user_id, action),
    INDEX idx_audit_entity (entity_type, entity_id),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table: sla_configurations
-- Purpose: SLA response and resolution targets per department / priority
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS sla_configurations;
CREATE TABLE sla_configurations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NULL,
    priority ENUM('LOW', 'NORMAL', 'HIGH', 'URGENT') NOT NULL,
    first_response_target_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    resolution_target_minutes INT UNSIGNED NOT NULL DEFAULT 480,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_dept_priority (department_id, priority),
    INDEX idx_sla_status (status),

    CONSTRAINT fk_sla_department
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable Foreign Key Checks
SET FOREIGN_KEY_CHECKS = 1;
