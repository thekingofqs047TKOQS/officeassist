-- ============================================================================
-- OfficeAssist Seed Data
-- ============================================================================

USE officeassist;

-- Disable Foreign Key Checks during Seeding
SET FOREIGN_KEY_CHECKS = 0;

-- Clean existing data
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE request_status_history;
TRUNCATE TABLE request_comments;
TRUNCATE TABLE request_attachments;
TRUNCATE TABLE requests;
TRUNCATE TABLE request_categories;
TRUNCATE TABLE department_members;
TRUNCATE TABLE users;
TRUNCATE TABLE locations;
TRUNCATE TABLE departments;

-- ----------------------------------------------------------------------------
-- 1. Departments
-- ----------------------------------------------------------------------------
INSERT INTO departments (id, name, code, description, status) VALUES
(1, 'Information Technology', 'IT', 'Hardware, Software, Network & Technical Assistance', 'ACTIVE'),
(2, 'Maintenance & Facilities', 'MAINT', 'Electrical, Plumbing, HVAC & Furniture Repair', 'ACTIVE'),
(3, 'Finance & Accounting', 'FIN', 'Invoicing, Expenses, Procurement & Payroll Queries', 'ACTIVE'),
(4, 'Human Resources', 'HR', 'Employee Support, Onboarding, Benefits & Policy', 'ACTIVE'),
(5, 'Administration', 'ADMIN', 'Office Supplies, Travel, Visitor Badges & Logistics', 'ACTIVE'),
(6, 'Security', 'SEC', 'Access Badges, Keycards, Parking & Campus Safety', 'ACTIVE'),
(7, 'Legal & Compliance', 'LEGAL', 'Contract Review, NDAs & Regulatory Guidance', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- 2. Locations
-- ----------------------------------------------------------------------------
INSERT INTO locations (id, building, floor, room, description, status) VALUES
(1, 'Main Building A', 'Floor 1', 'Room 101 - Reception', 'Main entrance reception area', 'ACTIVE'),
(2, 'Main Building A', 'Floor 2', 'Room 205 - Marketing Desk Area', 'Open space marketing desks', 'ACTIVE'),
(3, 'Main Building A', 'Floor 3', 'Room 310 - Executive Boardroom', 'Third floor conference room', 'ACTIVE'),
(4, 'Tech Tower B', 'Floor 1', 'Room B12 - IT Helpdesk', 'IT Support center', 'ACTIVE'),
(5, 'Tech Tower B', 'Floor 4', 'Room B402 - Finance Office', 'Accounts & Payroll department', 'ACTIVE'),
(6, 'Annex C', 'Ground Floor', 'Room C01 - Facilities Workshop', 'Maintenance storage & office', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- 3. Request Categories
-- ----------------------------------------------------------------------------
-- IT Categories (Department ID: 1)
INSERT INTO request_categories (department_id, name, description, status) VALUES
(1, 'Network & Wi-Fi', 'Internet connection drops, VPN issues, Wi-Fi access', 'ACTIVE'),
(1, 'Computer Hardware', 'Laptop/Desktop repair, monitor issues, keyboard/mouse replacement', 'ACTIVE'),
(1, 'Printer & Scanner', 'Printer paper jams, toner replacement, network scanning issues', 'ACTIVE'),
(1, 'Software & Apps', 'Software installation, license requests, software crashes', 'ACTIVE'),
(1, 'Email & User Account', 'Password resets, email setup, permission requests', 'ACTIVE');

-- Maintenance Categories (Department ID: 2)
INSERT INTO request_categories (department_id, name, description, status) VALUES
(2, 'Electricity & Lighting', 'Blown bulbs, power outlet failure, breaker trips', 'ACTIVE'),
(2, 'Plumbing & Restroom', 'Water leakage, faucet repair, drain clogs', 'ACTIVE'),
(2, 'Air Conditioning / HVAC', 'Temperature control, AC cooling/heating failure, ventilation', 'ACTIVE'),
(2, 'Furniture & Layout', 'Desk adjustment, chair replacement, whiteboards', 'ACTIVE');

-- Finance Categories (Department ID: 3)
INSERT INTO request_categories (department_id, name, description, status) VALUES
(3, 'Expense Reimbursement', 'Travel claims, expense report inquiries', 'ACTIVE'),
(3, 'Vendor Invoicing', 'Invoice processing & payment status', 'ACTIVE'),
(3, 'Procurement Request', 'Purchase order creation & budget approval', 'ACTIVE');

-- HR Categories (Department ID: 4)
INSERT INTO request_categories (department_id, name, description, status) VALUES
(4, 'Employee Benefits', 'Health insurance, pension & perk questions', 'ACTIVE'),
(4, 'Leave & Absence', 'Annual leave balance, sick leave documentation', 'ACTIVE'),
(4, 'Payroll & Salary Slip', 'Paystub clarification, tax deduction queries', 'ACTIVE');

-- Security Categories (Department ID: 6)
INSERT INTO request_categories (department_id, name, description, status) VALUES
(6, 'Access Badge / Keycard', 'Lost badge replacement, new room permission grant', 'ACTIVE'),
(6, 'Visitor Permit', 'Guest parking & building access pass', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- 4. Users (All passwords: "password123")
-- ----------------------------------------------------------------------------
INSERT INTO users (id, employee_id, full_name, email, phone, password_hash, department_id, location_id, role, status) VALUES
(1, 'EMP-001', 'System Administrator', 'admin@officeassist.com', '+1-555-0101', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 1, 4, 'SYSTEM_ADMIN', 'ACTIVE'),
(2, 'EMP-002', 'Alex Mercer (IT Manager)', 'alex.mercer@officeassist.com', '+1-555-0102', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 1, 4, 'DEPARTMENT_MANAGER', 'ACTIVE'),
(3, 'EMP-003', 'John Doe (IT Staff)', 'john.doe@officeassist.com', '+1-555-0103', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 1, 4, 'DEPARTMENT_STAFF', 'ACTIVE'),
(4, 'EMP-004', 'Mary Smith (IT Staff)', 'mary.smith@officeassist.com', '+1-555-0104', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 1, 4, 'DEPARTMENT_STAFF', 'ACTIVE'),
(5, 'EMP-005', 'Robert Vance (Maint Manager)', 'robert.vance@officeassist.com', '+1-555-0105', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 2, 6, 'DEPARTMENT_MANAGER', 'ACTIVE'),
(6, 'EMP-006', 'David Miller (Maint Staff)', 'david.miller@officeassist.com', '+1-555-0106', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 2, 6, 'DEPARTMENT_STAFF', 'ACTIVE'),
(7, 'EMP-007', 'Sarah Jenkins (Marketing)', 'sarah.jenkins@officeassist.com', '+1-555-0107', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 5, 2, 'EMPLOYEE', 'ACTIVE'),
(8, 'EMP-008', 'Michael Brown (Finance)', 'michael.brown@officeassist.com', '+1-555-0108', '$2y$10$iXp4tq0uiU7gafPc7gaxRubzCUK3qygy7/Bl1q5sDnciVeksoVXJS', 3, 5, 'EMPLOYEE', 'ACTIVE');

-- ----------------------------------------------------------------------------
-- 5. Department Service Staff Memberships
-- ----------------------------------------------------------------------------
INSERT INTO department_members (department_id, user_id, role_in_department) VALUES
(1, 2, 'MANAGER'),
(1, 3, 'STAFF'),
(1, 4, 'STAFF'),
(2, 5, 'MANAGER'),
(2, 6, 'STAFF');

-- Re-enable Foreign Key Checks
SET FOREIGN_KEY_CHECKS = 1;
