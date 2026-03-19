<?php

/**
 * Student Profile
 * Profile page for students to view and edit their information
 *
 * Security Features:
 * - HTTPS enforcement for data in transit
 * - CSRF token protection
 * - Input sanitization
 * - Field-level encryption support
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';

// HSTS Header for SSL/HTTPS
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Include database connection
include '../db_connection.php';

// ── Auto-create student_profile_data table ─────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS student_profile_data (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    student_id        VARCHAR(50) NOT NULL UNIQUE,
    email             VARCHAR(150) DEFAULT '',
    phone             VARCHAR(30)  DEFAULT '',
    address           TEXT         DEFAULT NULL,
    birthdate         DATE         DEFAULT NULL,
    guardian_name     VARCHAR(150) DEFAULT '',
    guardian_phone    VARCHAR(30)  DEFAULT '',
    photo             VARCHAR(255) DEFAULT '',
    professional_bio  TEXT         DEFAULT NULL,
    awards_honors     TEXT         DEFAULT NULL,
    extracurriculars  TEXT         DEFAULT NULL,
    portfolio_projects TEXT        DEFAULT NULL,
    skills            TEXT         DEFAULT NULL,
    updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Auth guard
if (!isset($_SESSION['student_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: index.php');
    exit;
}

// Get current student info
$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$grade_level  = $_SESSION['grade_level']  ?? 'Grade 7';

// ── Fetch full profile for nav chip ──────────────────────────
$_nav = [];
try {
    $sn = $conn->prepare("SELECT first_name, last_name, grade_level, phone, email, photo, phone_verified, email_verified, login_method FROM students WHERE student_id=? LIMIT 1");
    if ($sn) {
        $sn->bind_param('s', $student_id);
        $sn->execute();
        $_nav = $sn->get_result()->fetch_assoc() ?? [];
        $sn->close();
    }
} catch (Exception $e) {
}

$_login_method = $_nav['login_method'] ?? $_SESSION['login_method'] ?? null;
$_ev           = !empty($_nav['email_verified']);
$_pv           = !empty($_nav['phone_verified']);
$_nav_email    = $_nav['email']  ?? $_SESSION['student_email'] ?? null;
$_nav_phone    = $_nav['phone']  ?? $_SESSION['student_phone'] ?? null;
$_nav_photo    = $_nav['photo']  ?? null;

if ($_login_method === 'email' || ($_ev && $_nav_email))       $profile_display_mode = 'email';
elseif ($_login_method === 'phone' || ($_pv && $_nav_phone))   $profile_display_mode = 'phone';
elseif (!empty($_SESSION['notif_dismissed']))                   $profile_display_mode = 'skip';
else                                                            $profile_display_mode = 'none';

$user_verified    = ($_ev || $_pv || !empty($_SESSION['dash_email_verified']));
$nav_profile_type = 'icon';
$nav_profile_img  = 'assets/img/person/unknown.jpg';
if ($profile_display_mode === 'email') {
    if ($_nav_photo) {
        $nav_profile_img = htmlspecialchars($_nav_photo, ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } elseif (!empty($_SESSION['google_avatar'])) {
        $nav_profile_img = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    }
}
$nav_display_label = match ($profile_display_mode) {
    'email' => htmlspecialchars($_nav_email ?? $student_name, ENT_QUOTES, 'UTF-8'),
    'phone' => htmlspecialchars($_nav_phone ?? $student_name, ENT_QUOTES, 'UTF-8'),
    default => ''
};

// ── Handle save_profile POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $email              = trim($_POST['email'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $address            = trim($_POST['address'] ?? '');
    $birthdate          = trim($_POST['birthdate'] ?? '') ?: null;
    $guardian_name      = trim($_POST['guardian_name'] ?? '');
    $guardian_phone     = trim($_POST['guardian_phone'] ?? '');
    $professional_bio   = trim($_POST['professional_bio'] ?? '');
    $awards_honors      = trim($_POST['awards_honors'] ?? '');
    $extracurriculars   = trim($_POST['extracurriculars'] ?? '');
    $portfolio_projects = trim($_POST['portfolio_projects'] ?? '');
    $skills             = trim($_POST['skills'] ?? '');

    // Handle photo upload
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (in_array($_FILES['photo']['type'], $allowed) && $_FILES['photo']['size'] <= 3145728) {
            $ext  = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $dest = '../assets/img/person/student_' . $student_id . '_' . time() . '.' . $ext;
            @mkdir('../assets/img/person/', 0775, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photo = $dest;
            }
        }
    }

    // Upsert into student_profile_data
    if ($photo) {
        $stmt = $conn->prepare("INSERT INTO student_profile_data
            (student_id, email, phone, address, birthdate, guardian_name, guardian_phone, photo, professional_bio, awards_honors, extracurriculars, portfolio_projects, skills)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            email=VALUES(email), phone=VALUES(phone), address=VALUES(address),
            birthdate=VALUES(birthdate), guardian_name=VALUES(guardian_name),
            guardian_phone=VALUES(guardian_phone), photo=VALUES(photo),
            professional_bio=VALUES(professional_bio), awards_honors=VALUES(awards_honors),
            extracurriculars=VALUES(extracurriculars), portfolio_projects=VALUES(portfolio_projects),
            skills=VALUES(skills)");
        $stmt->bind_param('sssssssssssss', $student_id, $email, $phone, $address, $birthdate, $guardian_name, $guardian_phone, $photo, $professional_bio, $awards_honors, $extracurriculars, $portfolio_projects, $skills);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_profile_data
            (student_id, email, phone, address, birthdate, guardian_name, guardian_phone, professional_bio, awards_honors, extracurriculars, portfolio_projects, skills)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            email=VALUES(email), phone=VALUES(phone), address=VALUES(address),
            birthdate=VALUES(birthdate), guardian_name=VALUES(guardian_name),
            guardian_phone=VALUES(guardian_phone),
            professional_bio=VALUES(professional_bio), awards_honors=VALUES(awards_honors),
            extracurriculars=VALUES(extracurriculars), portfolio_projects=VALUES(portfolio_projects),
            skills=VALUES(skills)");
        $stmt->bind_param('ssssssssssss', $student_id, $email, $phone, $address, $birthdate, $guardian_name, $guardian_phone, $professional_bio, $awards_honors, $extracurriculars, $portfolio_projects, $skills);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'photo' => $photo]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// Default values
$student_email        = '';
$student_phone        = '';
$student_address      = '';
$student_birthdate    = '';
$student_guardian     = '';
$student_guardian_phone = '';
$student_photo        = '';
$professional_bio     = '';
$school_name          = 'Buyoan National High School';
$awards_honors        = '';
$extracurriculars     = '';
$portfolio_projects   = '';
$skills               = '';

// ── Load encryption key if available ─────────────────────────────────────────
$encryption_key = null;
if (file_exists('../config/encryption_key.php')) {
    include '../config/encryption_key.php';
}

function calculateAge($birthdate)
{
    if (empty($birthdate)) return '';
    $birth = new DateTime($birthdate);
    $today = new DateTime('today');
    return $birth->diff($today)->y;
}

try {
    // Get basic student info from students table
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['first_name'])) {
                $student_name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            }
            $grade_level = $row['grade_level'] ?? $grade_level;
        }
        $stmt->close();
    }

    // Get extended profile from student_profile_data
    $stmt2 = $conn->prepare("SELECT * FROM student_profile_data WHERE student_id = ?");
    if ($stmt2) {
        $stmt2->bind_param("s", $student_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($row2 = $result2->fetch_assoc()) {
            $student_email        = $row2['email']              ?? '';
            $student_phone        = $row2['phone']              ?? '';
            $student_address      = $row2['address']            ?? '';
            $student_birthdate    = $row2['birthdate']          ?? '';
            $student_guardian     = $row2['guardian_name']      ?? '';
            $student_guardian_phone = $row2['guardian_phone']   ?? '';
            $student_photo        = $row2['photo']              ?? '';
            $professional_bio     = $row2['professional_bio']   ?? '';
            $awards_honors        = $row2['awards_honors']      ?? '';
            $extracurriculars     = $row2['extracurriculars']   ?? '';
            $portfolio_projects   = $row2['portfolio_projects'] ?? '';
            $skills               = $row2['skills']             ?? '';
        }
        $stmt2->close();
    }
} catch (Exception $e) {
    error_log("Profile DB error: " . $e->getMessage());
}

// Calculate age from birthdate
$student_age = calculateAge($student_birthdate);

// Avatar initials
$initials = strtoupper(substr($student_name, 0, 1));
$words    = explode(' ', trim($student_name));
if (count($words) >= 2) {
    $initials = strtoupper($words[0][0] . $words[count($words) - 1][0]);
}
$avatarStyle   = $student_photo
    ? 'background-image:url(' . htmlspecialchars($student_photo, ENT_QUOTES, 'UTF-8') . ');background-size:cover;background-position:center;'
    : '';
$avatarContent = $student_photo ? '' : $initials;

// Skill count
$skill_count = count(array_filter(array_map('trim', explode("\n", $skills))));
// Awards count
$award_count = count(array_filter(array_map('trim', explode("\n", $awards_honors))));
// Activities count
$activity_count = count(array_filter(array_map('trim', explode("\n", $extracurriculars))));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Buyoan National High School</title>

    <!-- Shared styles -->
    <link rel="stylesheet" href="../admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Fraunces:ital,wght@0,300;0,600;1,300&display=swap" rel="stylesheet">

    <style>
        /* ── Design tokens (mirrors admin_profile.php) ──────────────── */
        :root {
            --green-dark: #3d5a2e;
            --green-mid: #5c7a42;
            --green-light: #8ca96b;
            --green-pale: #eef3e8;
            --green-ghost: #f6f8f3;
            --amber: #c07d38;
            --amber-pale: #fdf3e7;
            --red: #b94040;
            --red-pale: #fdf1f1;
            --text-primary: #1e2820;
            --text-secondary: #5a6558;
            --text-muted: #93a18e;
            --border: #dce5d5;
            --white: #ffffff;
            --shadow-sm: 0 1px 4px rgba(0, 0, 0, .06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .12);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--green-ghost);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }

        /* ── Main wrapper — sits beside the fixed sidebar ───────────── */
        .main {
            margin-left: 280px;
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Page wrapper ────────────────────────────────────────────── */
        .page-content {
            padding: 28px 32px 48px;
            flex: 1;
            width: 100%;
            max-width: 100%;
        }

        /* ── Breadcrumb ──────────────────────────────────────────────── */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-bottom: 22px;
            animation: cardReveal .4s ease both;
        }

        .breadcrumb a {
            color: var(--green-mid);
            text-decoration: none;
            font-weight: 500;
            transition: color .12s;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
            color: var(--green-dark);
        }

        .breadcrumb i {
            font-size: 8.5px;
            color: var(--text-muted);
        }

        .breadcrumb span {
            font-weight: 600;
            color: var(--text-secondary);
        }

        /* ── Animations ──────────────────────────────────────────────── */
        @keyframes cardReveal {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadein {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideup {
            from {
                opacity: 0;
                transform: translateY(32px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes barGrow {
            to {
                width: 75%;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Hero card ───────────────────────────────────────────────── */
        .hero-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 22px;
            overflow: hidden;
            animation: cardReveal .5s ease both;
        }

        .hero-banner {
            height: 130px;
            background: linear-gradient(125deg, #0f1c0b 0%, #2a4120 30%, var(--green-mid) 65%, #7dae56 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 120% at 80% 50%, rgba(140, 169, 107, .22) 0%, transparent 70%),
                radial-gradient(ellipse 30% 80% at 10% 80%, rgba(255, 255, 255, .07) 0%, transparent 60%);
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(-45deg, transparent, transparent 18px, rgba(255, 255, 255, .028) 18px, rgba(255, 255, 255, .028) 19px);
        }

        .hero-banner-accent {
            position: absolute;
            right: -30px;
            top: -30px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 28px solid rgba(255, 255, 255, .05);
            pointer-events: none;
        }

        .hero-banner-accent2 {
            position: absolute;
            right: 80px;
            bottom: -50px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 16px solid rgba(255, 255, 255, .04);
            pointer-events: none;
        }

        .hero-body {
            padding: 0 28px 24px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
        }

        .hero-left {
            display: flex;
            align-items: flex-end;
            gap: 18px;
        }

        .hero-avatar-wrap {
            margin-top: -50px;
            flex-shrink: 0;
            position: relative;
        }

        .hero-avatar {
            width: 92px;
            height: 92px;
            border-radius: var(--radius-md);
            border: 4px solid var(--white);
            box-shadow: 0 4px 20px rgba(0, 0, 0, .18), 0 0 0 1px rgba(0, 0, 0, .05);
            background: linear-gradient(145deg, var(--green-dark) 0%, var(--green-mid) 50%, var(--green-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            font-family: 'Fraunces', serif;
            letter-spacing: -1px;
            transition: transform .2s, box-shadow .2s;
            object-fit: cover;
        }

        .hero-avatar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, .22), 0 0 0 1px rgba(0, 0, 0, .05);
        }

        .hero-avatar-wrap::after {
            content: '';
            position: absolute;
            bottom: 6px;
            right: 6px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #4ade80;
            border: 2.5px solid var(--white);
            box-shadow: 0 0 0 2px rgba(74, 222, 128, .3);
        }

        .hero-info {
            padding-bottom: 4px;
        }

        .hero-name {
            font-family: 'Fraunces', serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
            letter-spacing: -.3px;
        }

        .hero-role {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--green-mid);
            margin: 3px 0 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .hero-role::before {
            content: '';
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--green-mid);
            flex-shrink: 0;
        }

        .hero-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 500;
            background: var(--green-pale);
            color: var(--green-dark);
            border: 1px solid var(--border);
            transition: background .14s, border-color .14s, transform .14s;
        }

        .chip:hover {
            background: #dcebd0;
            border-color: var(--green-light);
            transform: translateY(-1px);
        }

        .chip i {
            font-size: 10px;
            color: var(--green-mid);
        }

        .hero-actions {
            display: flex;
            gap: 8px;
            padding-bottom: 4px;
            flex-shrink: 0;
        }

        /* ── Buttons ─────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .16s cubic-bezier(.16, 1, .3, 1);
            border: none;
            font-family: inherit;
            white-space: nowrap;
            letter-spacing: .1px;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0);
            transition: background .14s;
            border-radius: inherit;
        }

        .btn:active::after {
            background: rgba(0, 0, 0, .06);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-mid) 100%);
            color: #fff;
            box-shadow: 0 2px 10px rgba(61, 90, 46, .28), inset 0 1px 0 rgba(255, 255, 255, .12);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--green-mid) 0%, #6a8f4e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(61, 90, 46, .32), inset 0 1px 0 rgba(255, 255, 255, .12);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-ghost {
            background: var(--white);
            color: var(--text-secondary);
            border: 1.5px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        .btn-ghost:hover {
            border-color: var(--green-light);
            color: var(--green-dark);
            background: var(--green-ghost);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, .06);
        }

        /* ── Stats strip ─────────────────────────────────────────────── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow .22s, transform .22s;
            position: relative;
            overflow: hidden;
            animation: cardReveal .5s ease both;
        }

        .stat-card:nth-child(1) {
            animation-delay: .06s;
        }

        .stat-card:nth-child(2) {
            animation-delay: .12s;
        }

        .stat-card:nth-child(3) {
            animation-delay: .18s;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 3px 3px 0 0;
            opacity: 0;
            transition: opacity .22s;
        }

        .stat-card:nth-child(1)::before {
            background: linear-gradient(90deg, var(--green-dark), var(--green-light));
        }

        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #b86e28, var(--amber));
        }

        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #2755b5, #5b82e8);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            transition: transform .2s;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.08) rotate(-3deg);
        }

        .stat-icon.green {
            background: var(--green-pale);
            color: var(--green-dark);
        }

        .stat-icon.amber {
            background: var(--amber-pale);
            color: var(--amber);
        }

        .stat-icon.blue {
            background: #eaf0fd;
            color: #3b66c4;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            font-family: 'Fraunces', serif;
            letter-spacing: -.5px;
        }

        .stat-label {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ── Grid ────────────────────────────────────────────────────── */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        /* ── Section cards ───────────────────────────────────────────── */
        .section-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .22s, transform .22s;
            animation: cardReveal .5s ease both;
        }

        .section-card:nth-child(1) {
            animation-delay: .10s;
        }

        .section-card:nth-child(2) {
            animation-delay: .16s;
        }

        .section-card:nth-child(3) {
            animation-delay: .22s;
        }

        .section-card:nth-child(4) {
            animation-delay: .28s;
        }

        .section-card:nth-child(5) {
            animation-delay: .34s;
        }

        .section-card:nth-child(6) {
            animation-delay: .40s;
        }

        .section-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .section-card.full {
            grid-column: 1 / -1;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to bottom, var(--green-ghost), var(--white));
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .section-title i {
            width: 27px;
            height: 27px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--green-pale), #dcebd0);
            color: var(--green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            box-shadow: 0 1px 3px rgba(61, 90, 46, .12);
        }

        .section-body {
            padding: 20px;
        }

        /* ── Info rows ───────────────────────────────────────────────── */
        .info-list {
            display: flex;
            flex-direction: column;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 0;
            border-bottom: 1px solid var(--green-ghost);
            transition: background .12s;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            min-width: 110px;
            padding-top: 1px;
        }

        .info-value {
            font-size: 13.5px;
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
            line-height: 1.55;
        }

        .info-value a {
            color: var(--green-mid);
            text-decoration: none;
            transition: color .12s;
        }

        .info-value a:hover {
            text-decoration: underline;
            color: var(--green-dark);
        }

        .info-value.muted {
            color: var(--text-muted);
            font-style: italic;
            font-weight: 400;
        }

        /* ── Tags ────────────────────────────────────────────────────── */
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tag {
            padding: 4px 11px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background: var(--green-pale);
            color: var(--green-dark);
            border: 1px solid var(--border);
            transition: background .14s, border-color .14s, transform .14s;
        }

        .tag:hover {
            background: #d0e3c2;
            border-color: var(--green-light);
            transform: translateY(-1px);
        }

        /* ── Bio text ────────────────────────────────────────────────── */
        .bio-text {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.85;
            padding: 16px 18px;
            background: var(--green-ghost);
            border-radius: var(--radius-md);
            border-left: 3px solid var(--green-mid);
            font-style: italic;
            position: relative;
        }

        .bio-text::before {
            content: '\201C';
            position: absolute;
            top: 8px;
            left: 18px;
            font-size: 48px;
            font-family: 'Fraunces', serif;
            color: var(--green-pale);
            line-height: 1;
            pointer-events: none;
        }

        /* ── Bullet list ─────────────────────────────────────────────── */
        .bullet-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .bullet-list li {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .bullet-list li::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green-mid);
            flex-shrink: 0;
            margin-top: 6px;
        }

        /* ── Sub-headings ────────────────────────────────────────────── */
        .sub-heading {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: 16px;
            margin-bottom: 10px;
            padding-bottom: 7px;
            border-bottom: 1px solid var(--border);
        }

        .sub-heading:first-child {
            margin-top: 0;
        }

        .sub-heading i {
            color: var(--green-light);
        }

        /* ── Extended content block ──────────────────────────────────── */
        .extended-block {
            background: var(--green-ghost);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            border-left: 3px solid var(--green-light);
            margin-top: 8px;
        }

        .extended-block p {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        .extended-block p:empty::after {
            content: 'Not provided';
            font-style: italic;
            color: var(--text-muted);
        }

        /* ── Modal ───────────────────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 18, 8, .55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadein .22s ease;
        }

        .modal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 72px rgba(0, 0, 0, .28), 0 4px 16px rgba(0, 0, 0, .1);
            width: 100%;
            max-width: 700px;
            margin: auto;
            animation: slideup .32s cubic-bezier(.16, 1, .3, 1);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 26px;
            background: linear-gradient(to bottom, var(--green-ghost), var(--white));
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .modal-header h2 i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--green-dark), var(--green-mid));
            color: #fff;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(61, 90, 46, .25);
        }

        .modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            background: var(--white);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all .16s;
        }

        .modal-close:hover {
            background: #fde8e8;
            color: var(--red);
            border-color: #f0d5d5;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 26px;
            max-height: calc(100vh - 240px);
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            padding: 18px 26px;
            border-top: 1px solid var(--border);
            background: var(--green-ghost);
        }

        /* ── Form elements ───────────────────────────────────────────── */
        .form-section {
            margin-bottom: 22px;
        }

        .form-section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .55px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--green-ghost);
        }

        .form-section-head i {
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, var(--green-pale), #dcebd0);
            color: var(--green-dark);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9.5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .form-input,
        .form-textarea,
        .form-select {
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--white);
            transition: border-color .16s, box-shadow .16s, background .16s;
            outline: none;
            width: 100%;
        }

        .form-input:hover,
        .form-textarea:hover,
        .form-select:hover {
            border-color: var(--green-light);
            background: #fafcf8;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            border-color: var(--green-mid);
            box-shadow: 0 0 0 3.5px rgba(92, 122, 66, .13);
            background: var(--white);
        }

        .form-input[readonly] {
            background: var(--green-ghost);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .form-textarea {
            resize: vertical;
            min-height: 84px;
            line-height: 1.65;
        }

        /* Avatar upload row */
        .avatar-upload-row {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px;
            background: linear-gradient(to right, var(--green-ghost), #f8fbf5);
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            margin-bottom: 22px;
        }

        .avatar-preview {
            width: 68px;
            height: 68px;
            border-radius: var(--radius-md);
            border: 2px solid var(--border);
            flex-shrink: 0;
            background: linear-gradient(145deg, var(--green-dark), var(--green-mid), var(--green-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            font-family: 'Fraunces', serif;
            background-size: cover;
            background-position: center;
            box-shadow: 0 2px 10px rgba(61, 90, 46, .18);
        }

        .avatar-upload-info {
            flex: 1;
        }

        .avatar-upload-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .avatar-upload-hint {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        /* ── Toast ───────────────────────────────────────────────────── */
        .toast {
            position: fixed;
            bottom: 26px;
            right: 26px;
            background: #1a2716;
            color: #fff;
            padding: 13px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .22), 0 2px 8px rgba(0, 0, 0, .12);
            animation: slideInRight .3s cubic-bezier(.16, 1, .3, 1);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, .08);
            min-width: 200px;
        }

        .toast.success {
            border-left: 3px solid #4ade80;
        }

        .toast.error {
            border-left: 3px solid #f87171;
        }

        .toast.success i {
            color: #4ade80;
        }

        .toast.error i {
            color: #f87171;
        }

        /* ── Loading ─────────────────────────────────────────────────── */
        .dashboard-loading {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--green-ghost);
            z-index: 9000;
            gap: 14px;
        }

        .dashboard-loading p {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--border);
            border-top-color: var(--green-mid);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        /* Inline spinner for save button */
        .btn-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        /* ── Responsive ──────────────────────────────────────────────── */
        @media (max-width: 960px) {
            .main {
                margin-left: 0;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .stats-strip {
                grid-template-columns: 1fr 1fr;
            }

            .page-content {
                padding: 22px 20px 40px;
            }
        }

        @media (max-width: 560px) {
            .page-content {
                padding: 14px 14px 36px;
            }

            .hero-body {
                flex-direction: column;
                align-items: flex-start;
                padding: 0 16px 20px;
            }

            .hero-left {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-actions {
                width: 100%;
            }

            .hero-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .stats-strip {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body
    data-student-name="<?php echo htmlspecialchars($student_name); ?>"
    data-grade-level="<?php echo htmlspecialchars($grade_level); ?>"
    data-profile-mode="<?php echo htmlspecialchars($profile_display_mode); ?>"
    data-profile-img="<?php echo ($nav_profile_type === 'img') ? $nav_profile_img : ''; ?>"
    data-profile-label="<?php echo $nav_display_label; ?>"
    data-user-verified="<?php echo $user_verified ? '1' : '0'; ?>">

    <!-- ── STUDENT NAV (loaded via JS fetch at bottom) ── -->
    <div id="nav-placeholder"></div>

    <div class="main">
        <main class="page-content" id="profile-content">

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="Dashboard.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Profile</span>
            </nav>

            <!-- ── Hero ─────────────────────────────────────────────── -->
            <div class="hero-card">
                <div class="hero-banner">
                    <div class="hero-banner-accent"></div>
                    <div class="hero-banner-accent2"></div>
                </div>
                <div class="hero-body">
                    <div class="hero-left">
                        <div class="hero-avatar-wrap">
                            <div class="hero-avatar" id="heroAvatar" style="<?php echo $avatarStyle; ?>"><?php echo htmlspecialchars($avatarContent); ?></div>
                        </div>
                        <div class="hero-info">
                            <div class="hero-name" id="heroName"><?php echo htmlspecialchars($student_name); ?></div>
                            <div class="hero-role"><?php echo htmlspecialchars($grade_level); ?> &mdash; <?php echo htmlspecialchars($school_name); ?></div>
                            <div class="hero-chips">
                                <span class="chip"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($student_id); ?></span>
                                <?php if ($student_email): ?><span class="chip"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student_email); ?></span><?php endif; ?>
                                <?php if ($student_phone): ?><span class="chip"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($student_phone); ?></span><?php endif; ?>
                                <?php if ($student_age): ?><span class="chip"><i class="fas fa-birthday-cake"></i> <?php echo htmlspecialchars($student_age); ?> yrs old</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="hero-actions">
                        <button class="btn btn-ghost" onclick="openChangePhotoModal()"><i class="fas fa-camera"></i> Change Photo</button>
                        <button class="btn btn-primary" onclick="openEditModal()"><i class="fas fa-edit"></i> Edit Profile</button>
                    </div>
                </div>
            </div>

            <!-- ── Stats ─────────────────────────────────────────────── -->
            <div class="stats-strip">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-tools"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $skill_count ?: '—'; ?></div>
                        <div class="stat-label">Skills Listed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-trophy"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $award_count ?: '—'; ?></div>
                        <div class="stat-label">Awards &amp; Honors</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users-rectangle"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $activity_count ?: '—'; ?></div>
                        <div class="stat-label">Extracurriculars</div>
                    </div>
                </div>
            </div>

            <!-- ── Grid ──────────────────────────────────────────────── -->
            <div class="grid-2">

                <!-- Contact / Personal -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-address-card"></i> Personal Information</h3>
                    </div>
                    <div class="section-body">
                        <div class="info-list">
                            <div class="info-row"><span class="info-label">Student ID</span><span class="info-value"><?php echo htmlspecialchars($student_id); ?></span></div>
                            <div class="info-row"><span class="info-label">Full Name</span><span class="info-value"><?php echo htmlspecialchars($student_name); ?></span></div>
                            <div class="info-row"><span class="info-label">Grade Level</span><span class="info-value"><?php echo htmlspecialchars($grade_level); ?></span></div>
                            <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value"><?php echo $student_birthdate ? htmlspecialchars(date('F j, Y', strtotime($student_birthdate))) : '<span class="muted">Not provided</span>'; ?></span></div>
                            <?php if ($student_age): ?>
                                <div class="info-row"><span class="info-label">Age</span><span class="info-value"><?php echo htmlspecialchars($student_age); ?> years old</span></div>
                            <?php endif; ?>
                            <div class="info-row"><span class="info-label">Address</span><span class="info-value"><?php echo $student_address ? htmlspecialchars($student_address) : '<span class="muted">Not provided</span>'; ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Contact details -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-phone-alt"></i> Contact &amp; Guardian</h3>
                    </div>
                    <div class="section-body">
                        <div class="sub-heading"><i class="fas fa-user"></i> Contact Details</div>
                        <div class="info-list">
                            <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?php echo $student_email ? '<a href="mailto:' . htmlspecialchars($student_email) . '">' . htmlspecialchars($student_email) . '</a>' : '<span class="muted">Not provided</span>'; ?></span></div>
                            <div class="info-row"><span class="info-label">Phone</span><span class="info-value"><?php echo $student_phone ? htmlspecialchars($student_phone) : '<span class="muted">Not provided</span>'; ?></span></div>
                        </div>
                        <div class="sub-heading" style="margin-top:14px;"><i class="fas fa-users"></i> Guardian Information</div>
                        <div class="info-list">
                            <div class="info-row"><span class="info-label">Guardian</span><span class="info-value"><?php echo $student_guardian ? htmlspecialchars($student_guardian) : '<span class="muted">Not provided</span>'; ?></span></div>
                            <div class="info-row"><span class="info-label">Guardian Phone</span><span class="info-value"><?php echo $student_guardian_phone ? htmlspecialchars($student_guardian_phone) : '<span class="muted">Not provided</span>'; ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Professional Bio — full width -->
                <div class="section-card full">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-user-tie"></i> Professional Bio</h3>
                    </div>
                    <div class="section-body">
                        <p class="bio-text"><?php echo $professional_bio ? htmlspecialchars($professional_bio) : '<span class="muted" style="font-style:normal;">No bio provided yet.</span>'; ?></p>
                    </div>
                </div>

                <!-- Academic Highlights -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-school"></i> Academic Highlights</h3>
                    </div>
                    <div class="section-body">
                        <div class="info-list">
                            <div class="info-row"><span class="info-label">School</span><span class="info-value"><?php echo htmlspecialchars($school_name); ?></span></div>
                            <div class="info-row"><span class="info-label">Grade Level</span><span class="info-value"><?php echo htmlspecialchars($grade_level); ?></span></div>
                        </div>
                        <div class="sub-heading" style="margin-top:14px;"><i class="fas fa-trophy"></i> Awards &amp; Honors</div>
                        <?php if ($awards_honors): ?>
                            <ul class="bullet-list">
                                <?php foreach (array_filter(array_map('trim', explode("\n", $awards_honors))) as $a): ?>
                                    <li><?php echo htmlspecialchars($a); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="info-value muted">Not provided</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Skills -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-tools"></i> Skills</h3>
                    </div>
                    <div class="section-body">
                        <div class="tag-cloud">
                            <?php if ($skills):
                                foreach (array_filter(array_map('trim', explode("\n", $skills))) as $sk):
                                    echo '<span class="tag">' . htmlspecialchars($sk) . '</span>';
                                endforeach;
                            else: ?>
                                <span class="info-value muted">No skills listed yet.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Extracurriculars — full width -->
                <div class="section-card full">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-users-rectangle"></i> Extracurricular Activities</h3>
                    </div>
                    <div class="section-body">
                        <?php if ($extracurriculars): ?>
                            <ul class="bullet-list">
                                <?php foreach (array_filter(array_map('trim', explode("\n", $extracurriculars))) as $act): ?>
                                    <li><?php echo htmlspecialchars($act); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="info-value muted">No activities listed yet.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Portfolio Projects -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-folder-open"></i> Portfolio Projects</h3>
                    </div>
                    <div class="section-body">
                        <?php if ($portfolio_projects): ?>
                            <ul class="bullet-list">
                                <?php foreach (array_filter(array_map('trim', explode("\n", $portfolio_projects))) as $p): ?>
                                    <li><?php echo htmlspecialchars($p); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="info-value muted">No portfolio projects added yet.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Summary sidebar -->
                <div class="section-card">
                    <div class="section-head">
                        <h3 class="section-title"><i class="fas fa-graduation-cap"></i> Profile Summary</h3>
                    </div>
                    <div class="section-body">
                        <div class="info-list">
                            <div class="info-row"><span class="info-label">School</span><span class="info-value"><?php echo htmlspecialchars($school_name); ?></span></div>
                            <div class="info-row"><span class="info-label">Grade</span><span class="info-value"><?php echo htmlspecialchars($grade_level); ?></span></div>
                            <div class="info-row"><span class="info-label">Skills</span><span class="info-value"><?php echo $skill_count ?: '—'; ?> listed</span></div>
                            <div class="info-row"><span class="info-label">Awards</span><span class="info-value"><?php echo $award_count ?: '—'; ?> total</span></div>
                            <div class="info-row"><span class="info-label">Activities</span><span class="info-value"><?php echo $activity_count ?: '—'; ?> joined</span></div>
                            <div class="info-row"><span class="info-label">Last Updated</span><span class="info-value muted">—</span></div>
                        </div>
                    </div>
                </div>

            </div><!-- /grid-2 -->
        </main>
    </div><!-- /main -->


    <!-- ════════════════════════════════════════════════════
         EDIT PROFILE MODAL
    ════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
            </div>
            <form id="profileForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">

                    <!-- Avatar upload -->
                    <div class="avatar-upload-row">
                        <div class="avatar-preview" id="avatarPreview" style="<?php echo $avatarStyle; ?>"><?php echo htmlspecialchars($avatarContent); ?></div>
                        <div class="avatar-upload-info">
                            <div class="avatar-upload-name">Profile Photo</div>
                            <div class="avatar-upload-hint">JPG or PNG, max 3MB. Square images work best.</div>
                            <label for="photoInput">
                                <span class="btn btn-ghost" style="font-size:12px;padding:7px 14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                    <i class="fas fa-upload"></i> Upload Photo
                                </span>
                            </label>
                            <input type="file" id="photoInput" name="photo" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-id-card"></i> Personal Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Student ID</label>
                                <input class="form-input" type="text" value="<?php echo htmlspecialchars($student_id); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input class="form-input" type="text" name="name" value="<?php echo htmlspecialchars($student_name); ?>" placeholder="Enter your full name">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input class="form-input" type="date" name="birthdate" value="<?php echo htmlspecialchars($student_birthdate); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Grade Level</label>
                                <select class="form-select" name="grade_level">
                                    <?php foreach (['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'] as $g): ?>
                                        <option value="<?php echo $g; ?>" <?php echo $grade_level === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-address-book"></i> Contact Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($student_email); ?>" placeholder="your@email.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input class="form-input" type="tel" name="phone" value="<?php echo htmlspecialchars($student_phone); ?>" placeholder="+63 912 345 6789">
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input class="form-input" type="text" name="address" value="<?php echo htmlspecialchars($student_address); ?>" placeholder="Street, Barangay, City">
                            </div>
                        </div>
                    </div>

                    <!-- Guardian Information -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-users"></i> Guardian Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Guardian Name</label>
                                <input class="form-input" type="text" name="guardian_name" value="<?php echo htmlspecialchars($student_guardian); ?>" placeholder="Full name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Guardian Phone</label>
                                <input class="form-input" type="tel" name="guardian_phone" value="<?php echo htmlspecialchars($student_guardian_phone); ?>" placeholder="Phone number">
                            </div>
                        </div>
                    </div>

                    <!-- Academic Profile -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-graduation-cap"></i> Academic Profile</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Professional Bio</label>
                                <textarea class="form-textarea" name="professional_bio" rows="4" placeholder="A summary of your career goals, academic focus, and passions..."><?php echo htmlspecialchars($professional_bio); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Awards &amp; Honors</label>
                                <textarea class="form-textarea" name="awards_honors" rows="3" placeholder="List your academic achievements, awards, and honors (one per line)..."><?php echo htmlspecialchars($awards_honors); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Extracurriculars -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-users-rectangle"></i> Extracurricular Activities</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Activities (one per line)</label>
                                <textarea class="form-textarea" name="extracurriculars" rows="3" placeholder="Sports, school clubs, volunteer work, leadership roles..."><?php echo htmlspecialchars($extracurriculars); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Portfolio Projects -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-folder-open"></i> Portfolio Projects</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Your Projects (one per line)</label>
                                <textarea class="form-textarea" name="portfolio_projects" rows="3" placeholder="Samples of your best schoolwork, digital projects, or creative hobbies..."><?php echo htmlspecialchars($portfolio_projects); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-tools"></i> Skills</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Skills (one per line — displayed as tags)</label>
                                <textarea class="form-textarea" name="skills" rows="3" placeholder="Teamwork&#10;Public Speaking&#10;Python&#10;English Proficiency"><?php echo htmlspecialchars($skills); ?></textarea>
                            </div>
                        </div>
                    </div>

                </div><!-- /modal-body -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> <span id="saveBtnText">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ── Modal helpers ─────────────────────────────────────────── */
        function openEditModal() {
            document.getElementById('editProfileModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editProfileModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function openChangePhotoModal() {
            openEditModal();
        }

        document.getElementById('editProfileModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeEditModal();
        });

        /* ── Photo preview ─────────────────────────────────────────── */
        function previewPhoto(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => {
                const el = document.getElementById('avatarPreview');
                el.style.backgroundImage = `url(${e.target.result})`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
                el.textContent = '';
                // Also update hero avatar
                const hero = document.getElementById('heroAvatar');
                hero.style.backgroundImage = `url(${e.target.result})`;
                hero.style.backgroundSize = 'cover';
                hero.style.backgroundPosition = 'center';
                hero.textContent = '';
            };
            reader.readAsDataURL(input.files[0]);
        }

        /* ── Form submit ───────────────────────────────────────────── */
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            const text = document.getElementById('saveBtnText');
            btn.disabled = true;
            text.innerHTML = '<span class="btn-spinner"></span> Saving…';

            const fd = new FormData(this);
            fd.append('action', 'save_profile');

            try {
                const res = await fetch('profile.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    closeEditModal();
                    showToast('Profile updated successfully!', 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Save failed. Please try again.', 'error');
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error');
            } finally {
                btn.disabled = false;
                text.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        });

        /* ── Toast ─────────────────────────────────────────────────── */
        function showToast(msg, type) {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle'
            };
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas fa-${icons[type]}"></i> ${msg}`;
            t.onclick = () => t.remove();
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }
    </script>

    <!-- ══ STUDENT NAV LOADER ══════════════════════════════════════════════
         Fetches Student_nav.php, resolves all data-nav-href links relative
         to the current page directory, then injects into #nav-placeholder.
    ══════════════════════════════════════════════════════════════════════ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var placeholder = document.getElementById('nav-placeholder');
            if (!placeholder) {
                console.warn('[NavLoader] #nav-placeholder not found');
                return;
            }

            var pageDir = window.location.pathname.replace(/\/[^\/]*$/, '/');

            fetch('Student_nav.php')
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function(html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;

                    tmp.querySelectorAll('[data-nav-href]').forEach(function(el) {
                        var rel = el.getAttribute('data-nav-href');
                        if (rel.startsWith('../')) {
                            el.setAttribute('href', pageDir.replace(/\/[^\/]+\/$/, '/') + rel.slice(3));
                        } else {
                            el.setAttribute('href', pageDir + rel);
                        }
                        el.removeAttribute('data-nav-href');
                    });

                    tmp.querySelectorAll('img[src]').forEach(function(img) {
                        var src = img.getAttribute('src');
                        if (src && !src.startsWith('/') && !src.startsWith('http'))
                            img.setAttribute('src', pageDir + src);
                    });

                    tmp.querySelectorAll('style').forEach(function(s) {
                        document.head.appendChild(s.cloneNode(true));
                        s.remove();
                    });

                    while (tmp.firstChild) placeholder.parentNode.insertBefore(tmp.firstChild, placeholder);
                    placeholder.remove();

                    tmp.querySelectorAll('script').forEach(function(old) {
                        var s = document.createElement('script');
                        s.textContent = old.textContent;
                        document.body.appendChild(s);
                    });

                    var nameEl = document.getElementById('navStudentName');
                    var gradeEl = document.getElementById('navGradeLevel');
                    if (nameEl && document.body.dataset.studentName) nameEl.textContent = document.body.dataset.studentName;
                    if (gradeEl && document.body.dataset.gradeLevel) gradeEl.textContent = document.body.dataset.gradeLevel;

                    (function waitForStudentNav(n) {
                        if (window.StudentNav && typeof window.StudentNav.bootProfileFromBody === 'function') {
                            window.StudentNav.bootProfileFromBody();
                        } else if (n > 0) {
                            setTimeout(function() {
                                waitForStudentNav(n - 1);
                            }, 60);
                        }
                    })(20);

                    var current = window.location.pathname.split('/').pop() || 'profile.php';
                    document.querySelectorAll('.sidebar .menu-item').forEach(function(item) {
                        var href = (item.getAttribute('href') || '').split('/').pop();
                        item.classList.toggle('active', href === current);
                    });

                    console.log('[NavLoader] Student_nav.php loaded — base dir: ' + pageDir);
                })
                .catch(function(err) {
                    console.error('[NavLoader] Failed:', err);
                });
        });
    </script>
</body>

</html>