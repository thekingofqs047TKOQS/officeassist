# OfficeAssist — Centralized Office Support Management Platform

**OfficeAssist** is a production-quality, enterprise-grade office service request and assistance management platform built using **React, Vite, Tailwind CSS, PHP 8.2+, and MySQL 8.0+**.

---

## 🌟 Core System Concept

> **THE EMPLOYEE GOES TO THE SYSTEM; THE REQUEST GOES TO THE DEPARTMENT.**

Organizations can dynamically define service departments (IT, Facilities, Finance, HR, Administration, Security, Legal) and service categories in the database without altering source code.

---

## ⚡ Complete System Features (Phases 1–5.4)

1. **Simplified 3-Role Architecture (Phase 5.4)**:
   - `SYSTEM_ADMIN`: Manages organization, departments, HODs, user accounts, account statuses (`ACTIVE`, `INACTIVE`, `SUSPENDED`), password resets. **Strictly isolated from request processing/claiming/resolving.**
   - `DEPARTMENT_HEAD` (HOD): Head of a specific department. Oversees department queue, claims requests personally, assigns/reassigns unassigned requests to department employees, posts public updates, marks requests completed.
   - `EMPLOYEE`: Single role for all normal employees and staff members. Possesses dual capabilities:
     - **Requester**: Submits service requests to other departments.
     - **Service Worker**: Receives, claims, works on, comments on, and completes requests sent to their home department.

2. **Atomic Request Claiming & Ownership Locking**:
   - `POST /api/requests/{id}/claim` uses row-level database locks to guarantee single-staff ownership without race conditions (`409 Conflict`).
   - Claimed requests cannot be claimed by other ordinary staff members. UI hides `CLAIM` button on assigned requests and displays handler name.

3. **User-Controlled Custom Problem Description**:
   - Predefined categories are optional (`category_id NULL`).
   - Allows typing custom issue descriptions (`issue_type_text`).
   - Auto-saves request drafts in local storage for recovery.

4. **Staff Quick Reply Presets**:
   - Service staff can send quick pre-filled response presets ("I'm on my way to inspect" or "Request received & investigating") to communicate instantly with requesters.

5. **Notification Soft-Deletion & Management**:
   - Notifications support soft-deletion (`deleted_at`).
   - Endpoints to delete single notifications (`DELETE /api/notifications/{id}`) and clear read notifications (`DELETE /api/notifications/read`).

6. **Global Dark/Light Theme System**:
   - Supports `light`, `dark`, and `system` modes with seamless persistence in `localStorage`, `matchMedia` OS listener, anti-FOUC initialization script, and global Tailwind `dark:` styling.

7. **Dynamic SLA Management & Analytics**:
   - Target response & resolution deadlines per priority (`URGENT`, `HIGH`, `NORMAL`, `LOW`).
   - Aggregated Request Volume, Department Throughput, and Staff Performance reports with CSV export.

---

## 🛠️ Installation & Setup Instructions

### 1. Database Setup
Ensure MySQL 8 is running, then import the schema and seed scripts:

```bash
# Execute DDL Schema (includes Phase 5 tables & enums)
mysql -h 127.0.0.1 -P 3307 -u root < database/schema.sql

# Execute Seed Data
mysql -h 127.0.0.1 -P 3307 -u root < database/seeds.sql

# Apply Phase 5 Schema Migrations
php scratch/apply_phase5_migration.php
```

### 2. Start Backend API Server
Start the built-in PHP 8 development server pointing to `backend/public/`:

```bash
php -S 127.0.0.1:8000 -t backend/public
```

The API will be live at `http://127.0.0.1:8000/api`.

### 3. Start Frontend SPA
Install frontend dependencies and start Vite dev server:

```bash
cd frontend
npm install
npm run dev
```

Open `http://localhost:5173` in your browser.

---

## 🧪 Comprehensive Automated Test Suites

Run the full verification and security regression suites:

```bash
# 1. Phase 1 API Baseline Tests
php scratch/test_backend_api.php

# 2. Phase 1 & 2 Security Audit & Vulnerability Suite
php scratch/test_security_audit.php

# 3. Phase 2 End-to-End Request Lifecycle Suite
php scratch/test_phase2_e2e.php

# 4. Phase 3 SLA, Admin & Report Suite
php scratch/test_phase3_suite.php

# 5. Phase 4 Production Readiness Suite
php scratch/test_phase4_suite.php

# 6. Phase 5 Workflow, Messaging, Transfer & Security Suite
php scratch/test_phase5_suite.php

# 7. Phase 5.3 Real-World Ownership, Communication & User Management Suite
php scratch/test_phase5_3_suite.php

# 8. Phase 5.4 3-Role Workflow & Security Suite
php scratch/test_phase5_4_suite.php

# 9. Frontend Production Build Verification
cd frontend && npm run build
```
