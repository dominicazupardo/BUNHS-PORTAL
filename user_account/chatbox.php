<?php

/**
 * chatbox.php  (student-side)
 * Enhanced: file request system, club group chats, redesigned UI.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';

// Auth guard — redirect to login if no active student session
if (!isset($_SESSION['student_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: index.php');
    exit;
}

include '../db_connection.php';

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$grade_level  = $_SESSION['grade_level']  ?? 'Grade 10';
$initials     = strtoupper(substr(strip_tags($student_name), 0, 1));
$notification_preference = null;

$chatApiPath    = '../admin_account/chat_api.php';
$fileRequestApi = '../admin_account/file_request_api.php';
$clubChatApi    = '../admin_account/club_chat_api.php';

// ── FETCH FULL STUDENT PROFILE ────────────────────────────────────────────────
$db_student = [];
try {
    $stmt = $conn->prepare(
        "SELECT first_name, last_name, grade_level,
                phone, email, photo,
                notification_preference,
                phone_verified, email_verified,
                login_method
         FROM students WHERE student_id = ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $db_student   = $row;
            $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $grade_level  = $row['grade_level'];
            $notification_preference = $row['notification_preference'];
            $initials = strtoupper(substr($student_name, 0, 1));
        }
        $stmt->close();
    }
} catch (Exception $e) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name, grade_level, notification_preference FROM students WHERE student_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $db_student   = $row;
                $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $grade_level  = $row['grade_level'];
                $notification_preference = $row['notification_preference'];
                $initials = strtoupper(substr($student_name, 0, 1));
            }
            $stmt->close();
        }
    } catch (Exception $e2) {
        error_log("DB error: " . $e2->getMessage());
    }
}

// ── PROFILE DISPLAY MODE ──────────────────────────────────────────────────────
$login_method   = isset($db_student['login_method']) ? $db_student['login_method'] : null;
$phone_verified = !empty($db_student['phone_verified']);
$email_verified = !empty($db_student['email_verified']);
$profile_photo  = !empty($db_student['photo']) ? $db_student['photo'] : null;
$student_email  = !empty($db_student['email'])  ? $db_student['email']  : null;
$student_phone  = !empty($db_student['phone'])  ? $db_student['phone']  : null;

if (!$login_method && !empty($_SESSION['login_method']))   $login_method  = $_SESSION['login_method'];
if (!$student_email && !empty($_SESSION['student_email'])) $student_email = $_SESSION['student_email'];
if (!$student_phone && !empty($_SESSION['student_phone'])) $student_phone = $_SESSION['student_phone'];

if ($login_method === 'email' || ($email_verified && $student_email)) {
    $profile_display_mode = 'email';
} elseif ($login_method === 'phone' || ($phone_verified && $student_phone)) {
    $profile_display_mode = 'phone';
} elseif (!empty($_SESSION['notif_dismissed'])) {
    $profile_display_mode = 'skip';
} else {
    $profile_display_mode = 'none';
}

$user_verified = ($email_verified || $phone_verified || !empty($_SESSION['dash_email_verified']));

// ── PROFILE PICTURE ───────────────────────────────────────────────────────────
$nav_profile_img  = 'assets/img/person/unknown.jpg';
$nav_profile_type = 'icon';

if ($profile_display_mode === 'email') {
    if ($profile_photo) {
        $nav_profile_img  = htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } elseif (!empty($_SESSION['google_avatar'])) {
        $nav_profile_img  = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    }
}

switch ($profile_display_mode) {
    case 'email':
        $nav_display_label = $student_email
            ? htmlspecialchars($student_email, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        break;
    case 'phone':
        $nav_display_label = $student_phone
            ? htmlspecialchars($student_phone, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        break;
    default:
        $nav_display_label = '';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages – BUNHS Student Portal</title>
    <link rel="stylesheet" href="../admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ════════════════════════════════════════════
           ROOT VARIABLES
        ════════════════════════════════════════════ */
        :root {
            --moss: #7a8f4e;
            --moss-dark: #5c6b38;
            --moss-light: #adbf72;
            --moss-ultra: rgba(122, 143, 78, .12);
            --moss-glow: rgba(122, 143, 78, .35);
            --sidebar-w: 280px;
            --bg: #f2f5f0;
            --surface: #ffffff;
            --border: #e4e9de;
            --text: #1a2010;
            --muted: #6b7c55;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-md: 0 6px 24px rgba(0, 0, 0, .09);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, .12);
            --radius: 14px;
            --radius-sm: 8px;
            --font: 'DM Sans', sans-serif;
            --font-display: 'Syne', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            background-image:
                radial-gradient(circle at 0% 0%, rgba(122, 143, 78, .06) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(92, 107, 56, .05) 0%, transparent 50%);
            min-height: 100vh;
            color: var(--text);
        }

        /* sidebar loaded from Student_nav.php */

        /* ════════════════════════════════════════════
           MAIN LAYOUT
        ════════════════════════════════════════════ */
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-header h1 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: var(--moss);
            font-size: 20px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .header-date {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            background: var(--surface);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            color: var(--muted);
            font-size: 13px;
            border: 1px solid var(--border);
        }

        .header-date i {
            color: var(--moss);
        }

        /* ════════════════════════════════════════════
           CHAT LAYOUT
        ════════════════════════════════════════════ */
        .chat-wrap {
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 18px;
            flex: 1;
            height: calc(100vh - 136px);
            min-height: 0;
        }

        /* ── Sidebar panel ── */
        .chat-panel {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 18px 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-header h3 {
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .panel-badge {
            background: var(--moss);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .panel-search {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
        }

        .panel-search input {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            outline: none;
            font-family: var(--font);
            background: var(--bg);
            transition: border-color .15s;
        }

        .panel-search input:focus {
            border-color: var(--moss);
        }

        .contact-list {
            flex: 1;
            overflow-y: auto;
        }

        .contact-list::-webkit-scrollbar {
            width: 3px;
        }

        .contact-list::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        /* Section label inside list */
        .conv-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 10px 18px 4px;
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f5f8f2;
            transition: background .15s;
            position: relative;
        }

        .conv-item:hover {
            background: var(--moss-ultra);
        }

        .conv-item.active {
            background: var(--moss-ultra);
            border-left: 3px solid var(--moss);
        }

        /* Admin avatar */
        .conv-avatar {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
        }

        .conv-avatar.admin-av {
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
        }

        /* Club avatar — distinct style */
        .conv-avatar.club-av {
            background: linear-gradient(135deg, #2d6a4f, #1b4332);
            border: 2px solid rgba(122, 143, 78, .4);
            position: relative;
        }

        .conv-avatar.club-av::after {
            content: '';
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: var(--moss-light);
            border-radius: 50%;
            border: 2px solid var(--surface);
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .club-tag {
            background: var(--moss-ultra);
            color: var(--moss);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 1px 6px;
            border-radius: 20px;
            border: 1px solid rgba(122, 143, 78, .2);
        }

        .conv-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-time {
            font-size: 10px;
            color: #aaa;
            white-space: nowrap;
            margin-left: 4px;
        }

        .conv-unread {
            background: var(--moss);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            position: absolute;
            top: 12px;
            right: 14px;
        }

        /* ════════════════════════════════════════════
           CHAT WINDOW
        ════════════════════════════════════════════ */
        .chat-win {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .chat-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 13px;
            background: linear-gradient(90deg, rgba(122, 143, 78, .04), transparent);
        }

        .chat-header-avatar {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 17px;
        }

        .chat-header-info h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 5px;
        }

        .chat-header-status {
            font-size: 12px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* Club header extra badge */
        .club-header-badge {
            margin-left: auto;
            background: var(--moss-ultra);
            color: var(--moss);
            border: 1px solid rgba(122, 143, 78, .25);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Messages */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f6f9f2;
        }

        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        /* Date divider */
        .date-sep {
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            position: relative;
            margin: 6px 0;
        }

        .date-sep span {
            background: #f6f9f2;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }

        .date-sep::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: var(--border);
        }

        /* Bubbles */
        .msg-row {
            display: flex;
            gap: 9px;
            max-width: 74%;
            animation: bubbleIn .2s ease;
        }

        @keyframes bubbleIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-row.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .msg-av {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            flex-shrink: 0;
            align-self: flex-end;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
        }

        .msg-row.sent .msg-av {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .msg-bubble {
            background: var(--surface);
            padding: 10px 14px;
            border-radius: 14px 14px 14px 4px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .msg-row.sent .msg-bubble {
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            color: #fff;
            border-radius: 14px 14px 4px 14px;
            border: none;
        }

        .msg-text {
            font-size: 13.5px;
            line-height: 1.55;
            word-break: break-word;
        }

        .msg-time {
            font-size: 10.5px;
            color: var(--muted);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .msg-row.sent .msg-time {
            color: rgba(255, 255, 255, .65);
            justify-content: flex-end;
        }

        /* File request bubble — special styling */
        .msg-bubble.file-req-bubble {
            background: #fffdf5;
            border: 1.5px solid #f0e09a;
            border-radius: 14px 14px 14px 4px;
        }

        .msg-row.sent .msg-bubble.file-req-bubble {
            background: linear-gradient(135deg, #5c6b38, var(--moss-dark));
            border: none;
        }

        .file-req-header {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 6px;
        }

        .file-req-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
        }

        .file-req-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .3px;
            color: var(--moss);
        }

        .msg-row.sent .file-req-label {
            color: rgba(255, 255, 255, .8);
        }

        .file-req-name {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .file-req-reason {
            font-size: 12.5px;
            opacity: .75;
        }

        /* Status pill */
        .req-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .req-status-pill.pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .req-status-pill.approved {
            background: #dcfce7;
            color: #14532d;
        }

        .req-status-pill.rejected {
            background: #fee2e2;
            color: #7f1d1d;
        }

        /* File download button (when approved) */
        .file-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 8px;
            padding: 7px 14px;
            background: var(--moss);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all .18s;
        }

        .file-download-btn:hover {
            background: var(--moss-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--moss-glow);
        }

        /* ════════════════════════════════════════════
           INPUT AREA
        ════════════════════════════════════════════ */
        .chat-input-wrap {
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            position: relative;
        }

        .input-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* File request button */
        .file-req-btn {
            width: 40px;
            height: 40px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--bg);
            color: var(--muted);
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .18s;
            flex-shrink: 0;
            position: relative;
        }

        .file-req-btn:hover {
            border-color: var(--moss);
            color: var(--moss);
            background: var(--moss-ultra);
        }

        .file-req-btn.active {
            border-color: var(--moss);
            color: var(--moss);
            background: var(--moss-ultra);
        }

        /* Dropdown upward */
        .file-dropdown {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 18px;
            width: 300px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 200;
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
        }

        .file-dropdown.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }

        .file-dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            font-size: 12px;
            color: var(--text);
            letter-spacing: .3px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .file-dropdown-header i {
            color: var(--moss);
        }

        .file-dropdown-body {
            max-height: 240px;
            overflow-y: auto;
        }

        .file-dropdown-body::-webkit-scrollbar {
            width: 3px;
        }

        .file-dropdown-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .file-dropdown-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 16px;
            cursor: pointer;
            transition: background .14s;
            border-bottom: 1px solid #f5f8f2;
        }

        .file-dropdown-item:last-child {
            border-bottom: none;
        }

        .file-dropdown-item:hover {
            background: var(--moss-ultra);
        }

        .file-dropdown-item-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .file-dropdown-item-icon.pdf {
            background: #fee2e2;
            color: #ef4444;
        }

        .file-dropdown-item-icon.doc {
            background: #dbeafe;
            color: #3b82f6;
        }

        .file-dropdown-item-icon.xls {
            background: #dcfce7;
            color: #16a34a;
        }

        .file-dropdown-item-icon.img {
            background: #ede9fe;
            color: #7c3aed;
        }

        .file-dropdown-item-icon.other {
            background: #f3f4f6;
            color: #6b7280;
        }

        .file-dropdown-item-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        .file-dropdown-item-cat {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .file-dropdown-empty {
            padding: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .file-dropdown-empty i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
            opacity: .4;
        }

        /* Reason input overlay (below message field) */
        .reason-bar {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 0 0;
            border-top: 1px dashed var(--border);
            margin-top: 8px;
        }

        .reason-bar.show {
            display: flex;
        }

        .reason-bar-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--moss);
            white-space: nowrap;
        }

        .reason-bar input {
            flex: 1;
            padding: 7px 11px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            font-family: var(--font);
            transition: border-color .15s;
        }

        .reason-bar input:focus {
            border-color: var(--moss);
        }

        .reason-cancel {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }

        .reason-cancel:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fee2e2;
        }

        /* Message text input */
        .msg-input {
            flex: 1;
            padding: 11px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: var(--font);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .msg-input:focus {
            border-color: var(--moss);
            box-shadow: 0 0 0 3px rgba(122, 143, 78, .12);
        }

        /* Send button */
        .send-btn {
            height: 40px;
            padding: 0 18px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all .18s;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px var(--moss-glow);
        }

        .send-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ════════════════════════════════════════════
           TOAST
        ════════════════════════════════════════════ */
        .toast-zone {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }

        .toast {
            background: var(--surface);
            border-radius: 11px;
            padding: 13px 18px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            transform: translateX(120%);
            transition: transform .3s ease;
            border-left: 4px solid transparent;
            font-size: 13.5px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-color: #22c55e;
        }

        .toast.error {
            border-color: #ef4444;
        }

        .toast i {
            font-size: 17px;
        }

        .toast.success i {
            color: #22c55e;
        }

        .toast.error i {
            color: #ef4444;
        }

        /* ════════════════════════════════════════════
           LOADING STATE
        ════════════════════════════════════════════ */
        .chat-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 48px;
            color: var(--muted);
            font-size: 13.5px;
        }

        .spin {
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════ */
        @media (max-width: 1000px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 820px) {
            .chat-wrap {
                grid-template-columns: 1fr;
            }

            .chat-panel {
                display: none;
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

    <!-- ── Main content ── -->
    <main class="main-content">
        <header class="page-header">
            <div>
                <h1><i class="fas fa-comments"></i> Messages</h1>
                <p>Chat with admin or your club members</p>
            </div>
            <div class="header-date">
                <i class="fas fa-calendar-alt"></i>
                <span id="currentDate"></span>
            </div>
        </header>

        <div class="chat-wrap">
            <!-- ── Left panel: contacts + clubs ── -->
            <div class="chat-panel">
                <div class="panel-header">
                    <h3>Conversations</h3>
                </div>
                <div class="panel-search">
                    <input type="text" id="convSearch" placeholder="Search chats…" oninput="filterConvs(this.value)">
                </div>
                <div class="contact-list" id="contactList">
                    <!-- Admin contact (always present) -->
                    <div class="conv-section-label">Direct</div>
                    <div class="conv-item active" id="adminConvItem" onclick="openAdminChat()">
                        <div class="conv-avatar admin-av">AD</div>
                        <div class="conv-info">
                            <div class="conv-name">Admin Department</div>
                            <div class="conv-preview" id="adminPreview">Loading…</div>
                        </div>
                    </div>
                    <!-- Club chats will be injected here -->
                    <div id="clubChatsSection"></div>
                </div>
            </div>

            <!-- ── Chat window ── -->
            <div class="chat-win">
                <div class="chat-header" id="chatHeader">
                    <div class="chat-header-avatar" id="chatAvatar">AD</div>
                    <div class="chat-header-info">
                        <h3 id="chatName">Admin Department</h3>
                        <div class="chat-header-status">
                            <span class="online-dot"></span>Online
                        </div>
                    </div>
                    <div id="clubHeaderBadge" style="display:none;" class="club-header-badge">
                        <i class="fas fa-users"></i>
                        <span id="clubMemberCount">0 members</span>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <div class="chat-loading">
                        <i class="fas fa-spinner spin"></i> Loading messages…
                    </div>
                </div>

                <div class="chat-input-wrap">
                    <!-- File dropdown (opens upward) -->
                    <div class="file-dropdown" id="fileDropdown">
                        <div class="file-dropdown-header">
                            <i class="fas fa-lock"></i> Request a Restricted File
                        </div>
                        <div class="file-dropdown-body" id="fileDropdownBody">
                            <div class="file-dropdown-empty">
                                <i class="fas fa-spinner spin"></i>
                                Loading files…
                            </div>
                        </div>
                    </div>

                    <div class="input-row">
                        <button class="file-req-btn" id="fileReqBtn" title="Request a restricted file" onclick="toggleFileDropdown()" style="display:none;">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="text" class="msg-input" id="msgInput"
                            placeholder="Type your message…"
                            onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMsg();}">
                        <button class="send-btn" id="sendBtn" onclick="sendMsg()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>

                    <!-- Reason bar (shown when a file is selected) -->
                    <div class="reason-bar" id="reasonBar">
                        <span class="reason-bar-label"><i class="fas fa-file-lock"></i> Reason:</span>
                        <input type="text" id="reasonInput"
                            placeholder="Why do you need this file?"
                            onkeydown="if(event.key==='Enter'){event.preventDefault();sendMsg();}">
                        <button class="reason-cancel" onclick="cancelFileRequest()" title="Cancel">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="toast-zone" id="toastZone"></div>

    <script>
        /* ════════════════════════════════════════════
   CONFIG & STATE
════════════════════════════════════════════ */
        const API = '<?= htmlspecialchars($chatApiPath,    ENT_QUOTES, "UTF-8") ?>';
        const FILE_REQ_API = '<?= htmlspecialchars($fileRequestApi, ENT_QUOTES, "UTF-8") ?>';
        const CLUB_API = '<?= htmlspecialchars($clubChatApi,    ENT_QUOTES, "UTF-8") ?>';
        const STUDENT_INIT = '<?= $initials ?>';

        let convId = 0; // admin conversation id
        let activeChatId = 0; // currently open conv/club id
        let activeMode = 'admin'; // 'admin' | 'club'
        let pollTimer = null;
        let selectedFile = null; // { id, title, file_type }
        let allConvItems = [];

        /* ════════════════════════════════════════════
           INIT
        ════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('currentDate').textContent =
                new Date().toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

            ensureAdminConv().then(() => {
                openAdminChat();
                loadClubChats();
                loadRestrictedFiles();
            });
        });

        /* ════════════════════════════════════════════
           ADMIN CHAT
        ════════════════════════════════════════════ */
        async function ensureAdminConv() {
            const fd = new FormData();
            fd.append('action', 'get_student_conv');
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success && data.conv_id) convId = data.conv_id;
        }

        function openAdminChat() {
            activeMode = 'admin';
            activeChatId = convId;

            // UI updates
            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            document.getElementById('adminConvItem').classList.add('active');
            document.getElementById('chatName').textContent = 'Admin Department';
            document.getElementById('chatAvatar').textContent = 'AD';
            document.getElementById('clubHeaderBadge').style.display = 'none';
            document.getElementById('fileReqBtn').style.display = ''; // show file request btn

            clearInterval(pollTimer);
            loadAdminMessages();
            pollTimer = setInterval(loadAdminMessages, 5000);
        }

        async function loadAdminMessages() {
            if (!convId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('conversation_id', convId);
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) return;
            renderMessages(data.messages, 'admin');
            markAdminRead();
        }

        function markAdminRead() {
            if (!convId) return;
            const fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('conversation_id', convId);
            fetch(API, {
                method: 'POST',
                body: fd
            });
        }

        /* ════════════════════════════════════════════
           CLUB CHATS
        ════════════════════════════════════════════ */
        async function loadClubChats() {
            const fd = new FormData();
            fd.append('action', 'get_student_clubs');
            const res = await fetch(CLUB_API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success || !data.clubs.length) return;

            const section = document.getElementById('clubChatsSection');
            section.innerHTML = `<div class="conv-section-label">Club Chats</div>` +
                data.clubs.map(club => `
            <div class="conv-item" id="club_${club.id}"
                 onclick="openClubChat(${club.id}, '${esc(club.name)}', ${club.member_count})">
                <div class="conv-avatar club-av">
                    ${esc(club.name.charAt(0).toUpperCase())}
                </div>
                <div class="conv-info">
                    <div class="conv-name">
                        ${esc(club.name)}
                        <span class="club-tag">Club</span>
                    </div>
                    <div class="conv-preview">${esc(club.last_message || 'No messages yet')}</div>
                </div>
                <span class="conv-time">${esc(club.last_time || '')}</span>
                ${club.unread > 0 ? `<span class="conv-unread">${club.unread}</span>` : ''}
            </div>
        `).join('');
        }

        function openClubChat(clubId, clubName, memberCount) {
            activeMode = 'club';
            activeChatId = clubId;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            const el = document.getElementById('club_' + clubId);
            if (el) el.classList.add('active');

            document.getElementById('chatName').textContent = clubName;
            document.getElementById('chatAvatar').textContent = clubName.charAt(0).toUpperCase();
            document.getElementById('clubHeaderBadge').style.display = '';
            document.getElementById('clubMemberCount').textContent = memberCount + ' members';
            document.getElementById('fileReqBtn').style.display = 'none'; // no file requests in club chat
            cancelFileRequest();

            clearInterval(pollTimer);
            loadClubMessages(clubId);
            pollTimer = setInterval(() => loadClubMessages(clubId), 5000);
        }

        async function loadClubMessages(clubId) {
            const fd = new FormData();
            fd.append('action', 'fetch_club_messages');
            fd.append('club_id', clubId);
            const res = await fetch(CLUB_API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) return;
            renderMessages(data.messages, 'club');
        }

        /* ════════════════════════════════════════════
           RENDER MESSAGES
        ════════════════════════════════════════════ */
        function renderMessages(msgs, mode) {
            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 60;

            if (!msgs.length) {
                box.innerHTML = `<div style="text-align:center;padding:48px;color:#aaa;">
            <i class="fas fa-comments" style="font-size:36px;opacity:.25;display:block;margin-bottom:10px;"></i>
            No messages yet. Say hello!
        </div>`;
                return;
            }

            let lastDate = '';
            box.innerHTML = msgs.map(m => {
                const isMine = (mode === 'admin') ?
                    m.sender_role === 'student' :
                    m.sender_id == <?= (int)$student_id ?>;

                const init = isMine ? STUDENT_INIT : (m.avatar_letter || (mode === 'club' ? m.sender_name?.charAt(0)?.toUpperCase() : 'AD'));
                const msgDate = (m.created_at || '').substring(0, 10);
                let divider = '';
                if (msgDate && msgDate !== lastDate) {
                    lastDate = msgDate;
                    divider = `<div class="date-sep"><span>${formatDateLabel(msgDate)}</span></div>`;
                }

                /* Check if this is a file request message */
                if (m.message_type === 'file_request') {
                    return divider + buildFileReqBubble(m, isMine, init);
                }

                return `${divider}
        <div class="msg-row ${isMine ? 'sent' : ''}">
            <div class="msg-av">${esc(init)}</div>
            <div>
                ${mode === 'club' && !isMine ? `<div style="font-size:11px;color:var(--muted);margin-bottom:3px;padding-left:2px;">${esc(m.sender_name)}</div>` : ''}
                <div class="msg-bubble">
                    <div class="msg-text">${esc(m.message)}</div>
                </div>
                <div class="msg-time"><i class="far fa-clock"></i>${esc(m.time_label)}</div>
            </div>
        </div>`;
            }).join('');

            if (atBottom) box.scrollTop = box.scrollHeight;

            // Update preview
            if (mode === 'admin' && msgs.length) {
                const last = msgs[msgs.length - 1];
                document.getElementById('adminPreview').textContent = (last.message || '').substring(0, 45);
            }
        }

        function buildFileReqBubble(m, isMine, init) {
            const statusMap = {
                pending: {
                    cls: 'pending',
                    icon: 'fa-clock',
                    label: 'Pending Approval'
                },
                approved: {
                    cls: 'approved',
                    icon: 'fa-check-circle',
                    label: 'Approved'
                },
                rejected: {
                    cls: 'rejected',
                    icon: 'fa-times-circle',
                    label: 'Rejected'
                },
            };
            const s = statusMap[m.request_status] || statusMap.pending;
            const fileTypeIcon = getFileIcon(m.file_type || '');

            const downloadBtn = (m.request_status === 'approved' && m.download_url) ? `
        <a href="${esc(m.download_url)}" class="file-download-btn" target="_blank">
            <i class="fas fa-download"></i> Download File
        </a>` : '';

            return `
    <div class="msg-row ${isMine ? 'sent' : ''}">
        <div class="msg-av">${esc(init)}</div>
        <div>
            <div class="msg-bubble file-req-bubble">
                <div class="file-req-header">
                    <div class="file-req-icon"><i class="fas ${fileTypeIcon}"></i></div>
                    <span class="file-req-label">FILE REQUEST</span>
                </div>
                <div class="file-req-name">${esc(m.file_name)}</div>
                <div class="file-req-reason">${esc(m.message)}</div>
                <div><span class="req-status-pill ${s.cls}">
                    <i class="fas ${s.icon}"></i>${s.label}
                </span></div>
                ${downloadBtn}
            </div>
            <div class="msg-time"><i class="far fa-clock"></i>${esc(m.time_label)}</div>
        </div>
    </div>`;
        }

        /* ════════════════════════════════════════════
           SEND MESSAGE
        ════════════════════════════════════════════ */
        async function sendMsg() {
            const msgInput = document.getElementById('msgInput');
            const reasonInput = document.getElementById('reasonInput');
            const sendBtn = document.getElementById('sendBtn');

            /* File request mode */
            if (selectedFile) {
                const reason = reasonInput.value.trim();
                if (!reason) {
                    reasonInput.focus();
                    toast('Please enter a reason for the file request.', 'error');
                    return;
                }
                sendBtn.disabled = true;
                await sendFileRequest(selectedFile, reason);
                sendBtn.disabled = false;
                return;
            }

            const text = msgInput.value.trim();
            if (!text) return;

            sendBtn.disabled = true;
            msgInput.value = '';

            if (activeMode === 'admin') {
                const fd = new FormData();
                fd.append('action', 'send_message');
                fd.append('message', text);
                if (convId) fd.append('conversation_id', convId);

                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                sendBtn.disabled = false;
                if (data.success) {
                    if (data.conv_id && !convId) convId = data.conv_id;
                    await loadAdminMessages();
                } else {
                    msgInput.value = text;
                    toast('Could not send message. Please try again.', 'error');
                }
            } else {
                const fd = new FormData();
                fd.append('action', 'send_club_message');
                fd.append('club_id', activeChatId);
                fd.append('message', text);

                const res = await fetch(CLUB_API, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                sendBtn.disabled = false;
                if (data.success) {
                    await loadClubMessages(activeChatId);
                } else {
                    msgInput.value = text;
                    toast('Could not send message.', 'error');
                }
            }
        }

        async function sendFileRequest(file, reason) {
            const fd = new FormData();
            fd.append('action', 'submit_file_request');
            fd.append('file_id', file.id);
            fd.append('reason', reason);
            fd.append('conversation_id', convId);

            const res = await fetch(FILE_REQ_API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                cancelFileRequest();
                toast('File request sent! Waiting for admin approval.', 'success');
                await loadAdminMessages();
            } else {
                toast(data.message || 'Could not send request.', 'error');
            }
        }

        /* ════════════════════════════════════════════
           RESTRICTED FILE DROPDOWN
        ════════════════════════════════════════════ */
        async function loadRestrictedFiles() {
            const fd = new FormData();
            fd.append('action', 'get_restricted_files');
            const res = await fetch(FILE_REQ_API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            const body = document.getElementById('fileDropdownBody');

            if (!data.success || !data.files.length) {
                body.innerHTML = `<div class="file-dropdown-empty">
            <i class="fas fa-folder-open"></i>No restricted files available.</div>`;
                return;
            }

            body.innerHTML = data.files.map(f => `
        <div class="file-dropdown-item" onclick="selectFile(${f.id}, '${esc(f.title)}', '${esc(f.file_type)}')">
            <div class="file-dropdown-item-icon ${getFileIconClass(f.file_type)}">
                <i class="fas ${getFileIcon(f.file_type)}"></i>
            </div>
            <div>
                <div class="file-dropdown-item-name">${esc(f.title)}</div>
                <div class="file-dropdown-item-cat">
                    <i class="fas fa-tag"></i>${esc(f.category)}
                </div>
            </div>
        </div>
    `).join('');
        }

        function toggleFileDropdown() {
            const dd = document.getElementById('fileDropdown');
            const btn = document.getElementById('fileReqBtn');
            dd.classList.toggle('open');
            btn.classList.toggle('active');

            // Close on outside click
            if (dd.classList.contains('open')) {
                setTimeout(() => {
                    document.addEventListener('click', closeDropdownOutside, {
                        once: true
                    });
                }, 50);
            }
        }

        function closeDropdownOutside(e) {
            const dd = document.getElementById('fileDropdown');
            const btn = document.getElementById('fileReqBtn');
            if (!dd.contains(e.target) && !btn.contains(e.target)) {
                dd.classList.remove('open');
                btn.classList.remove('active');
            }
        }

        function selectFile(id, title, fileType) {
            selectedFile = {
                id,
                title,
                file_type: fileType
            };
            const dd = document.getElementById('fileDropdown');
            const btn = document.getElementById('fileReqBtn');
            dd.classList.remove('open');
            btn.classList.remove('active');

            // Pre-fill message input with file name tag
            const msgInput = document.getElementById('msgInput');
            msgInput.value = `📄 Requesting: ${title}`;
            msgInput.disabled = true;

            // Show reason bar
            document.getElementById('reasonBar').classList.add('show');
            document.getElementById('reasonInput').focus();
            btn.style.color = 'var(--moss)';
        }

        function cancelFileRequest() {
            selectedFile = null;
            document.getElementById('msgInput').value = '';
            document.getElementById('msgInput').disabled = false;
            document.getElementById('reasonInput').value = '';
            document.getElementById('reasonBar').classList.remove('show');
        }

        /* ════════════════════════════════════════════
           SEARCH FILTER
        ════════════════════════════════════════════ */
        function filterConvs(q) {
            q = q.toLowerCase();
            document.querySelectorAll('.conv-item').forEach(el => {
                const name = el.querySelector('.conv-name')?.textContent?.toLowerCase() || '';
                el.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        }

        /* ════════════════════════════════════════════
           HELPERS
        ════════════════════════════════════════════ */
        function esc(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDateLabel(d) {
            const today = new Date().toISOString().substring(0, 10);
            if (d === today) return 'Today';
            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);
            if (d === yest) return 'Yesterday';
            return new Date(d).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function getFileIcon(ft) {
            if (!ft) return 'fa-file';
            if (ft.includes('pdf')) return 'fa-file-pdf';
            if (ft.includes('image')) return 'fa-file-image';
            if (ft.includes('word') || ft.includes('document')) return 'fa-file-word';
            if (ft.includes('sheet') || ft.includes('excel')) return 'fa-file-excel';
            return 'fa-file-alt';
        }

        function getFileIconClass(ft) {
            if (!ft) return 'other';
            if (ft.includes('pdf')) return 'pdf';
            if (ft.includes('image')) return 'img';
            if (ft.includes('word') || ft.includes('document')) return 'doc';
            if (ft.includes('sheet') || ft.includes('excel')) return 'xls';
            return 'other';
        }

        function toast(msg, type = 'success') {
            const zone = document.getElementById('toastZone');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                   <span>${msg}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 320);
            }, 3500);
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

            // Compute the directory of the current page so relative links resolve correctly
            var pageDir = window.location.pathname.replace(/\/[^\/]*$/, '/');

            fetch('Student_nav.php')
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function(html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;

                    // ── Resolve data-nav-href → real href using current page directory ──
                    tmp.querySelectorAll('[data-nav-href]').forEach(function(el) {
                        var rel = el.getAttribute('data-nav-href');
                        if (rel.startsWith('../')) {
                            var parentDir = pageDir.replace(/\/[^\/]+\/$/, '/');
                            el.setAttribute('href', parentDir + rel.slice(3));
                        } else {
                            el.setAttribute('href', pageDir + rel);
                        }
                        el.removeAttribute('data-nav-href');
                    });

                    // ── Resolve relative image src paths ──
                    tmp.querySelectorAll('img[src]').forEach(function(img) {
                        var src = img.getAttribute('src');
                        if (src && !src.startsWith('/') && !src.startsWith('http')) {
                            img.setAttribute('src', pageDir + src);
                        }
                    });

                    // ── Move <style> tags into <head> ──
                    tmp.querySelectorAll('style').forEach(function(styleEl) {
                        document.head.appendChild(styleEl.cloneNode(true));
                        styleEl.remove();
                    });

                    // ── Insert markup before placeholder then remove it ──
                    while (tmp.firstChild) {
                        placeholder.parentNode.insertBefore(tmp.firstChild, placeholder);
                    }
                    placeholder.remove();

                    // ── Re-execute <script> tags (innerHTML does not run them) ──
                    tmp.querySelectorAll('script').forEach(function(oldScript) {
                        var newScript = document.createElement('script');
                        newScript.textContent = oldScript.textContent;
                        document.body.appendChild(newScript);
                    });

                    // ── Populate student name & grade from body data attributes ──
                    var nameEl = document.getElementById('navStudentName');
                    var gradeEl = document.getElementById('navGradeLevel');
                    if (nameEl && document.body.dataset.studentName) nameEl.textContent = document.body.dataset.studentName;
                    if (gradeEl && document.body.dataset.gradeLevel) gradeEl.textContent = document.body.dataset.gradeLevel;

                    // ── Boot the profile chip (avatar + label) ────────────────────
                    (function waitForStudentNav(attempts) {
                        if (window.StudentNav && typeof window.StudentNav.bootProfileFromBody === 'function') {
                            window.StudentNav.bootProfileFromBody();
                        } else if (attempts > 0) {
                            setTimeout(function() {
                                waitForStudentNav(attempts - 1);
                            }, 60);
                        }
                    })(20);

                    // ── Highlight the current page link ──
                    var current = window.location.pathname.split('/').pop() || 'chatbox.php';
                    document.querySelectorAll('.sidebar .menu-item').forEach(function(item) {
                        var href = (item.getAttribute('href') || '').split('/').pop();
                        item.classList.toggle('active', href === current);
                    });

                    console.log('[NavLoader] Student_nav.php loaded — base dir: ' + pageDir);
                })
                .catch(function(err) {
                    console.error('[NavLoader] Failed to load Student_nav.php:', err);
                });
        });
    </script>

</body>

</html>