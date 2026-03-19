<?php
/* ═══════════════════════════════════════════════════════════
   student_report.php  –  Student Incident Report Submission
   ═══════════════════════════════════════════════════════════ */

session_start();

/* ── Database config (update credentials to match your server) ── */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_db');

/* ── Guard: only logged-in students may access this page ── */
if (!isset($_SESSION['student_id'])) {
    header('Location: ../index.php');
    exit;
}

/* ── Pull student info from session ── */
$student_id   = (int)$_SESSION['student_id'];
$student_name = htmlspecialchars($_SESSION['student_name']  ?? 'Unknown Student');
$grade_sec    = htmlspecialchars($_SESSION['grade_section'] ?? '');

/* ═══════════════════════════════════════════════════════════
   DATABASE CONNECTION HELPER
   ═══════════════════════════════════════════════════════════ */
function db_connect()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/* ═══════════════════════════════════════════════════════════
   HANDLE AJAX / FORM SUBMISSION
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {

    header('Content-Type: application/json');

    /* ── Sanitize inputs ── */
    $reporter_name    = htmlspecialchars(strip_tags(trim($_POST['reporter_name'] ?? '')));
    $reporter_grade   = htmlspecialchars(strip_tags(trim($_POST['reporter_grade'] ?? '')));
    $person_reported  = htmlspecialchars(strip_tags(trim($_POST['person_reported'] ?? '')));
    $person_type      = in_array($_POST['person_type'] ?? '', ['Student', 'Teacher']) ? $_POST['person_type'] : 'Student';
    $report_category  = htmlspecialchars(strip_tags(trim($_POST['report_category'] ?? '')));
    $incident_date    = $_POST['incident_date'] ?? date('Y-m-d');
    $description      = htmlspecialchars(strip_tags(trim($_POST['description'] ?? '')));
    $report_type      = ($person_type === 'Teacher') ? 'Student vs Teacher' : 'Student vs Student';
    $status           = 'Pending';
    $submission_date  = date('Y-m-d H:i:s');

    /* ── Validate required fields ── */
    $errors = [];
    if (empty($person_reported))  $errors[] = 'Person being reported is required.';
    if (empty($report_category))  $errors[] = 'Report category is required.';
    if (empty($description))      $errors[] = 'Incident description is required.';
    if (strlen($description) < 20) $errors[] = 'Please provide a more detailed description (at least 20 characters).';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    /* ── Handle optional file upload ── */
    $evidence_path = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $file_type = mime_content_type($_FILES['evidence']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX.']);
            exit;
        }
        if ($_FILES['evidence']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size must not exceed 5MB.']);
            exit;
        }
        $upload_dir = '../uploads/report_evidence/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext        = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
        $safe_name  = 'evidence_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['evidence']['tmp_name'], $upload_dir . $safe_name)) {
            $evidence_path = $safe_name;
        }
    }

    /* ── Insert into database ── */
    $conn = db_connect();

    /* Auto-generate Report ID: RPT-YYYYMMDD-NNNN */
    $today  = date('Ymd');
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM reports WHERE DATE(submission_date) = CURDATE()");
    $row    = $result->fetch_assoc();
    $seq    = str_pad((int)$row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    $report_id = "RPT-{$today}-{$seq}";

    $stmt = $conn->prepare("
        INSERT INTO reports
            (report_id, student_id, reporter_name, reporter_grade,
             person_reported, person_type, report_category, report_type,
             incident_date, description, evidence_path, status, submission_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'sissssssssss s',
        $report_id,
        $student_id,
        $reporter_name,
        $reporter_grade,
        $person_reported,
        $person_type,
        $report_category,
        $report_type,
        $incident_date,
        $description,
        $evidence_path,
        $status,
        $submission_date
    );

    // Fix bind string (no space)
    $stmt->close();
    $stmt = $conn->prepare("
        INSERT INTO reports
            (report_id, student_id, reporter_name, reporter_grade,
             person_reported, person_type, report_category, report_type,
             incident_date, description, evidence_path, status, submission_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sissssssssss s',
        $report_id,
        $student_id,
        $reporter_name,
        $reporter_grade,
        $person_reported,
        $person_type,
        $report_category,
        $report_type,
        $incident_date,
        $description,
        $evidence_path,
        $status,
        $submission_date
    );

    // Correct bind_param without space
    $stmt->close();
    $stmt = $conn->prepare("
        INSERT INTO reports
        (report_id,student_id,reporter_name,reporter_grade,person_reported,person_type,
         report_category,report_type,incident_date,description,evidence_path,status,submission_date)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        'sisssssssssss',
        $report_id,
        $student_id,
        $reporter_name,
        $reporter_grade,
        $person_reported,
        $person_type,
        $report_category,
        $report_type,
        $incident_date,
        $description,
        $evidence_path,
        $status,
        $submission_date
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        echo json_encode([
            'success'   => true,
            'message'   => 'Your report has been submitted successfully.',
            'report_id' => $report_id
        ]);
    } else {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save report. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Report – Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── RESET / BASE ───────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f0f2f5;
            color: #2d3748;
            min-height: 100vh;
        }

        /* ─── LAYOUT WRAPPER ────────────────────────── */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 32px 28px;
            transition: margin-left .3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 72px 16px 24px;
            }
        }

        /* ─── PAGE HEADER ───────────────────────────── */
        .page-header {
            margin-bottom: 28px;
        }

        .page-header .breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: #a0aec0;
            margin-bottom: 8px;
        }

        .page-header .breadcrumb a {
            color: #8a9a5b;
            text-decoration: none;
            font-weight: 600;
        }

        .page-header .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-header h1 {
            font-size: 1.55rem;
            font-weight: 800;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #e53e3e;
        }

        .page-header p {
            margin-top: 5px;
            font-size: 0.88rem;
            color: #718096;
        }

        /* ─── CARDS ──────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06), 0 4px 16px rgba(0, 0, 0, .06);
            padding: 28px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid #edf2f7;
        }

        .card-title i {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .icon-red {
            background: #fff5f5;
            color: #e53e3e;
        }

        .icon-blue {
            background: #ebf8ff;
            color: #3182ce;
        }

        .icon-green {
            background: #f0fff4;
            color: #38a169;
        }

        .icon-orange {
            background: #fffaf0;
            color: #dd6b20;
        }

        /* ─── REPORTER INFO STRIP ────────────────────── */
        .reporter-strip {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #f7fafc;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .reporter-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #8a9a5b;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .reporter-strip .info h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2d3748;
        }

        .reporter-strip .info p {
            font-size: 0.78rem;
            color: #718096;
            margin-top: 2px;
        }

        .reporter-strip .verified-badge {
            margin-left: auto;
            background: #f0fff4;
            color: #38a169;
            font-size: 0.73rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ─── FORM GRID ──────────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-grid .full {
                grid-column: 1;
            }
        }

        /* ─── FORM FIELDS ────────────────────────────── */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: .05em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-label .required {
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 0.88rem;
            color: #2d3748;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #8a9a5b;
            box-shadow: 0 0 0 3px rgba(138, 154, 91, .15);
        }

        .form-control:disabled {
            background: #f7fafc;
            color: #718096;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        select.form-control {
            cursor: pointer;
        }

        .form-hint {
            font-size: 0.74rem;
            color: #a0aec0;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ─── CATEGORY GROUP HEADERS IN SELECT ── */
        /* (styled via JS replacement with visible labels) */

        /* ─── INCIDENT TYPE TOGGLE ───────────────────── */
        .type-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .type-option {
            position: relative;
        }

        .type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .type-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all .2s;
            font-size: 0.87rem;
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
        }

        .type-option label i {
            font-size: 1.1rem;
        }

        .type-option input:checked+label {
            border-color: #8a9a5b;
            background: #f6f8f0;
            color: #4a6320;
        }

        .type-option label:hover {
            border-color: #cbd5e0;
            background: #edf2f7;
        }

        /* ─── FILE UPLOAD ────────────────────────────── */
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            position: relative;
        }

        .file-upload-area:hover,
        .file-upload-area.dragover {
            border-color: #8a9a5b;
            background: #f6f8f0;
        }

        .file-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-area .upload-icon {
            font-size: 2rem;
            color: #cbd5e0;
            margin-bottom: 8px;
        }

        .file-upload-area p {
            font-size: 0.83rem;
            color: #718096;
        }

        .file-upload-area strong {
            color: #4a5568;
        }

        .file-preview {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #38a169;
        }

        /* ─── CATEGORY SELECT WRAPPER ────────────────── */
        .category-select-wrapper {
            position: relative;
        }

        /* ─── SUBMIT BUTTON ──────────────────────────── */
        .submit-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }

        .submit-note {
            font-size: 0.78rem;
            color: #a0aec0;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            max-width: 400px;
        }

        .submit-note i {
            color: #8a9a5b;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: #e53e3e;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            letter-spacing: .01em;
        }

        .btn-submit:hover {
            background: #c53030;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(229, 62, 62, .35);
        }

        .btn-submit:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 12px 20px;
            background: #edf2f7;
            color: #4a5568;
            border: none;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        /* ─── ALERT / TOAST ──────────────────────────── */
        .alert {
            display: none;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            align-items: flex-start;
            gap: 10px;
        }

        .alert.show {
            display: flex;
        }

        .alert i {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .alert-success {
            background: #f0fff4;
            color: #276749;
            border: 1px solid #c6f6d5;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        /* ─── SUCCESS OVERLAY ────────────────────────── */
        .success-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .success-overlay.show {
            display: flex;
        }

        .success-box {
            background: #fff;
            border-radius: 20px;
            padding: 40px 36px;
            text-align: center;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .2);
            animation: popIn .3s ease;
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(.88);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .success-box .check-ring {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #f0fff4;
            border: 3px solid #c6f6d5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 2rem;
            color: #38a169;
        }

        .success-box h2 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .success-box p {
            font-size: 0.87rem;
            color: #718096;
            line-height: 1.65;
        }

        .success-box .report-id-display {
            background: #f7fafc;
            border-radius: 8px;
            padding: 8px 16px;
            font-family: monospace;
            font-size: 1rem;
            font-weight: 700;
            color: #2d3748;
            margin: 14px auto 20px;
            display: inline-block;
        }

        .btn-done {
            padding: 11px 28px;
            background: #8a9a5b;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-done:hover {
            background: #7a8a4c;
        }

        /* ─── GUIDELINES CARD ────────────────────────── */
        .guideline-list {
            list-style: none;
        }

        .guideline-list li {
            display: flex;
            gap: 10px;
            padding: 8px 0;
            font-size: 0.84rem;
            color: #4a5568;
            border-bottom: 1px solid #f7fafc;
            line-height: 1.5;
        }

        .guideline-list li:last-child {
            border-bottom: none;
        }

        .guideline-list li i {
            color: #8a9a5b;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ─── CATEGORY BADGE ─────────────────────────── */
        .cat-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .cat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 0.73rem;
            font-weight: 600;
            cursor: default;
        }

        .cat-student {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .cat-teacher {
            background: #fff5f5;
            color: #c53030;
        }

        /* ─── CHAR COUNTER ───────────────────────────── */
        .char-counter {
            font-size: 0.73rem;
            color: #a0aec0;
            text-align: right;
            margin-top: 2px;
        }

        .char-counter.warn {
            color: #d69e2e;
        }

        .char-counter.limit {
            color: #e53e3e;
        }
    </style>
</head>

<body>

    <div class="layout">
        <!-- ═══ NAVIGATION (loaded from Student_nav.php) ═══ -->
        <div id="nav-container"></div>

        <!-- ═══ MAIN CONTENT ═══ -->
        <main class="main-content">

            <!-- Breadcrumb + Title -->
            <div class="page-header">
                <div class="breadcrumb">
                    <a href="Dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
                    <i class="fa-solid fa-chevron-right" style="font-size:.65rem;"></i>
                    <span>Submit Report</span>
                </div>
                <h1><i class="fa-solid fa-triangle-exclamation"></i> Incident Report Form</h1>
                <p>Fill in the details below to submit a confidential report to the school principal.</p>
            </div>

            <!-- Alert bar (success / error) -->
            <div class="alert alert-error" id="formAlert">
                <i class="fa-solid fa-circle-xmark"></i>
                <span id="alertMsg"></span>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start;">

                <!-- ════ LEFT: Form ════ -->
                <div>
                    <!-- Who is filing -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fa-solid fa-user icon-blue"></i> Reporter Information
                        </div>

                        <div class="reporter-strip">
                            <div class="reporter-avatar" id="avatarInitials"><?= strtoupper(substr($student_name, 0, 2)) ?></div>
                            <div class="info">
                                <h4><?= $student_name ?></h4>
                                <p><i class="fa-solid fa-graduation-cap"></i> <?= $grade_sec ?: 'Grade &amp; Section not set' ?></p>
                            </div>
                            <div class="verified-badge"><i class="fa-solid fa-shield-check"></i> Verified Student</div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Reporter Name</label>
                                <input type="text" class="form-control" value="<?= $student_name ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Grade &amp; Section</label>
                                <input type="text" class="form-control" value="<?= $grade_sec ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Incident details -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fa-solid fa-file-circle-exclamation icon-red"></i> Incident Details
                        </div>

                        <!-- Report type toggle -->
                        <div class="form-group" style="margin-bottom:20px;">
                            <label class="form-label">Who Are You Reporting? <span class="required">*</span></label>
                            <div class="type-toggle">
                                <div class="type-option">
                                    <input type="radio" name="person_type" id="type_student" value="Student" checked>
                                    <label for="type_student">
                                        <i class="fa-solid fa-user" style="color:#3182ce;"></i>
                                        A Classmate / Student
                                    </label>
                                </div>
                                <div class="type-option">
                                    <input type="radio" name="person_type" id="type_teacher" value="Teacher">
                                    <label for="type_teacher">
                                        <i class="fa-solid fa-chalkboard-user" style="color:#e53e3e;"></i>
                                        A Teacher
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <!-- Person reported -->
                            <div class="form-group full">
                                <label class="form-label" for="person_reported">
                                    Full Name of Person Being Reported <span class="required">*</span>
                                </label>
                                <input type="text" id="person_reported" name="person_reported"
                                    class="form-control" placeholder="e.g. Juan dela Cruz" maxlength="120">
                                <span class="form-hint"><i class="fa-solid fa-info-circle"></i> Write the full name as accurately as possible.</span>
                            </div>

                            <!-- Category -->
                            <div class="form-group full">
                                <label class="form-label" for="report_category">
                                    Report Category <span class="required">*</span>
                                </label>
                                <select id="report_category" name="report_category" class="form-control">
                                    <option value="">— Select a category —</option>
                                </select>
                                <span class="form-hint"><i class="fa-solid fa-info-circle"></i>
                                    Choose the category that best describes the incident.</span>
                            </div>

                            <!-- Date of incident -->
                            <div class="form-group">
                                <label class="form-label" for="incident_date">
                                    Date of Incident <span class="required">*</span>
                                </label>
                                <input type="date" id="incident_date" name="incident_date"
                                    class="form-control" max="<?= date('Y-m-d') ?>">
                            </div>

                            <!-- Placeholder for alignment -->
                            <div class="form-group" style="align-self:end;">
                                <div style="background:#fffbeb; border-radius:9px; padding:10px 14px;
                                        border-left:3px solid #d69e2e; font-size:0.8rem; color:#744210;">
                                    <i class="fa-solid fa-clock" style="margin-right:6px;"></i>
                                    Your report will be submitted with today's date and time.
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="form-group full">
                                <label class="form-label" for="description">
                                    Detailed Description of Incident <span class="required">*</span>
                                </label>
                                <textarea id="description" name="description" class="form-control"
                                    rows="6" maxlength="2000"
                                    placeholder="Describe what happened in detail. Include: where it happened, what was said or done, who witnessed it, and how long it has been occurring..."></textarea>
                                <div class="char-counter" id="descCounter">0 / 2000</div>
                            </div>
                        </div>
                    </div>

                    <!-- Evidence -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fa-solid fa-paperclip icon-orange"></i> Evidence (Optional)
                        </div>
                        <div class="file-upload-area" id="dropZone">
                            <input type="file" id="evidence" name="evidence"
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                            <p><strong>Click to upload</strong> or drag and drop a file here</p>
                            <p style="margin-top:4px; font-size:0.75rem;">JPG, PNG, GIF, PDF, DOC, DOCX · Max 5MB</p>
                            <div class="file-preview" id="filePreview"></div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="card">
                        <div class="submit-section">
                            <div class="submit-note">
                                <i class="fa-solid fa-lock"></i>
                                Your report is confidential. Only the principal and authorized school staff can view its contents.
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn-reset" onclick="resetForm()">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </button>
                                <button type="button" class="btn-submit" id="submitBtn" onclick="submitReport()">
                                    <i class="fa-solid fa-paper-plane"></i> Submit Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════ RIGHT: Sidebar info ════ -->
                <div>
                    <!-- Guidelines -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fa-solid fa-circle-info icon-blue"></i> Reporting Guidelines
                        </div>
                        <ul class="guideline-list">
                            <li><i class="fa-solid fa-check-circle"></i> Only submit reports for real incidents you have witnessed or experienced.</li>
                            <li><i class="fa-solid fa-check-circle"></i> Provide as much detail as possible — dates, places, and names.</li>
                            <li><i class="fa-solid fa-check-circle"></i> False or malicious reports may result in disciplinary action.</li>
                            <li><i class="fa-solid fa-check-circle"></i> All reports are treated confidentially by the administration.</li>
                            <li><i class="fa-solid fa-check-circle"></i> You may attach evidence such as screenshots or photos to support your report.</li>
                            <li><i class="fa-solid fa-check-circle"></i> The principal will review and act on your report within 3–5 school days.</li>
                        </ul>
                    </div>

                    <!-- Student-to-student categories quick ref -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fa-solid fa-tags icon-orange"></i> Report Categories
                        </div>
                        <p style="font-size:0.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                               color:#a0aec0; margin-bottom:8px;">Student vs Student</p>
                        <div class="cat-badge-row" style="margin-bottom:14px;">
                            <span class="cat-badge cat-student">Bullying</span>
                            <span class="cat-badge cat-student">Cyberbullying</span>
                            <span class="cat-badge cat-student">Physical Assault</span>
                            <span class="cat-badge cat-student">Sexual Harassment</span>
                            <span class="cat-badge cat-student">Extortion</span>
                            <span class="cat-badge cat-student">Theft / Stealing</span>
                            <span class="cat-badge cat-student">Vandalism</span>
                            <span class="cat-badge cat-student">Discrimination</span>
                        </div>
                        <p style="font-size:0.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                               color:#a0aec0; margin-bottom:8px;">Student vs Teacher</p>
                        <div class="cat-badge-row">
                            <span class="cat-badge cat-teacher">Corporal Punishment</span>
                            <span class="cat-badge cat-teacher">Verbal Abuse</span>
                            <span class="cat-badge cat-teacher">Sexual Harassment</span>
                            <span class="cat-badge cat-teacher">Unfair Grading</span>
                            <span class="cat-badge cat-teacher">Psychological Abuse</span>
                            <span class="cat-badge cat-teacher">Unjust Requirements</span>
                            <span class="cat-badge cat-teacher">Negligence</span>
                            <span class="cat-badge cat-teacher">Solicitation</span>
                        </div>
                    </div>

                    <!-- Emergency contacts -->
                    <div class="card" style="background: linear-gradient(135deg,#fff5f5 0%,#fed7d7 100%); border: 1px solid #fed7d7;">
                        <div class="card-title" style="border-bottom-color:#fbb6b6;">
                            <i class="fa-solid fa-phone-volume icon-red"></i>
                            <span style="color:#c53030;">Need Immediate Help?</span>
                        </div>
                        <p style="font-size:0.82rem; color:#742a2a; margin-bottom:12px; line-height:1.6;">
                            If you or someone is in immediate danger, contact school staff or call emergency services right away.
                        </p>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div style="background:#fff; border-radius:8px; padding:10px 12px; font-size:0.82rem;">
                                <div style="font-weight:700; color:#2d3748;"><i class="fa-solid fa-school" style="color:#e53e3e; margin-right:6px;"></i>School Guidance Office</div>
                                <div style="color:#718096; margin-top:2px;">Visit the Guidance Office or call the front desk.</div>
                            </div>
                            <div style="background:#fff; border-radius:8px; padding:10px 12px; font-size:0.82rem;">
                                <div style="font-weight:700; color:#2d3748;"><i class="fa-solid fa-shield-halved" style="color:#3182ce; margin-right:6px;"></i>Emergency: 911</div>
                                <div style="color:#718096; margin-top:2px;">For life-threatening situations call 911 immediately.</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /grid -->
        </main>
    </div>

    <!-- ════ SUCCESS OVERLAY ════ -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-box">
            <div class="check-ring"><i class="fa-solid fa-check"></i></div>
            <h2>Report Submitted!</h2>
            <p>Your report has been securely submitted to the school principal. You will be notified once it is reviewed.</p>
            <div class="report-id-display" id="successReportId">#RPT-XXXXXXXX-0001</div>
            <p style="font-size:0.78rem; color:#a0aec0; margin-bottom:20px;">Keep this reference number for your records.</p>
            <button class="btn-done" onclick="closeSuccess()">
                <i class="fa-solid fa-house"></i> Back to Dashboard
            </button>
        </div>
    </div>

    <!-- ════ SCRIPTS ════ -->
    <script>
        /* ── Load student navigation ── */
        fetch('Student_nav.php')
            .then(r => r.text())
            .then(html => {
                document.getElementById('nav-container').innerHTML = html;
                /* highlight "Report" as active */
                document.querySelectorAll('.sidebar .menu-item').forEach(item => {
                    const href = (item.getAttribute('data-nav-href') || item.getAttribute('href') || '');
                    if (href.includes('student_report')) {
                        document.querySelectorAll('.sidebar .menu-item').forEach(i => i.classList.remove('active'));
                        item.classList.add('active');
                    }
                    /* wire up data-nav-href as real href */
                    if (item.hasAttribute('data-nav-href') && !item.getAttribute('href')) {
                        item.setAttribute('href', item.getAttribute('data-nav-href'));
                    }
                });
            })
            .catch(err => console.error('Nav load error:', err));

        /* ── Category definitions ── */
        const categories = {
            Student: [
                'Bullying (Physical / Verbal / Relational)',
                'Cyberbullying',
                'Physical Assault or Violence',
                'Sexual Harassment',
                'Extortion',
                'Theft or Stealing',
                'Vandalism of Personal Property',
                'Discrimination (Gender / Religion / Disability)'
            ],
            Teacher: [
                'Corporal Punishment',
                'Verbal Abuse / Public Humiliation',
                'Sexual Harassment / Grooming',
                'Unfair Grading Practices',
                'Emotional / Psychological Abuse',
                'Unjust Academic Requirements',
                'Negligence / Dereliction of Duty',
                'Solicitation / Grade Exchange'
            ]
        };

        /* ── Populate category dropdown based on selected person type ── */
        function populateCategories(type) {
            const sel = document.getElementById('report_category');
            const groupLabel = type === 'Teacher' ? 'Student vs Teacher' : 'Student vs Student';
            sel.innerHTML = `<option value="">— Select a category —</option>
        <optgroup label="${groupLabel}">
            ${categories[type].map(c => `<option value="${c}">${c}</option>`).join('')}
        </optgroup>`;
        }

        /* ── Wire type-toggle radio buttons ── */
        document.querySelectorAll('input[name="person_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                populateCategories(this.value);
            });
        });

        /* ── Default load ── */
        populateCategories('Student');

        /* ── Character counter ── */
        const descTA = document.getElementById('description');
        const counter = document.getElementById('descCounter');
        descTA.addEventListener('input', function() {
            const len = this.value.length;
            const max = 2000;
            counter.textContent = `${len} / ${max}`;
            counter.className = 'char-counter' + (len > 1800 ? (len >= max ? ' limit' : ' warn') : '');
        });

        /* ── File upload preview ── */
        const fileInput = document.getElementById('evidence');
        const filePreview = document.getElementById('filePreview');
        const dropZone = document.getElementById('dropZone');

        fileInput.addEventListener('change', function() {
            if (this.files[0]) {
                filePreview.textContent = '📎 ' + this.files[0].name + ' (' + (this.files[0].size / 1024).toFixed(1) + ' KB)';
            }
        });

        ['dragover', 'dragenter'].forEach(ev => dropZone.addEventListener(ev, e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        }));
        ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.remove('dragover')));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            if (e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                filePreview.textContent = '📎 ' + e.dataTransfer.files[0].name;
            }
        });

        /* ── Form validation & submission ── */
        function showAlert(type, msg) {
            const el = document.getElementById('formAlert');
            const msgEl = document.getElementById('alertMsg');
            el.className = `alert alert-${type} show`;
            msgEl.textContent = msg;
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideAlert() {
            document.getElementById('formAlert').className = 'alert';
        }

        function submitReport() {
            hideAlert();

            const personReported = document.getElementById('person_reported').value.trim();
            const category = document.getElementById('report_category').value;
            const incidentDate = document.getElementById('incident_date').value;
            const description = document.getElementById('description').value.trim();
            const personType = document.querySelector('input[name="person_type"]:checked').value;

            /* Client-side validation */
            if (!personReported) {
                showAlert('error', 'Please enter the name of the person you are reporting.');
                return;
            }
            if (!category) {
                showAlert('error', 'Please select a report category.');
                return;
            }
            if (!incidentDate) {
                showAlert('error', 'Please select the date of the incident.');
                return;
            }
            if (!description) {
                showAlert('error', 'Please describe the incident in detail.');
                return;
            }
            if (description.length < 20) {
                showAlert('error', 'Description is too short. Please provide more detail (at least 20 characters).');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';

            const formData = new FormData();
            formData.append('action', 'submit_report');
            formData.append('reporter_name', '<?= addslashes($student_name) ?>');
            formData.append('reporter_grade', '<?= addslashes($grade_sec) ?>');
            formData.append('person_reported', personReported);
            formData.append('person_type', personType);
            formData.append('report_category', category);
            formData.append('incident_date', incidentDate);
            formData.append('description', description);

            const file = document.getElementById('evidence').files[0];
            if (file) formData.append('evidence', file);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successReportId').textContent = data.report_id;
                        document.getElementById('successOverlay').classList.add('show');
                        resetForm();
                    } else {
                        showAlert('error', data.message || 'Submission failed. Please try again.');
                    }
                })
                .catch(() => showAlert('error', 'A network error occurred. Please check your connection.'))
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Report';
                });
        }

        function resetForm() {
            document.getElementById('person_reported').value = '';
            document.getElementById('report_category').value = '';
            document.getElementById('incident_date').value = '';
            document.getElementById('description').value = '';
            document.getElementById('filePreview').textContent = '';
            document.querySelector('input[name="person_type"][value="Student"]').checked = true;
            populateCategories('Student');
            counter.textContent = '0 / 2000';
            counter.className = 'char-counter';
            hideAlert();
        }

        function closeSuccess() {
            document.getElementById('successOverlay').classList.remove('show');
            window.location.href = 'Dashboard.php';
        }

        /* ── Set today as default incident date ── */
        document.getElementById('incident_date').value = new Date().toISOString().split('T')[0];
    </script>

</body>

</html>