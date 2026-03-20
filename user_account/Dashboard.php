<?php

/**
 * Student Dashboard - Redesigned (ENHANCED)
 * Buyoan National High School
 * Now supports both Student and Parent dashboards
 */

// ── SESSION: must start before session_config.php or any $_SESSION read ──────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';
include '../db_connection.php';

// ── Load APCu cache helper (walks up to project root automatically) ───────────
$_ch_path = null;
foreach ([__DIR__ . '/../cache_helper.php', __DIR__ . '/cache_helper.php'] as $_p) {
    if (file_exists($_p)) {
        $_ch_path = $_p;
        break;
    }
}
if ($_ch_path) {
    require_once $_ch_path;
} else {
    // Graceful no-op stubs so the rest of the file works without APCu
    if (!function_exists('cache_get')) {
        function cache_get($k)
        {
            return false;
        }
    }
    if (!function_exists('cache_set')) {
        function cache_set($k, $v, $t) {}
    }
    if (!function_exists('cache_delete')) {
        function cache_delete($k) {}
    }
}

// ── AUTHENTICATION GUARD ─────────────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: index.php');
    exit;
}

$student_id              = $_SESSION['student_id'];
$student_name            = $_SESSION['student_name'] ?? 'User';
$user_type               = $_SESSION['user_type'] ?? 'student';  // NEW: Detect user type
$grade_level             = $_SESSION['grade_level']  ?? 'Grade 10';
$notification_preference = null;

// ── "Join us" from index.php passes ?show_notif=1 ────────────────────────────
if (isset($_GET['show_notif']) && $_GET['show_notif'] === '1') {
    unset($_SESSION['notif_dismissed']);
}

// ── FETCH FULL STUDENT/PARENT PROFILE (with APCu caching) ───────────────────────
// Cache key: student:profile:{id}   TTL: 120 seconds
// Invalidate anywhere the student updates their profile or verification status.
$db_student  = [];
$profile_key = "student:profile:{$student_id}";
$cached_profile = cache_get($profile_key);

if ($cached_profile !== false) {
    // CACHE HIT — restore profile from memory, no DB query needed
    $db_student              = $cached_profile;
    $student_name            = trim($cached_profile['first_name'] . ' ' . $cached_profile['last_name']);
    if (!empty($cached_profile['grade_level'])) {
        $grade_level = $cached_profile['grade_level'];
    }
    $notification_preference = $cached_profile['notification_preference'] ?? null;
    $user_type               = $cached_profile['user_type'] ?? 'student';
} else {
    // CACHE MISS — query DB then store result
    try {
        $stmt = $conn->prepare(
            "SELECT first_name, last_name, grade_level,
                    phone, email, photo,
                    notification_preference,
                    phone_verified, email_verified,
                    login_method, user_type,
                    relationship_to_student, occupation, address
             FROM students WHERE student_id = ? LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $db_student              = $row;
                $student_name            = trim($row['first_name'] . ' ' . $row['last_name']);
                $grade_level             = $row['grade_level'];
                $notification_preference = $row['notification_preference'];
                $user_type               = $row['user_type'] ?? 'student';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Fallback: minimal query for older schema
        try {
            $stmt = $conn->prepare("SELECT first_name, last_name, grade_level, notification_preference, user_type FROM students WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $db_student              = $row;
                    $student_name            = trim($row['first_name'] . ' ' . $row['last_name']);
                    $grade_level             = $row['grade_level'];
                    $notification_preference = $row['notification_preference'];
                    $user_type               = $row['user_type'] ?? 'student';
                }
                $stmt->close();
            }
        } catch (Exception $e2) {
            error_log("DB error: " . $e2->getMessage());
        }
    }

    // Store in cache only when we got a valid row
    if (!empty($db_student)) {
        cache_set($profile_key, $db_student, 120);
    }
}

// ── DETERMINE PROFILE DISPLAY MODE ───────────────────────────────────────────
$login_method   = isset($db_student['login_method']) ? $db_student['login_method'] : null;
$phone_verified = !empty($db_student['phone_verified']);
$email_verified = !empty($db_student['email_verified']);
$profile_photo  = !empty($db_student['photo'])  ? $db_student['photo']  : null;
$student_email  = !empty($db_student['email'])  ? $db_student['email']  : null;
$student_phone  = !empty($db_student['phone'])  ? $db_student['phone']  : null;

if (!$login_method && !empty($_SESSION['login_method'])) {
    $login_method = $_SESSION['login_method'];
}
if (!$student_email && !empty($_SESSION['student_email'])) {
    $student_email = $_SESSION['student_email'];
}
if (!$student_phone && !empty($_SESSION['student_phone'])) {
    $student_phone = $_SESSION['student_phone'];
}

// Resolve display mode
if ($login_method === 'email' || ($email_verified && $student_email)) {
    $profile_display_mode = 'email';
} elseif ($login_method === 'phone' || ($phone_verified && $student_phone)) {
    $profile_display_mode = 'phone';
} elseif (!empty($_SESSION['notif_dismissed'])) {
    $profile_display_mode = 'skip';
} else {
    $profile_display_mode = 'none';
}

// NEW: Redirect parent users to parent dashboard
if ($user_type === 'parent') {
    // Check if parent_dashboard.php exists, otherwise use profile
    if (file_exists('parent_dashboard.php')) {
        header('Location: parent_dashboard.php');
        exit;
    }
}

/**
 * Helper to display content based on user type
 */
function isStudent()
{
    global $user_type;
    return $user_type === 'student';
}

function isParent()
{
    global $user_type;
    return $user_type === 'parent';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isStudent() ? 'Student' : 'Parent'; ?> Dashboard — BUNHS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8f5f0 0%, #f0f7f4 100%);
            color: #1a3a2a;
        }

        .dashboard-container {
            min-height: 100vh;
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .dashboard-header h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 28px;
            font-weight: 700;
            color: #1a3a2a;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(82, 183, 136, 0.12);
            border: 1px solid rgba(82, 183, 136, 0.3);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #2d6a4f;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(82, 183, 136, 0.2);
            border-radius: 8px;
            text-decoration: none;
            color: #2d6a4f;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            background: #52b788;
            color: white;
            border-color: #52b788;
        }

        .content-section {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 2px 12px rgba(26, 58, 42, 0.08);
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a3a2a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .greeting-box {
            background: linear-gradient(135deg, #3a8c6a 0%, #1a3a2a 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
        }

        .greeting-box h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .greeting-box p {
            opacity: 0.9;
            font-size: 14px;
        }

        .role-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            font-size: 12px;
            margin-top: 12px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .feature-card {
            padding: 20px;
            background: #f8f5f0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .feature-card:hover {
            background: white;
            border-color: #52b788;
            box-shadow: 0 4px 12px rgba(82, 183, 136, 0.15);
        }

        .feature-card i {
            font-size: 28px;
            color: #2d6a4f;
            margin-bottom: 8px;
            display: block;
        }

        .feature-card h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1a3a2a;
        }

        .hidden-parent {
            display: none;
        }

        .hidden-student {
            display: none;
        }

        <?php if ($user_type === 'parent'): ?>.hidden-parent {
            display: block !important;
        }

        .student-only {
            display: none !important;
        }

        <?php else: ?>.hidden-student {
            display: block !important;
        }

        .parent-only {
            display: none !important;
        }

        <?php endif; ?>
    </style>
</head>

<body>

    <div class="dashboard-container">

        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><?php echo isStudent() ? '📚 Student Dashboard' : '👨‍👩‍👧 Parent Portal'; ?></h1>
            </div>
            <div class="nav-links">
                <a href="profile.php">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <a href="../index.php?logout=1">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </div>

        <!-- Greeting Section -->
        <div class="greeting-box">
            <h2>Welcome back, <?php echo htmlspecialchars($student_name); ?></h2>
            <p>
                <?php if (isStudent()): ?>
                    You're doing great! Keep up with your studies and check your assignments below.
                <?php else: ?>
                    Stay connected with your child's academic progress and school activities.
                <?php endif; ?>
            </p>
            <div class="role-indicator">
                <i class="fas fa-<?php echo isStudent() ? 'graduation-cap' : 'heart'; ?>"></i>
                <?php echo ucfirst($user_type); ?> Account
            </div>
        </div>

        <!-- Student-Only Sections -->
        <?php if (isStudent()): ?>

            <div class="content-section student-only">
                <h3 class="section-title">
                    <i class="fas fa-tasks"></i> Your Academic Status
                </h3>
                <p style="color: #666; margin-bottom: 16px;">
                    Grade Level: <strong><?php echo htmlspecialchars($grade_level); ?></strong>
                </p>
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-file-alt"></i>
                        <h3>Assignments</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Grades</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Attendance</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-book"></i>
                        <h3>Subjects</h3>
                    </div>
                </div>
            </div>

            <div class="content-section student-only">
                <h3 class="section-title">
                    <i class="fas fa-bell"></i> Notifications & Events
                </h3>
                <p style="color: #666;">
                    Notification preference: <strong><?php echo $notification_preference ? ucfirst($notification_preference) : 'Not set'; ?></strong>
                </p>
            </div>

        <?php endif; ?>

        <!-- Parent-Only Sections -->
        <?php if (isParent()): ?>

            <div class="content-section parent-only">
                <h3 class="section-title">
                    <i class="fas fa-link"></i> Linked Student Profile
                </h3>
                <p style="color: #666; margin-bottom: 16px;">
                    Manage your connections to student accounts and monitor academic progress.
                </p>
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-user-graduate"></i>
                        <h3>Add Student</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-bar"></i>
                        <h3>Academic Progress</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Attendance</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-file-pdf"></i>
                        <h3>Report Cards</h3>
                    </div>
                </div>
            </div>

            <div class="content-section parent-only">
                <h3 class="section-title">
                    <i class="fas fa-comments"></i> Communication
                </h3>
                <p style="color: #666; margin-bottom: 16px;">
                    Connect with teachers and stay updated on school activities.
                </p>
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Messages</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Announcements</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-calendar"></i>
                        <h3>Event Calendar</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-file-signature"></i>
                        <h3>Permission Slips</h3>
                    </div>
                </div>
            </div>

            <div class="content-section parent-only">
                <h3 class="section-title">
                    <i class="fas fa-credit-card"></i> Financials & Admin
                </h3>
                <p style="color: #666; margin-bottom: 16px;">
                    View tuition status, payments, and required documents.
                </p>
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-wallet"></i>
                        <h3>Tuition Status</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-receipt"></i>
                        <h3>Payment History</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Pay Online</h3>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-folder-open"></i>
                        <h3>Documents</h3>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <!-- Universal Features -->
        <div class="content-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i> Quick Links
            </h3>
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-home"></i>
                    <h3>School Homepage</h3>
                </div>
                <div class="feature-card">
                    <i class="fas fa-question-circle"></i>
                    <h3>Help & Support</h3>
                </div>
                <div class="feature-card">
                    <i class="fas fa-cog"></i>
                    <h3>Settings</h3>
                </div>
            </div>
        </div>

    </div>

</body>

</html>