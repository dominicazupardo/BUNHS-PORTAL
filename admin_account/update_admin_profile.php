<?php
// ═════════════════════════════════════════════════════════════
//  update_admin_profile.php
//  Handles AJAX POST to update the admin's public profile data.
//  Returns JSON: { success: bool, message: string, profile_image?: string }
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

// ── Sanitise & validate inputs ────────────────────────────────
function sanitise(string $v): string
{
    return trim(strip_tags($v));
}

$full_name        = sanitise($_POST['full_name']        ?? '');
$title            = sanitise($_POST['title']            ?? '');
$biography        = sanitise($_POST['biography']        ?? '');
$office_location  = sanitise($_POST['office_location']  ?? '');
$school_phone     = sanitise($_POST['school_phone']     ?? '');
$school_email     = sanitise($_POST['school_email']     ?? '');
$education_history = sanitise($_POST['education_history'] ?? '');
$certifications   = sanitise($_POST['certifications']   ?? '');
$years_experience = max(0, (int) ($_POST['years_experience'] ?? 0));
$twitter_url      = filter_var(trim($_POST['twitter_url']  ?? ''), FILTER_SANITIZE_URL);
$linkedin_url     = filter_var(trim($_POST['linkedin_url'] ?? ''), FILTER_SANITIZE_URL);
$responsibilities = sanitise($_POST['responsibilities']  ?? '');
$leadership_goals = sanitise($_POST['leadership_goals']  ?? '');

// Required field check
if ($full_name === '') {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}

// Email format check
if ($school_email !== '' && !filter_var($school_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid school email address.']);
    exit;
}

// ── Profile image upload ──────────────────────────────────────
$profile_image_name = null;   // null = no change

if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['profile_image'];
    $maxSize  = 2 * 1024 * 1024;   // 2 MB
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // Size check
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 2 MB.']);
        exit;
    }

    // MIME check using finfo (more reliable than $_FILES['type'])
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WebP, or GIF images are allowed.']);
        exit;
    }

    // Build a safe filename
    $ext                = pathinfo($file['name'], PATHINFO_EXTENSION);
    $profile_image_name = 'admin_' . $admin_id . '_' . time() . '.' . strtolower($ext);
    $uploadDir          = __DIR__ . '/uploads/admin_profiles/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $profile_image_name)) {
        echo json_encode(['success' => false, 'message' => 'Image upload failed. Please try again.']);
        exit;
    }

    // Delete old image (optional – keeps the uploads folder clean)
    $stmtOld = $conn->prepare("SELECT profile_image FROM admin WHERE id = ?");
    $stmtOld->bind_param('i', $admin_id);
    $stmtOld->execute();
    $oldRow = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();
    if (!empty($oldRow['profile_image'])) {
        $oldFile = $uploadDir . $oldRow['profile_image'];
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

// ── Build query (include image only when a new one was uploaded) ──
if ($profile_image_name !== null) {
    $sql = "UPDATE admin SET
                full_name        = ?,
                title            = ?,
                biography        = ?,
                office_location  = ?,
                school_phone     = ?,
                school_email     = ?,
                education_history= ?,
                certifications   = ?,
                years_experience = ?,
                twitter_url      = ?,
                linkedin_url     = ?,
                responsibilities = ?,
                leadership_goals = ?,
                profile_image    = ?,
                updated_at       = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssisssssi',
        $full_name,
        $title,
        $biography,
        $office_location,
        $school_phone,
        $school_email,
        $education_history,
        $certifications,
        $years_experience,
        $twitter_url,
        $linkedin_url,
        $responsibilities,
        $leadership_goals,
        $profile_image_name,
        $admin_id
    );
} else {
    $sql = "UPDATE admin SET
                full_name        = ?,
                title            = ?,
                biography        = ?,
                office_location  = ?,
                school_phone     = ?,
                school_email     = ?,
                education_history= ?,
                certifications   = ?,
                years_experience = ?,
                twitter_url      = ?,
                linkedin_url     = ?,
                responsibilities = ?,
                leadership_goals = ?,
                updated_at       = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssissssi',
        $full_name,
        $title,
        $biography,
        $office_location,
        $school_phone,
        $school_email,
        $education_history,
        $certifications,
        $years_experience,
        $twitter_url,
        $linkedin_url,
        $responsibilities,
        $leadership_goals,
        $admin_id
    );
}

if ($stmt->execute()) {
    $response = ['success' => true, 'message' => 'Profile updated successfully.'];
    if ($profile_image_name !== null) {
        $response['profile_image'] = $profile_image_name;
    }
    echo json_encode($response);
} else {
    error_log('update_admin_profile.php DB error: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}

$stmt->close();
$conn->close();
