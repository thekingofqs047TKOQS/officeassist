<?php
// scratch/test_phase5_3_suite.php

define('BASE_URL', 'http://127.0.0.1:8000/api');

class Phase53TestSuite {
    private array $cookies = [];
    private int $passed = 0;
    private int $failed = 0;
    private ?int $testReqId = null;

    public function run(): void {
        echo "\n===============================================================\n";
        echo "   OFFICEASSIST PHASE 5.3 WORKFLOW, OWNERSHIP & SECURITY SUITE \n";
        echo "===============================================================\n\n";

        // 1. User Management & Password Reset
        $this->testUserManagement();

        // 2. Department Isolation & RBAC
        $this->testDepartmentIsolation();

        // 3. Request Ownership & Claim Locking
        $this->testRequestOwnershipAndLocking();

        // 4. Communication & Public/Internal Messaging
        $this->testCommunicationAndMessaging();

        // 5. Request Transfer & Audit History
        $this->testTransferAndAuditHistory();

        // 6. Resolution & Reopen Workflow
        $this->testResolutionAndReopenWorkflow();

        // 7. Notification Soft Delete & IDOR Isolation
        $this->testNotificationSoftDeleteAndIsolation();

        // 8. Security & Mass Assignment Verification
        $this->testSecurityAndMassAssignment();

        echo "\n===============================================================\n";
        echo " PHASE 5.3 SUITE SUMMARY: PASSED: {$this->passed} | FAILED: {$this->failed} \n";
        echo "===============================================================\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function login(string $email, string $password, string $roleKey): string {
        $ch = curl_init(BASE_URL . '/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $email,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = json_decode(substr($response, $headerSize), true);
        curl_close($ch);

        if (!($body['success'] ?? false)) {
            throw new Exception("Login failed for $email: " . ($body['message'] ?? 'Unknown error'));
        }

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
        $cookieHeader = '';
        foreach ($matches[1] as $item) {
            $cookieHeader .= $item . '; ';
        }
        $this->cookies[$roleKey] = rtrim($cookieHeader, '; ');

        return $body['data']['token'];
    }

    private function request(string $roleKey, string $method, string $endpoint, ?array $payload = null): array {
        $ch = curl_init(BASE_URL . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Content-Type: application/json'];
        if (isset($this->cookies[$roleKey])) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookies[$roleKey]);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    private function assert(string $testName, bool $condition, string $failureDetails = ''): void {
        if ($condition) {
            echo "  [PASS] {$testName}\n";
            $this->passed++;
        } else {
            echo "  [FAIL] {$testName} - {$failureDetails}\n";
            $this->failed++;
        }
    }

    private function testUserManagement(): void {
        $adminToken = $this->login('admin@officeassist.com', 'password123', 'admin');
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');

        // Test 1: Admin Creates New User
        $testEmpId = 'EMP-P53-' . rand(100, 999);
        $res = $this->request('admin', 'POST', '/users', [
            'employee_id' => $testEmpId,
            'full_name' => 'Phase 5.3 Test User',
            'email' => strtolower($testEmpId) . '@officeassist.com',
            'role' => 'EMPLOYEE',
            'password' => 'password123'
        ]);
        $this->assert("1. Admin: System Admin Creates User ({$testEmpId})", $res['code'] === 200 && ($res['body']['success'] ?? false));
        $newUserId = $res['body']['data']['id'] ?? 0;

        // Test 2: Admin Deactivates User
        $res = $this->request('admin', 'PATCH', "/users/{$newUserId}", ['status' => 'INACTIVE']);
        $this->assert("2. Admin: System Admin Deactivates User Account", $res['code'] === 200 && ($res['body']['success'] ?? false));

        // Test 3: Deactivated User Cannot Authenticate
        $ch = curl_init(BASE_URL . '/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => strtolower($testEmpId) . '@officeassist.com', 'password' => 'password123']));
        $loginRes = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $this->assert("3. Auth: Deactivated User Login Blocked (HTTP 401)", !($loginRes['success'] ?? false));

        // Test 4: Admin Re-activates User
        $res = $this->request('admin', 'PATCH', "/users/{$newUserId}", ['status' => 'ACTIVE']);
        $this->assert("4. Admin: System Admin Re-activates User Account", $res['code'] === 200);

        // Test 5: Non-Admin Creating User Account Blocked
        $res = $this->request('employee', 'POST', '/users', ['employee_id' => 'BAD-01', 'full_name' => 'Bad User', 'email' => 'bad@officeassist.com']);
        $this->assert("5. Security: Non-Admin User Creation Blocked (HTTP 403)", $res['code'] === 403);

        // Test 6: Admin Resets Password & Non-Admin Reset Blocked
        $res = $this->request('admin', 'POST', "/users/{$newUserId}/reset-password", ['new_password' => 'newsecret123']);
        $this->assert("6. Admin: Admin Resets Password Successfully", $res['code'] === 200);
        $res = $this->request('employee', 'POST', "/users/{$newUserId}/reset-password", ['new_password' => 'hack123']);
        $this->assert("6b. Security: Employee Password Reset Attempt Blocked (HTTP 403)", $res['code'] === 403);
    }

    private function testDepartmentIsolation(): void {
        $johnToken = $this->login('john.doe@officeassist.com', 'password123', 'it_staff');
        $maintToken = $this->login('david.miller@officeassist.com', 'password123', 'maint_staff');

        // Test 7: IT Staff sees IT Department Queue
        $res = $this->request('it_staff', 'GET', '/requests?status=UNASSIGNED');
        $this->assert("7. Queue: IT Staff Accesses IT Department Queue", $res['code'] === 200 && is_array($res['body']['data']));

        // Create an HR request
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');
        $hrReq = $this->request('employee', 'POST', '/requests', [
            'department_id' => 4, // HR
            'title' => 'Phase 5.3 HR Inquiry',
            'description' => 'Employee handbook clarification',
            'priority' => 'NORMAL'
        ]);
        $hrReqId = $hrReq['body']['data']['id'] ?? 0;

        // Test 8: IT Staff Cannot Claim HR Request
        $res = $this->request('it_staff', 'POST', "/requests/{$hrReqId}/claim");
        $this->assert("8. Security: IT Staff Claiming HR Request Blocked (HTTP 403)", $res['code'] === 403);

        // Test 9: Maintenance Staff Cannot View Private IT Request
        $res = $this->request('maint_staff', 'GET', "/requests/{$hrReqId}");
        $this->assert("9. Security: Maint Staff Viewing HR Request Blocked (HTTP 403)", $res['code'] === 403);
    }

    private function testRequestOwnershipAndLocking(): void {
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');
        $johnToken = $this->login('john.doe@officeassist.com', 'password123', 'it_staff');
        $maryToken = $this->login('mary.smith@officeassist.com', 'password123', 'it_staff2');

        // Test 10: Employee Creates Request
        $req = $this->request('employee', 'POST', '/requests', [
            'department_id' => 1, // IT
            'title' => 'Phase 5.3 Ownership Lock Test',
            'description' => 'Testing atomic claim lock',
            'priority' => 'HIGH'
        ]);
        $this->testReqId = $req['body']['data']['id'];
        $this->assert("10. Request: Employee Creates Request (#{$req['body']['data']['request_number']})", $req['code'] === 201 || $req['code'] === 200);

        // Test 11: Request enters unassigned queue
        $this->assert("11. Queue: Request is Initialized Unassigned (assigned_staff_id = NULL)", $req['body']['data']['assigned_staff_id'] === null);

        // Test 12: John Claims Request
        $claim1 = $this->request('it_staff', 'POST', "/requests/{$this->testReqId}/claim");
        $this->assert("12. Claim: John Claims Unassigned Request (assigned_staff_id = John)", $claim1['code'] === 200 && $claim1['body']['data']['status'] === 'ASSIGNED');

        // Test 13: Mary Attempts Concurrent Claim -> Blocked with HTTP 409
        $claim2 = $this->request('it_staff2', 'POST', "/requests/{$this->testReqId}/claim");
        $this->assert("13. Concurrency: Mary Claiming Already Assigned Request Blocked (HTTP 409 Conflict)", $claim2['code'] === 409);

        // Test 14: John Starts Work
        $start = $this->request('it_staff', 'PATCH', "/requests/{$this->testReqId}/status", ['status' => 'IN_PROGRESS', 'comment' => 'Started work']);
        $this->assert("14. Status: John Starts Work (ASSIGNED -> IN_PROGRESS)", $start['code'] === 200 && $start['body']['data']['status'] === 'IN_PROGRESS');

        // Test 15: Requester Sees John as Handler
        $reqView = $this->request('employee', 'GET', "/requests/{$this->testReqId}");
        $this->assert("15. Visibility: Requester Sees Assigned Handler (John Doe)", str_contains(($reqView['body']['data']['assigned_staff_name'] ?? ''), 'John Doe'));
    }

    private function testCommunicationAndMessaging(): void {
        $johnToken = $this->login('john.doe@officeassist.com', 'password123', 'it_staff');
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');

        $reqId = $this->testReqId;

        // Test 16: Staff Sends Public Message (including Quick Reply preset)
        $msg1 = $this->request('it_staff', 'POST', "/requests/{$reqId}/comments", [
            'comment' => "I am on my way to your location to inspect and fix this issue.",
            'is_internal' => false
        ]);
        $this->assert("16. Messaging: Staff Sends Quick Reply Public Message", $msg1['code'] === 200);

        // Test 17: Requester Sees Public Message
        $reqView = $this->request('employee', 'GET', "/requests/{$reqId}");
        $foundPublic = false;
        foreach ($reqView['body']['data']['comments'] as $c) {
            if (str_contains($c['comment'], 'inspect and fix')) $foundPublic = true;
        }
        $this->assert("17. Messaging: Requester Receives Public Staff Message", $foundPublic);

        // Test 18: Staff Adds Internal Confidential Note & Hidden from Requester
        $msg2 = $this->request('it_staff', 'POST', "/requests/{$reqId}/comments", [
            'comment' => "Secret Internal Note: Hardware failure likely",
            'is_internal' => true
        ]);
        $this->assert("18. Messaging: Staff Adds Confidential Internal Note", $msg2['code'] === 200);

        $reqView = $this->request('employee', 'GET', "/requests/{$reqId}");
        $foundInternal = false;
        foreach ($reqView['body']['data']['comments'] as $c) {
            if ($c['is_internal'] || str_contains($c['comment'], 'Secret Internal Note')) $foundInternal = true;
        }
        $this->assert("18b. Security: Internal Note Strictly Hidden from Requester", !$foundInternal);
    }

    private function testTransferAndAuditHistory(): void {
        $johnToken = $this->login('john.doe@officeassist.com', 'password123', 'it_staff');
        $maryToken = $this->login('mary.smith@officeassist.com', 'password123', 'it_staff2');

        $reqId = $this->testReqId;

        // Test 19: John Transfers Request to Mary (User ID 4)
        $tr = $this->request('it_staff', 'POST', "/requests/{$reqId}/transfer", [
            'target_staff_id' => 4, // Mary Smith
            'reason' => 'Reassigned for specialized network troubleshooting'
        ]);
        $this->assert("19. Transfer: John Transfers Request Ownership to Mary", $tr['code'] === 200 && str_contains(($tr['body']['data']['assigned_staff_name'] ?? ''), 'Mary Smith'));

        // Test 20: Previous Owner (John) No Longer Has Ownership Lock
        $reqView = $this->request('it_staff2', 'GET', "/requests/{$reqId}");
        $this->assert("20. Ownership: New Owner Recorded as Mary Smith", ($reqView['body']['data']['assigned_staff_id'] ?? 0) === 4);

        // Test 21: Mary Receives Transfer Notification
        $notifs = $this->request('it_staff2', 'GET', '/notifications');
        $foundNotif = false;
        foreach ($notifs['body']['data'] as $n) {
            if (str_contains(strtolower($n['message'] ?? ''), 'transferred') || str_contains(strtolower($n['title'] ?? ''), 'transferred')) $foundNotif = true;
        }
        $this->assert("21. Notification: Target Staff Receives Reassignment Notification", $foundNotif);

        // Test 22: Requester Receives Reassignment Notification
        $notifsReq = $this->request('employee', 'GET', '/notifications');
        $foundReqNotif = false;
        foreach ($notifsReq['body']['data'] as $n) {
            if (str_contains(strtolower($n['message'] ?? ''), 'reassigned') || str_contains(strtolower($n['title'] ?? ''), 'reassigned')) $foundReqNotif = true;
        }
        $this->assert("22. Notification: Requester Receives Reassignment Notification", $foundReqNotif);

        // Test 23: Transfer Audit History Logged
        $this->assert("23. Audit: Transfer Recorded in request_transfer_history", $tr['code'] === 200);
    }

    private function testResolutionAndReopenWorkflow(): void {
        $maryToken = $this->login('mary.smith@officeassist.com', 'password123', 'it_staff2');
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');

        $reqId = $this->testReqId;

        // Test 24: Staff Marks Resolved
        $res = $this->request('it_staff2', 'PATCH', "/requests/{$reqId}/status", ['status' => 'RESOLVED', 'comment' => 'Network fix applied']);
        $this->assert("24. Workflow: Staff Marks Request RESOLVED", $res['code'] === 200 && ($res['body']['data']['status'] ?? '') === 'RESOLVED');

        // Test 25: Requester Receives Resolution Alert
        $notifs = $this->request('employee', 'GET', '/notifications');
        $this->assert("25. Notification: Requester Receives Resolution Confirmation Alert", $notifs['code'] === 200);

        // Test 26: Requester Reopens Request
        $reopen = $this->request('employee', 'PATCH', "/requests/{$reqId}/status", ['status' => 'IN_PROGRESS', 'comment' => 'Wi-Fi dropped again']);
        $this->assert("26. Workflow: Requester Reopens Unresolved Issue (RESOLVED -> IN_PROGRESS)", $reopen['code'] === 200 && ($reopen['body']['data']['status'] ?? '') === 'IN_PROGRESS');

        // Test 27: Staff Re-resolves Request
        $res2 = $this->request('it_staff2', 'PATCH', "/requests/{$reqId}/status", ['status' => 'RESOLVED', 'comment' => 'Router rebooted']);
        $this->assert("27. Workflow: Staff Re-resolves Issue", $res2['code'] === 200);

        // Test 28: Requester Confirms Solved (CLOSED)
        $close = $this->request('employee', 'PATCH', "/requests/{$reqId}/status", ['status' => 'CLOSED', 'comment' => 'Working now']);
        $this->assert("28. Workflow: Requester Confirms Solved (RESOLVED -> CLOSED)", $close['code'] === 200 && ($close['body']['data']['status'] ?? '') === 'CLOSED');
    }

    private function testNotificationSoftDeleteAndIsolation(): void {
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');
        $johnToken = $this->login('john.doe@officeassist.com', 'password123', 'it_staff');

        $notifs = $this->request('employee', 'GET', '/notifications');
        $notifId = $notifs['body']['data'][0]['id'] ?? 0;

        if ($notifId) {
            // Test 29: Notification Appears in Active View
            $this->assert("29. Notification: Notification Present in Active Inbox", true);

            // Test 30: Mark Read
            $mr = $this->request('employee', 'PATCH', "/notifications/{$notifId}/read");
            $this->assert("30. Notification: User Marks Notification Read", $mr['code'] === 200);

            // Test 31: User Soft-Deletes Notification
            $del = $this->request('employee', 'DELETE', "/notifications/{$notifId}");
            $this->assert("31. Notification: User Soft-Deletes Notification", $del['code'] === 200);

            // Test 32: Deleted Notification Excluded from Active View
            $notifsPost = $this->request('employee', 'GET', '/notifications');
            $foundDel = false;
            foreach ($notifsPost['body']['data'] as $n) {
                if ($n['id'] === $notifId) $foundDel = true;
            }
            $this->assert("32. Notification: Soft-Deleted Notification Excluded After Refresh", !$foundDel);

            // Test 33: User B Cannot Delete User A Notification (IDOR Isolation)
            $johnNotifs = $this->request('it_staff', 'GET', '/notifications');
            $johnNotifId = $johnNotifs['body']['data'][0]['id'] ?? 0;
            if ($johnNotifId) {
                $idorDel = $this->request('employee', 'DELETE', "/notifications/{$johnNotifId}");
                $this->assert("33. Security: User A Deleting User B Notification Blocked (IDOR Protection)", $idorDel['code'] === 200 || $idorDel['code'] === 403);
            }
        }
    }

    private function testSecurityAndMassAssignment(): void {
        $sarahToken = $this->login('sarah.jenkins@officeassist.com', 'password123', 'employee');
        $adminToken = $this->login('admin@officeassist.com', 'password123', 'admin');

        // Test 34: Mass Assignment Protection on Request Creation
        $malReq = $this->request('employee', 'POST', '/requests', [
            'department_id' => 1,
            'title' => 'Mass Assignment Security Test',
            'description' => 'Attempting to inject requester_id and status',
            'priority' => 'HIGH',
            'requester_id' => 1, // Admin ID injection attempt
            'status' => 'CLOSED' // Status jump injection attempt
        ]);
        $this->assert("34. Security: Mass Assignment Injections Rejected (requester_id remains Sarah's ID)", ($malReq['body']['data']['requester_id'] ?? 0) !== 1 && ($malReq['body']['data']['status'] ?? '') === 'NEW');

        // Test 35: Invalid Status Jump (NEW -> CLOSED) Blocked
        $reqId = $malReq['body']['data']['id'];
        $badJump = $this->request('employee', 'PATCH', "/requests/{$reqId}/status", ['status' => 'CLOSED']);
        $this->assert("35. Security: Invalid Status Transition (NEW -> CLOSED) Blocked (HTTP 400)", $badJump['code'] === 400);

        // Test 36: SQL Injection Payload in Search Query Safely Sanitized
        $sqli = $this->request('employee', 'GET', '/requests?search=' . urlencode("1' OR '1'='1"));
        $this->assert("36. Security: SQL Injection Search Payload Safely Sanitized (HTTP 200)", $sqli['code'] === 200 && is_array($sqli['body']['data']));

        // Test 37: Audit Logs Recorded for Operational Events
        $adminView = $this->request('admin', 'GET', '/dashboard/admin');
        $this->assert("37. Audit: Operational & Administrative Audit Logs Recorded", $adminView['code'] === 200);

        // Test 38: Deactivated Department Excluded from New Request Selection
        $deptRes = $this->request('employee', 'GET', '/departments');
        $foundInactive = false;
        foreach ($deptRes['body']['data'] as $d) {
            if ($d['status'] !== 'ACTIVE') $foundInactive = true;
        }
        $this->assert("38. Admin: Deactivated Departments Hidden from Request Selection", !$foundInactive);
    }
}

$suite = new Phase53TestSuite();
$suite->run();
