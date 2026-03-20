<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  signup.php — CACHED VERSION (cache-invalidation patch)
//
//  Only the sections marked  ▼ CACHE PATCH ▼  change.
//  All existing functions (sendEmailOTP, sendSmsOTP, generateOTP, etc.)
//  and the HTML output are preserved exactly.
//
//  KEY CHANGES:
//    1. Add require_once 'cache_helper.php' near the top.
//    2. Cache the email-existence check (step "check_email" action).
//    3. Invalidate the email cache immediately after a new sub-admin is saved.
// ═══════════════════════════════════════════════════════════════════════════════

// PHPMailer namespace imports — must be at file scope
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

include 'db_connection.php';

// ▼ CACHE PATCH — load helper
require_once 'cache_helper.php';

// ── All your existing define() constants, helper functions, etc. go here ──────
// (sendEmailOTP, sendSmsOTP, generateOTP, isValidEmail, isValidPhone …)


// ══════════════════════════════════════════════════════════════════════════════
//  ▼ CACHE PATCH — Action handler with cache integration
//  Find the section in your signup.php that handles $_POST['action'] and
//  replace/augment it with the patterns below.
// ══════════════════════════════════════════════════════════════════════════════

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? '');


// ── ACTION: check_email ───────────────────────────────────────────────────────
// Called via AJAX as the user types their email in the signup form.
if ($action === 'check_email') {

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'invalid']);
        exit;
    }

    $cache_key = "email_exists:{$email}";

    // ── Try cache first ───────────────────────────────────────────────────────
    $cached = cache_get($cache_key);
    if ($cached !== false) {
        // CACHE HIT — 'exists' or 'available'
        echo json_encode(['status' => $cached]);
        exit;
    }

    // ── CACHE MISS — query DB ─────────────────────────────────────────────────
    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $status = $result->num_rows > 0 ? 'exists' : 'available';
    cache_set($cache_key, $status, CACHE_TTL_EMAIL_EXISTS);

    echo json_encode(['status' => $status]);
    exit;
}


// ── ACTION: send_otp ──────────────────────────────────────────────────────────
// Your existing send_otp logic — OTPs stay in $_SESSION (no DB, no cache needed)
if ($action === 'send_otp') {
    // ... your existing OTP generation and sending logic ...
    // OTPs live in $_SESSION['signup_otp_data'] — no APCu required here.
    exit;
}


// ── ACTION: verify_otp ───────────────────────────────────────────────────────
// Your existing verify_otp logic — reads from $_SESSION, no changes needed.
if ($action === 'verify_otp') {
    // ... your existing verification logic ...
    exit;
}


// ── ACTION: create_account ───────────────────────────────────────────────────
// ▼ CACHE PATCH — After a successful INSERT, invalidate the email cache.
if ($action === 'create_account') {

    // --- your existing validation, password hash, and INSERT logic here ---

    // Example final INSERT:
    $username = trim($_POST['username'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    // (your existing validation …)

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO `sub_admin` (username, email, password, status, created_at)
         VALUES (?, ?, ?, 'pending', NOW())"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $username, $email, $hashed);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            // ▼ CACHE PATCH — New sub-admin created: invalidate email cache
            // Without this, check_email.php would still report 'available'
            // for the next 5 minutes even though the slot is now taken.
            cache_delete("email_exists:{$email}");

            // Also pre-warm the sub-admin credential cache with the new row
            // (status = 'pending', so login will be blocked until approved)
            $new_id = $conn->insert_id;
            cache_set("subadmin:{$username}", [
                'id'       => $new_id,
                'password' => $hashed,
                'status'   => 'pending',    // ← will fail password_verify gate
            ], CACHE_TTL_CREDENTIALS);

            echo json_encode(['success' => true, 'message' => 'Account created. Awaiting admin approval.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
    exit;
}


echo json_encode(['success' => false, 'message' => 'Invalid action.']);
