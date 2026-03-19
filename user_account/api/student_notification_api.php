<?php
// ─────────────────────────────────────────────────────────────
// student_notification_api.php
// PHP 5.4+ compatible — no ?? operator, no type hints
// Fixed: db path resolution, missing-column safety, 500 errors
// ─────────────────────────────────────────────────────────────

function _sna_shutdown()
{
    $e = error_get_last();
    if ($e && ($e['type'] === E_ERROR   || $e['type'] === E_PARSE ||
        $e['type'] === E_CORE_ERROR || $e['type'] === E_COMPILE_ERROR)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Server error: ' . $e['message'] . ' (line ' . $e['line'] . ')'
        ));
    }
}
register_shutdown_function('_sna_shutdown');

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
session_start();

// ── DB: walk UP the directory tree until db_connection.php is found ──
// This file lives at: BUNHS_School_System/user_account/api/
// db_connection.php is at: BUNHS_School_System/
// So we need to climb 2 levels. The loop handles any depth safely.
$_db     = null;
$_search = __DIR__;
for ($_i = 0; $_i < 6; $_i++) {
    $_cand = $_search . '/db_connection.php';
    if (file_exists($_cand)) {
        $_db = $_cand;
        break;
    }
    $_parent = dirname($_search);
    if ($_parent === $_search) break;
    $_search = $_parent;
}

if (!$_db) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Cannot find db_connection.php. Searched from: ' . __DIR__
    ));
    exit;
}
include $_db;   // provides $conn (mysqli)

// ── Session guard ─────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Session expired. Please log in again.'));
    exit;
}

$studentId  = $_SESSION['student_id'];
$action     = isset($_POST['action'])     ? trim($_POST['action'])     : '';
$preference = isset($_POST['preference']) ? trim($_POST['preference']) : '';
$phone      = isset($_POST['phone'])      ? trim($_POST['phone'])      : '';
$email      = isset($_POST['email'])      ? trim($_POST['email'])      : '';

// ── Ensure all required columns exist (IF NOT EXISTS = zero cost if already there) ──
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS notification_preference VARCHAR(10)  DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone_verified          TINYINT(1)   DEFAULT 0");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS email_verified          TINYINT(1)   DEFAULT 0");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone                   VARCHAR(20)  DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS email                   VARCHAR(255) DEFAULT NULL");

// ─────────────────────────────────────────────────────────────
// ACTION: skip
// ─────────────────────────────────────────────────────────────
if ($action === 'skip') {
    $_SESSION['notif_dismissed'] = true;

    $stmt = $conn->prepare("UPDATE students SET notification_preference = 'none' WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(array('success' => true));
    exit;
}

// ─────────────────────────────────────────────────────────────
// ACTION: save_preference
// ─────────────────────────────────────────────────────────────
if ($action === 'save_preference') {

    if ($preference !== 'phone' && $preference !== 'email' && $preference !== 'both') {
        echo json_encode(array('success' => false, 'message' => 'Invalid notification preference.'));
        exit;
    }

    $phoneNeeded = ($preference === 'phone' || $preference === 'both');
    $emailNeeded = ($preference === 'email' || $preference === 'both');

    if ($phoneNeeded && $phone === '') {
        echo json_encode(array('success' => false, 'message' => 'Phone number is required.'));
        exit;
    }
    if ($emailNeeded && $email === '') {
        echo json_encode(array('success' => false, 'message' => 'Email address is required.'));
        exit;
    }
    if ($emailNeeded && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid email address format.'));
        exit;
    }

    $setParts  = array('notification_preference = ?', 'phone_verified = 0', 'email_verified = 0');
    $bindTypes = 's';
    $bindVals  = array($preference);

    if ($phoneNeeded && $phone !== '') {
        $setParts[]  = 'phone = ?';
        $bindTypes  .= 's';
        $bindVals[]  = $phone;
    }
    if ($emailNeeded && $email !== '') {
        $setParts[]  = 'email = ?';
        $bindTypes  .= 's';
        $bindVals[]  = $email;
    }

    $bindTypes  .= 's';
    $bindVals[]  = $studentId;

    $sql  = 'UPDATE students SET ' . implode(', ', $setParts) . ' WHERE student_id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(array('success' => false, 'message' => 'DB prepare error: ' . $conn->error));
        exit;
    }

    $args = array($bindTypes);
    foreach ($bindVals as $k => $v) {
        $args[] = &$bindVals[$k];
    }
    call_user_func_array(array($stmt, 'bind_param'), $args);

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['notification_preference'] = $preference;
        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Failed to save preference. Please try again.'));
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// ACTION: get_preference
// ─────────────────────────────────────────────────────────────
if ($action === 'get_preference') {
    // All columns are guaranteed to exist from the ALTER TABLE block above
    $stmt = $conn->prepare(
        "SELECT notification_preference, phone, email, phone_verified, email_verified
         FROM students WHERE student_id = ? LIMIT 1"
    );
    if (!$stmt) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $conn->error));
        exit;
    }
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        echo json_encode(array(
            'success'        => true,
            'preference'     => $row['notification_preference'],
            'phone'          => $row['phone'],
            'email'          => $row['email'],
            'phone_verified' => (bool)$row['phone_verified'],
            'email_verified' => (bool)$row['email_verified']
        ));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Student not found.'));
    }
    exit;
}

echo json_encode(array('success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)));
