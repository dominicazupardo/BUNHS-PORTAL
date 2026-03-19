<?php
// ═════════════════════════════════════════════════════════════
//  update_admin_private.php
//  Handles AJAX POST to update sensitive / private admin data.
//  Returns JSON: { success: bool, message: string }
// ═════════════════════════════════════════════════════════════
session_start();
header('Content-Type: application/json; charset=utf-8');

// ── Auth guard ────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

include 'db_connection.php';   // Provides $conn (mysqli)

$admin_id = (int) $_SESSION['user_id'];

// ── Sanitise inputs ───────────────────────────────────────────
function sanitise(string $v): string
{
    return trim(strip_tags($v));
}

$personal_mobile         = sanitise($_POST['personal_mobile']         ?? '');
$personal_email          = sanitise($_POST['personal_email']          ?? '');
$date_of_birth           = sanitise($_POST['date_of_birth']           ?? '');
$place_of_birth          = sanitise($_POST['place_of_birth']          ?? '');
$home_address            = sanitise($_POST['home_address']            ?? '');
$government_id           = sanitise($_POST['government_id']           ?? '');
$emergency_contact_name  = sanitise($_POST['emergency_contact_name']  ?? '');
$emergency_contact_phone = sanitise($_POST['emergency_contact_phone'] ?? '');
$emergency_relationship  = sanitise($_POST['emergency_relationship']  ?? '');
$bank_account            = sanitise($_POST['bank_account']            ?? '');

// ── Validate ──────────────────────────────────────────────────
if ($personal_email !== '' && !filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid personal email address.']);
    exit;
}

// Date of birth: must be a valid past date if provided
if ($date_of_birth !== '') {
    $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
    if (!$dob || $dob >= new DateTime()) {
        echo json_encode(['success' => false, 'message' => 'Invalid date of birth.']);
        exit;
    }
}

// ── Update database ───────────────────────────────────────────
$sql = "UPDATE admin SET
            personal_mobile          = ?,
            personal_email           = ?,
            date_of_birth            = ?,
            place_of_birth           = ?,
            home_address             = ?,
            government_id            = ?,
            emergency_contact_name   = ?,
            emergency_contact_phone  = ?,
            emergency_relationship   = ?,
            bank_account             = ?,
            updated_at               = NOW()
        WHERE id = ?";

$stmt = $conn->prepare($sql);

// date_of_birth stored as NULL when empty
$dob_value = $date_of_birth !== '' ? $date_of_birth : null;

$stmt->bind_param(
    'ssssssssssi',
    $personal_mobile,
    $personal_email,
    $dob_value,
    $place_of_birth,
    $home_address,
    $government_id,
    $emergency_contact_name,
    $emergency_contact_phone,
    $emergency_relationship,
    $bank_account,
    $admin_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Private information updated successfully.']);
} else {
    error_log('update_admin_private.php DB error: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}

$stmt->close();
$conn->close();
