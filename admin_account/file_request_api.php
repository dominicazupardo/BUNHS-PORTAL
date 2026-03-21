<?php

/**
 * file_request_api.php
 * ─────────────────────────────────────────────────────────────
 * Full implementation for file request system.
 * 
 * Handles:
 *   - Student requests for restricted downloadable forms
 *   - Admin approval/rejection of requests
 *   - Single download tracking after approval
 *   - File lock/unlock status management
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../session_config.php';
include '../db_connection.php';

header('Content-Type: application/json');

// Auth check
$is_student = isset($_SESSION['student_id']);
$is_admin   = in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']);

if (!$is_student && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── STUDENT: Get restricted files (not yet requested or approved) ──────────
if ($action === 'get_restricted_files') {
    if (!$is_student) {
        echo json_encode(['success' => false, 'message' => 'Student only']);
        exit;
    }

    $student_id = (int) $_SESSION['student_id'];

    // Get all forms that are restricted (not publicly downloadable)
    $stmt = $conn->prepare(
        "SELECT f.id, f.title, f.description, f.file_type, f.is_restricted,
                COALESCE(fr.status, 'not_requested') AS request_status,
                fr.id AS request_id, fr.approval_date,
                CASE 
                    WHEN f.is_restricted = 1 AND fr.status = 'approved' THEN 0
                    WHEN f.is_restricted = 1 AND COALESCE(fr.status, 'not_requested') != 'approved' THEN 1
                    ELSE 0
                END AS is_locked
         FROM downloadable_forms f
         LEFT JOIN file_requests fr ON f.id = fr.form_id AND fr.student_id = ?
         WHERE f.is_active = 1 AND f.is_restricted = 1
         ORDER BY f.title ASC"
    );

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB error']);
        exit;
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// ── STUDENT: Submit file request ────────────────────────────────────────────
if ($action === 'submit_file_request') {
    if (!$is_student) {
        echo json_encode(['success' => false, 'message' => 'Student only']);
        exit;
    }

    $student_id = (int) $_SESSION['student_id'];
    $form_id    = (int) ($_POST['form_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if (!$form_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid form ID']);
        exit;
    }

    // Check if form exists and is restricted
    $stmt = $conn->prepare("SELECT id, title, is_restricted FROM downloadable_forms WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $form_id);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$form) {
        echo json_encode(['success' => false, 'message' => 'Form not found']);
        exit;
    }

    if (!$form['is_restricted']) {
        echo json_encode(['success' => false, 'message' => 'This form is publicly available']);
        exit;
    }

    // Check if already has pending/approved request
    $stmt = $conn->prepare(
        "SELECT id, status FROM file_requests 
         WHERE student_id = ? AND form_id = ? AND status IN ('pending', 'approved')
         LIMIT 1"
    );
    $stmt->bind_param('ii', $student_id, $form_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        if ($existing['status'] === 'approved') {
            echo json_encode(['success' => false, 'message' => 'You already have access to this form']);
        } else {
            echo json_encode(['success' => false, 'message' => 'You already have a pending request for this form']);
        }
        exit;
    }

    // Insert request
    $stmt = $conn->prepare(
        "INSERT INTO file_requests (student_id, form_id, reason, status, request_date)
         VALUES (?, ?, ?, 'pending', NOW())"
    );
    $stmt->bind_param('iss', $student_id, $form_id, $reason);

    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Request submitted successfully',
            'request_id' => $request_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }

    $stmt->close();
    exit;
}

// ── STUDENT: Get request status for a form ──────────────────────────────────
if ($action === 'get_request_status') {
    if (!$is_student) {
        echo json_encode(['success' => false, 'message' => 'Student only']);
        exit;
    }

    $student_id = (int) $_SESSION['student_id'];
    $form_id    = (int) ($_GET['form_id'] ?? 0);

    if (!$form_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid form ID']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT id, status, approval_date, rejection_reason
         FROM file_requests
         WHERE student_id = ? AND form_id = ?
         ORDER BY request_date DESC
         LIMIT 1"
    );
    $stmt->bind_param('ii', $student_id, $form_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($request) {
        echo json_encode(['success' => true, 'request' => $request]);
    } else {
        echo json_encode(['success' => true, 'request' => null]);
    }
    exit;
}

// ── STUDENT: Download file (only if approved) ────────────────────────────────
if ($action === 'download_file') {
    if (!$is_student) {
        echo json_encode(['success' => false, 'message' => 'Student only']);
        exit;
    }

    $student_id = (int) $_SESSION['student_id'];
    $form_id    = (int) ($_GET['form_id'] ?? 0);

    if (!$form_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid form ID']);
        exit;
    }

    // Get form details
    $stmt = $conn->prepare(
        "SELECT id, title, file_path, file_type, is_restricted
         FROM downloadable_forms
         WHERE id = ? AND is_active = 1"
    );
    $stmt->bind_param('i', $form_id);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$form) {
        echo json_encode(['success' => false, 'message' => 'Form not found']);
        exit;
    }

    // If restricted, check if student has approval
    if ($form['is_restricted']) {
        $stmt = $conn->prepare(
            "SELECT id FROM file_requests
             WHERE student_id = ? AND form_id = ? AND status = 'approved'
             LIMIT 1"
        );
        $stmt->bind_param('ii', $student_id, $form_id);
        $stmt->execute();
        $approved = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$approved) {
            echo json_encode(['success' => false, 'message' => 'You do not have access to this form']);
            exit;
        }

        // Check if already downloaded (one-time download)
        $stmt = $conn->prepare(
            "SELECT id FROM file_downloads
             WHERE student_id = ? AND form_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $student_id, $form_id);
        $stmt->execute();
        $already_downloaded = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($already_downloaded) {
            echo json_encode(['success' => false, 'message' => 'You have already downloaded this file. Submit a new request to download again.']);
            exit;
        }
    }

    // Check file exists
    $file_path = '../../' . $form['file_path'];
    if (!file_exists($file_path)) {
        echo json_encode(['success' => false, 'message' => 'File not found on server']);
        exit;
    }

    // Log download
    $stmt = $conn->prepare(
        "INSERT INTO file_downloads (student_id, form_id, download_date)
         VALUES (?, ?, NOW())"
    );
    $stmt->bind_param('ii', $student_id, $form_id);
    $stmt->execute();
    $stmt->close();

    // Update download count
    $stmt = $conn->prepare(
        "UPDATE downloadable_forms SET download_count = download_count + 1 WHERE id = ?"
    );
    $stmt->bind_param('i', $form_id);
    $stmt->execute();
    $stmt->close();

    // Trigger download
    $filename = $form['title'] . '.' . pathinfo($form['file_path'], PATHINFO_EXTENSION);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

// ── ADMIN: Get all file requests ────────────────────────────────────────────
if ($action === 'get_all_requests') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $status_filter = trim($_GET['status'] ?? '');

    $sql = "SELECT fr.id, fr.student_id, fr.form_id, fr.reason, fr.status,
                   fr.request_date, fr.approval_date, fr.rejection_reason,
                   s.first_name, s.last_name, s.grade_level,
                   df.title as form_title
            FROM file_requests fr
            JOIN students s ON fr.student_id = s.id
            JOIN downloadable_forms df ON fr.form_id = df.id";

    if (!empty($status_filter)) {
        $sql .= " WHERE fr.status = '" . $conn->real_escape_string($status_filter) . "'";
    }

    $sql .= " ORDER BY fr.request_date DESC";

    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit;
    }

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $row['student_name'] = htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8');
        $row['form_title']   = htmlspecialchars($row['form_title'], ENT_QUOTES, 'UTF-8');
        $row['reason']       = htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8');
        $row['rejection_reason'] = htmlspecialchars($row['rejection_reason'] ?? '', ENT_QUOTES, 'UTF-8');
        $requests[] = $row;
    }

    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

// ── ADMIN: Process file request (approve/reject) ─────────────────────────────
if ($action === 'process_file_request') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $request_id = (int) ($_POST['request_id'] ?? 0);
    $decision   = trim($_POST['decision'] ?? ''); // 'approve' or 'reject'
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (!$request_id || !in_array($decision, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Get request details
    $stmt = $conn->prepare(
        "SELECT id, student_id, form_id, status FROM file_requests WHERE id = ?"
    );
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    if ($request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Request already processed']);
        exit;
    }

    // Update request
    $new_status = ($decision === 'approve') ? 'approved' : 'rejected';
    $approval_date = ($decision === 'approve') ? date('Y-m-d H:i:s') : null;

    if ($decision === 'approve') {
        $stmt = $conn->prepare(
            "UPDATE file_requests
             SET status = 'approved', approval_date = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('i', $request_id);
    } else {
        $stmt = $conn->prepare(
            "UPDATE file_requests
             SET status = 'rejected', rejection_reason = ?
             WHERE id = ?"
        );
        $stmt->bind_param('si', $rejection_reason, $request_id);
    }

    if ($stmt->execute()) {
        // Send notification message to student
        $conv_id = get_or_create_conversation($conn, $request['student_id'], 1);

        $notif_message = ($decision === 'approve')
            ? "✅ Your file request for form has been approved. You can now download it."
            : "❌ Your file request has been rejected. Reason: " . $rejection_reason;

        $stmt2 = $conn->prepare(
            "INSERT INTO chat_messages
             (conversation_id, sender_id, sender_role, message, message_type, is_read, created_at)
             VALUES (?, 1, 'admin', ?, 'file_request_response', 0, NOW())"
        );
        $stmt2->bind_param('is', $conv_id, $notif_message);
        $stmt2->execute();
        $stmt2->close();

        echo json_encode([
            'success' => true,
            'message' => 'Request ' . $new_status . ' successfully',
            'new_status' => $new_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process request']);
    }

    $stmt->close();
    exit;
}

// ── ADMIN: Get pending requests count ───────────────────────────────────────
if ($action === 'get_pending_count') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM file_requests WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'pending_count' => (int) $result['count']]);
    exit;
}

// ── Helper: Get or create conversation ──────────────────────────────────────
function get_or_create_conversation(mysqli $conn, int $student_id, int $admin_id = 1): int
{
    $s = $conn->prepare(
        "SELECT id FROM chat_conversations WHERE student_id = ? AND admin_id = ? LIMIT 1"
    );
    $s->bind_param('ii', $student_id, $admin_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if ($row) return (int) $row['id'];

    $s = $conn->prepare(
        "INSERT INTO chat_conversations (student_id, admin_id, last_message, updated_at)
         VALUES (?, ?, '', NOW())"
    );
    $s->bind_param('ii', $student_id, $admin_id);
    $s->execute();
    $id = (int) $conn->insert_id;
    $s->close();
    return $id;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
