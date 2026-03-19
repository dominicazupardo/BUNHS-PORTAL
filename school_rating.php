<?php

/**
 * school_rating.php
 * Buyoan National High School — School Rating Page
 *
 * Features:
 *  - Dynamic ratings fetched from MySQL (school_ratings table)
 *  - One rating per user (identified by student_id via session)
 *  - Secure PDO prepared statements; all inputs validated
 *  - Modern card-based UI with Plus Jakarta Sans, star interactions,
 *    progress-bar distribution, and smooth animations
 *
 * Requires: db_connection.php (PDO $pdo OR MySQLi $conn)
 *
 * Table DDL (run once):
 * ---------------------------------------------------------------
 * CREATE TABLE IF NOT EXISTS school_ratings (
 *     id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *     user_identifier VARCHAR(255) NOT NULL UNIQUE,
 *     rating          TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
 *     feedback        TEXT         NULL,
 *     created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * ---------------------------------------------------------------
 */

session_start();

/* ── 1. DATABASE CONNECTION ─────────────────────────────────────────── */
// Adjust path to match your project structure
$db_path = '../db_connection.php';
if (file_exists($db_path)) {
    include $db_path;
} else {
    // Fallback: try same directory
    include 'db_connection.php';
}

/*
 * Normalise connection: this file uses PDO internally.
 * If db_connection.php exposes $conn (MySQLi), we wrap it.
 * If it exposes $pdo (PDO), we use that directly.
 */
$pdo = null;
if (isset($conn) && $conn instanceof mysqli) {
    // Re-create a PDO handle from MySQLi credentials isn't possible without
    // the raw credentials, so we fall back to MySQLi prepared statements.
    $use_mysqli = true;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $use_mysqli = false;
} else {
    // Last resort: define $use_mysqli = false and let queries fail gracefully
    $use_mysqli = false;
}

/* ── 2. SESSION / USER IDENTIFICATION ──────────────────────────────── */
$student_id   = $_SESSION['student_id']   ?? null;
$student_name = $_SESSION['student_name'] ?? 'Guest';
$is_logged_in = !empty($student_id);

// Use student_id as the unique identifier stored in school_ratings
$user_identifier = $is_logged_in ? $student_id : null;

/* ── 3. HELPER: execute query (MySQLi or PDO) ───────────────────────── */
/**
 * Safe query helper – returns the raw result object/statement.
 * Usage: sr_query($sql, $types, $params) where $types is the
 * MySQLi bind-param string (e.g. "si") and $params is an array.
 */
function sr_query(string $sql, string $types = '', array $params = [])
{
    global $conn, $pdo, $use_mysqli;

    if ($use_mysqli) {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) return false;
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params ?: null);
        return $stmt;
    }
}

/* ── 4. CREATE TABLE IF NOT EXISTS ──────────────────────────────────── */
$create_sql = "
    CREATE TABLE IF NOT EXISTS school_ratings (
        id              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_identifier VARCHAR(255) NOT NULL UNIQUE,
        rating          TINYINT      NOT NULL,
        feedback        TEXT         NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
sr_query($create_sql);

/* ── 5. POST HANDLER ────────────────────────────────────────────────── */
$message      = '';
$message_type = ''; // 'success' | 'error' | 'warning'
$already_rated = false;

if ($is_logged_in) {
    // Check existing rating
    if ($use_mysqli) {
        $chk = sr_query(
            "SELECT id, rating FROM school_ratings WHERE user_identifier = ? LIMIT 1",
            "s",
            [$user_identifier]
        );
        $existing = $chk ? $chk->get_result()->fetch_assoc() : null;
    } else {
        $chk = sr_query(
            "SELECT id, rating FROM school_ratings WHERE user_identifier = ? LIMIT 1",
            '',
            [$user_identifier]
        );
        $existing = $chk ? $chk->fetch(PDO::FETCH_ASSOC) : null;
    }
    $already_rated = !empty($existing);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {

    // CSRF-lite: verify referer (optional, strengthen with tokens in production)
    $rating   = filter_input(INPUT_POST, 'rating',   FILTER_VALIDATE_INT);
    $feedback = trim(filter_input(INPUT_POST, 'feedback', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // Validate
    if (!$rating || $rating < 1 || $rating > 5) {
        $message      = 'Please select a valid star rating (1–5).';
        $message_type = 'error';
    } elseif (mb_strlen($feedback) > 1000) {
        $message      = 'Feedback must be 1,000 characters or less.';
        $message_type = 'error';
    } elseif ($already_rated) {
        $message      = 'You have already submitted your rating. Thank you for your feedback!';
        $message_type = 'warning';
    } else {
        // Insert
        $feedback_val = $feedback !== '' ? $feedback : null;
        if ($use_mysqli) {
            $ins = sr_query(
                "INSERT INTO school_ratings (user_identifier, rating, feedback) VALUES (?, ?, ?)",
                "sis",
                [$user_identifier, $rating, $feedback_val]
            );
            $ok = $ins && $ins->affected_rows > 0;
        } else {
            $ins = sr_query(
                "INSERT INTO school_ratings (user_identifier, rating, feedback) VALUES (?, ?, ?)",
                '',
                [$user_identifier, $rating, $feedback_val]
            );
            $ok = $ins && $ins->rowCount() > 0;
        }

        if ($ok) {
            $message      = 'Thank you! Your rating has been submitted successfully.';
            $message_type = 'success';
            $already_rated = true;
        } else {
            $message      = 'Something went wrong. Please try again.';
            $message_type = 'error';
        }
    }
}

/* ── 6. FETCH AGGREGATE DATA ─────────────────────────────────────────── */
$avg_rating  = 0.0;
$total_count = 0;
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($use_mysqli) {
    $agg = sr_query("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM school_ratings");
    if ($agg) {
        $row         = $agg->get_result()->fetch_assoc();
        $avg_rating  = round((float)($row['avg_r'] ?? 0), 1);
        $total_count = (int)($row['total'] ?? 0);
    }
    $dist = sr_query("SELECT rating, COUNT(*) AS cnt FROM school_ratings GROUP BY rating");
    if ($dist) {
        $res = $dist->get_result();
        while ($r = $res->fetch_assoc()) {
            $distribution[(int)$r['rating']] = (int)$r['cnt'];
        }
    }
} else {
    $agg = sr_query("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM school_ratings");
    if ($agg) {
        $row         = $agg->fetch(PDO::FETCH_ASSOC);
        $avg_rating  = round((float)($row['avg_r'] ?? 0), 1);
        $total_count = (int)($row['total'] ?? 0);
    }
    $dist = sr_query("SELECT rating, COUNT(*) AS cnt FROM school_ratings GROUP BY rating");
    if ($dist) {
        foreach ($dist->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $distribution[(int)$r['rating']] = (int)$r['cnt'];
        }
    }
}

/* ── 7. FETCH RECENT REVIEWS (latest 6 with feedback) ──────────────── */
$reviews = [];
if ($use_mysqli) {
    $rev = sr_query(
        "SELECT user_identifier, rating, feedback, created_at
           FROM school_ratings
          WHERE feedback IS NOT NULL AND feedback <> ''
          ORDER BY created_at DESC LIMIT 6"
    );
    if ($rev) {
        $r = $rev->get_result();
        while ($row = $r->fetch_assoc()) $reviews[] = $row;
    }
} else {
    $rev = sr_query(
        "SELECT user_identifier, rating, feedback, created_at
           FROM school_ratings
          WHERE feedback IS NOT NULL AND feedback <> ''
          ORDER BY created_at DESC LIMIT 6"
    );
    if ($rev) $reviews = $rev->fetchAll(PDO::FETCH_ASSOC);
}

/* ── 8. HELPER FUNCTIONS ─────────────────────────────────────────────── */
/**
 * Render filled/half/empty stars as HTML.
 * $value float 0–5, $size CSS font-size string
 */
function render_stars(float $value, string $size = '20px'): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($value >= $i) {
            $html .= '<i class="fas fa-star" style="color:#f59e0b;font-size:' . $size . '"></i>';
        } elseif ($value >= $i - 0.5) {
            $html .= '<i class="fas fa-star-half-alt" style="color:#f59e0b;font-size:' . $size . '"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color:#d1d5db;font-size:' . $size . '"></i>';
        }
    }
    return $html;
}

/** Anonymise a student ID for public display */
function anonymise_id(string $id): string
{
    if (strlen($id) <= 4) return str_repeat('*', strlen($id));
    return substr($id, 0, 2) . str_repeat('*', max(strlen($id) - 4, 3)) . substr($id, -2);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>School Rating — Buyoan National High School</title>
    <meta name="description" content="Rate Buyoan National High School and share your feedback.">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <!-- Font Awesome (icons) -->
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <!-- Bootstrap (matches existing system) -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS (existing layout / nav / breadcrumbs) -->
    <link href="assets/css/main.css" rel="stylesheet">

    <!-- ─── Rating Page Styles ────────────────────────────────────────── -->
    <style>
        /* ── Design tokens ─────────────────────────── */
        :root {
            --sr-green: #3b975e;
            --sr-green-light: #d1fae5;
            --sr-green-dark: #276643;
            --sr-gold: #f59e0b;
            --sr-gold-light: #fef3c7;
            --sr-slate: #1e293b;
            --sr-muted: #64748b;
            --sr-border: #e2e8f0;
            --sr-bg: #f8fafc;
            --sr-card-bg: #ffffff;
            --sr-radius: 16px;
            --sr-radius-sm: 10px;
            --sr-shadow: 0 4px 24px rgba(0, 0, 0, .07);
            --sr-shadow-lg: 0 12px 48px rgba(0, 0, 0, .12);
            --sr-font: 'Plus Jakarta Sans', 'Poppins', system-ui, sans-serif;
            --sr-transition: all .25s cubic-bezier(.4, 0, .2, 1);
        }

        /* ── Base ─────────────────────────────────── */
        .school-rating-page *,
        .school-rating-page *::before,
        .school-rating-page *::after {
            box-sizing: border-box;
        }

        .school-rating-page {
            font-family: var(--sr-font);
            background: var(--sr-bg);
        }

        /* ── Section wrapper ──────────────────────── */
        .sr-section {
            padding: 64px 0 80px;
        }

        /* ── Summary card ─────────────────────────── */
        .sr-summary-card {
            background: var(--sr-card-bg);
            border-radius: var(--sr-radius);
            box-shadow: var(--sr-shadow);
            border: 1px solid var(--sr-border);
            padding: 40px 36px;
            transition: var(--sr-transition);
        }

        .sr-summary-card:hover {
            box-shadow: var(--sr-shadow-lg);
            transform: translateY(-2px);
        }

        /* Big rating number */
        .sr-avg-number {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 800;
            color: var(--sr-slate);
            line-height: 1;
            letter-spacing: -2px;
        }

        .sr-avg-number span {
            font-size: .4em;
            font-weight: 500;
            color: var(--sr-muted);
            letter-spacing: 0;
        }

        .sr-stars-row {
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 8px 0 4px;
        }

        .sr-review-count {
            font-size: .9rem;
            color: var(--sr-muted);
            font-weight: 500;
        }

        /* Distribution bars */
        .sr-dist-row {
            display: grid;
            grid-template-columns: 28px 1fr 44px;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .sr-dist-label {
            font-size: .78rem;
            font-weight: 700;
            color: var(--sr-muted);
            text-align: right;
        }

        .sr-dist-track {
            height: 8px;
            background: var(--sr-border);
            border-radius: 99px;
            overflow: hidden;
        }

        .sr-dist-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--sr-gold), #fbbf24);
            border-radius: 99px;
            transition: width .8s cubic-bezier(.4, 0, .2, 1);
            width: 0;
            /* animated via JS */
        }

        .sr-dist-pct {
            font-size: .75rem;
            font-weight: 600;
            color: var(--sr-muted);
            text-align: right;
        }

        /* ── Form card ─────────────────────────────── */
        .sr-form-card {
            background: var(--sr-card-bg);
            border-radius: var(--sr-radius);
            box-shadow: var(--sr-shadow);
            border: 1px solid var(--sr-border);
            padding: 36px;
            position: relative;
            overflow: hidden;
        }

        .sr-form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--sr-green), #6ee7a0);
        }

        .sr-form-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--sr-slate);
            margin-bottom: 4px;
        }

        .sr-form-subtitle {
            font-size: .85rem;
            color: var(--sr-muted);
            margin-bottom: 24px;
        }

        /* ── Star picker ─────────────────────────── */
        .sr-star-picker {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin: 20px 0 8px;
        }

        .sr-star-picker input[type="radio"] {
            display: none;
        }

        .sr-star-picker label {
            cursor: pointer;
            font-size: 2.2rem;
            color: #d1d5db;
            transition: color .15s, transform .15s;
            line-height: 1;
        }

        /* Hover: colour all stars up to hovered */
        .sr-star-picker label:hover,
        .sr-star-picker label:hover~label {
            /* trick via flex-direction:row-reverse */
        }

        /* We use row-reverse + sibling selectors */
        .sr-star-picker {
            flex-direction: row-reverse;
        }

        .sr-star-picker label:hover,
        .sr-star-picker label:hover~label {
            color: var(--sr-gold);
            transform: scale(1.15);
        }

        .sr-star-picker input[type="radio"]:checked~label {
            color: var(--sr-gold);
        }

        .sr-star-label {
            text-align: center;
            font-size: .82rem;
            color: var(--sr-muted);
            min-height: 18px;
            margin-bottom: 16px;
            font-weight: 500;
            transition: var(--sr-transition);
        }

        /* ── Textarea ────────────────────────────── */
        .sr-textarea {
            width: 100%;
            border: 1.5px solid var(--sr-border);
            border-radius: var(--sr-radius-sm);
            padding: 12px 14px;
            font-family: var(--sr-font);
            font-size: .9rem;
            color: var(--sr-slate);
            resize: vertical;
            min-height: 100px;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
            background: #fafafa;
        }

        .sr-textarea:focus {
            border-color: var(--sr-green);
            box-shadow: 0 0 0 3px rgba(59, 151, 94, .12);
            background: #fff;
        }

        .sr-char-counter {
            text-align: right;
            font-size: .75rem;
            color: var(--sr-muted);
            margin-top: 4px;
        }

        /* ── Submit button ───────────────────────── */
        .sr-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--sr-green);
            color: #fff;
            border: none;
            border-radius: var(--sr-radius-sm);
            padding: 13px 28px;
            font-family: var(--sr-font);
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--sr-transition);
            width: 100%;
            justify-content: center;
            margin-top: 18px;
            position: relative;
            overflow: hidden;
        }

        .sr-btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0);
            transition: background .2s;
        }

        .sr-btn:hover:not(:disabled)::after {
            background: rgba(255, 255, 255, .1);
        }

        .sr-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 151, 94, .35);
        }

        .sr-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .sr-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            opacity: .75;
        }

        .sr-btn .sr-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: sr-spin .6s linear infinite;
        }

        @keyframes sr-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Alert messages ──────────────────────── */
        .sr-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-radius: var(--sr-radius-sm);
            padding: 14px 16px;
            font-size: .88rem;
            font-weight: 500;
            margin-bottom: 20px;
            animation: sr-fadeIn .35s ease;
        }

        @keyframes sr-fadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sr-alert-success {
            background: var(--sr-green-light);
            color: var(--sr-green-dark);
            border: 1px solid #6ee7a0;
        }

        .sr-alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .sr-alert-warning {
            background: var(--sr-gold-light);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .sr-alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        /* ── Already-rated state ─────────────────── */
        .sr-already-rated {
            text-align: center;
            padding: 16px 0 4px;
        }

        .sr-rated-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--sr-green-light);
            color: var(--sr-green-dark);
            border-radius: 99px;
            padding: 8px 18px;
            font-size: .85rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        /* ── Guest notice ────────────────────────── */
        .sr-guest-notice {
            background: #f1f5f9;
            border: 1.5px dashed var(--sr-border);
            border-radius: var(--sr-radius-sm);
            padding: 20px;
            text-align: center;
        }

        .sr-guest-notice p {
            font-size: .9rem;
            color: var(--sr-muted);
            margin-bottom: 12px;
        }

        .sr-login-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--sr-slate);
            color: #fff;
            border-radius: 8px;
            padding: 9px 20px;
            font-size: .88rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--sr-transition);
        }

        .sr-login-btn:hover {
            background: var(--sr-green);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ── Reviews list ────────────────────────── */
        .sr-reviews-section {
            margin-top: 60px;
        }

        .sr-section-heading {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--sr-slate);
            margin-bottom: 6px;
        }

        .sr-section-sub {
            font-size: .9rem;
            color: var(--sr-muted);
            margin-bottom: 28px;
        }

        .sr-review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .sr-review-card {
            background: var(--sr-card-bg);
            border: 1px solid var(--sr-border);
            border-radius: var(--sr-radius);
            padding: 22px;
            transition: var(--sr-transition);
        }

        .sr-review-card:hover {
            box-shadow: var(--sr-shadow);
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .sr-review-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .sr-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--sr-green), #6ee7a0);
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: .95rem;
            color: #fff;
            flex-shrink: 0;
        }

        .sr-review-meta strong {
            display: block;
            font-size: .88rem;
            font-weight: 700;
            color: var(--sr-slate);
        }

        .sr-review-meta span {
            font-size: .75rem;
            color: var(--sr-muted);
        }

        .sr-review-stars {
            display: flex;
            gap: 2px;
            margin-bottom: 10px;
        }

        .sr-review-text {
            font-size: .88rem;
            color: #475569;
            line-height: 1.65;
        }

        /* ── No-reviews state ────────────────────── */
        .sr-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--sr-muted);
        }

        .sr-empty i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: .4;
        }

        /* ── Divider ─────────────────────────────── */
        .sr-divider {
            border: none;
            border-top: 1px solid var(--sr-border);
            margin: 28px 0;
        }

        /* ── Responsive ──────────────────────────── */
        @media (max-width: 768px) {

            .sr-form-card,
            .sr-summary-card {
                padding: 24px 18px;
            }

            .sr-section {
                padding: 40px 0 60px;
            }

            .sr-star-picker label {
                font-size: 1.8rem;
            }
        }

        /* ── Page-title override to use our font ─── */
        .page-title .heading h1,
        .page-title .heading p {
            font-family: var(--sr-font) !important;
        }
    </style>
</head>

<body class="campus-facilities-page school-rating-page">

    <!-- ── Header (unchanged from existing system) ────────────────────── -->
    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

            <a href="index.php" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="Bagong Pilipinas" class="me-2"
                    style="height:85px;width:auto;border-radius:20px;">
                <img src="assets/img/DepED logo circle.png" alt="DepEd Logo" class="me-2"
                    style="height:85px;width:auto;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2"
                    style="height:85px;width:auto;border-radius:50px;">
                <h4 class="sitename mb-0">Buyoan National High School</h4>
            </a>

            <div id="nav-placeholder"></div>

        </div>
    </header>

    <main class="main">

        <!-- ── Page title / breadcrumb ───────────────────────────────── -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">School Rating</h1>
                            <p class="mb-0">
                                Share your experience and help us improve.
                                Your honest feedback matters to our school community.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="index.php">Home</a></li>
                        <li class="current">School Rating</li>
                    </ol>
                </div>
            </nav>
        </div>

        <!-- ── Main Rating Section ───────────────────────────────────── -->
        <section id="school-rating" class="sr-section">
            <div class="container">

                <div class="row g-4">

                    <!-- ════ LEFT: Summary ════════════════════════════════ -->
                    <div class="col-lg-5">
                        <div class="sr-summary-card h-100">

                            <!-- Overall score -->
                            <div class="text-center mb-4">
                                <div class="sr-avg-number">
                                    <?= number_format($avg_rating, 1) ?>
                                    <span>/ 5</span>
                                </div>
                                <div class="sr-stars-row justify-content-center">
                                    <?= render_stars($avg_rating, '22px') ?>
                                </div>
                                <p class="sr-review-count">
                                    <i class="fas fa-users me-1"></i>
                                    <?= number_format($total_count) ?>
                                    <?= $total_count === 1 ? 'rating' : 'ratings' ?> submitted
                                </p>
                            </div>

                            <hr class="sr-divider">

                            <!-- Distribution bars -->
                            <div>
                                <p class="mb-3" style="font-size:.82rem;font-weight:700;color:var(--sr-muted);text-transform:uppercase;letter-spacing:.06em;">
                                    Rating Breakdown
                                </p>
                                <?php foreach ([5, 4, 3, 2, 1] as $star):
                                    $count = $distribution[$star];
                                    $pct   = $total_count > 0 ? round(($count / $total_count) * 100) : 0;
                                ?>
                                    <div class="sr-dist-row" data-pct="<?= $pct ?>">
                                        <span class="sr-dist-label"><?= $star ?>★</span>
                                        <div class="sr-dist-track">
                                            <div class="sr-dist-fill" style="width:0%"></div>
                                        </div>
                                        <span class="sr-dist-pct"><?= $pct ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($total_count === 0): ?>
                                <p class="text-center mt-4" style="font-size:.85rem;color:var(--sr-muted);">
                                    <i class="far fa-comment-dots me-1"></i>
                                    No ratings yet — be the first!
                                </p>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- ════ RIGHT: Form ══════════════════════════════════ -->
                    <div class="col-lg-7">
                        <div class="sr-form-card">

                            <!-- ── Message alert ──────────────────────── -->
                            <?php if ($message): ?>
                                <div class="sr-alert sr-alert-<?= htmlspecialchars($message_type) ?>" role="alert">
                                    <i class="fas <?= $message_type === 'success' ? 'fa-check-circle'
                                                        : ($message_type === 'warning' ? 'fa-exclamation-triangle'
                                                            : 'fa-times-circle') ?> mt-1 flex-shrink-0"></i>
                                    <span><?= htmlspecialchars($message) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!$is_logged_in): ?>
                                <!-- Guest state -->
                                <p class="sr-form-title">Rate Our School</p>
                                <p class="sr-form-subtitle">Sign in to share your rating and help the community.</p>
                                <div class="sr-guest-notice">
                                    <i class="fas fa-lock mb-3" style="font-size:2rem;color:#94a3b8;display:block;"></i>
                                    <p>You need to be logged in as a student to submit a rating.</p>
                                    <a href="#" class="sr-login-btn btn-login">
                                        <i class="fas fa-sign-in-alt"></i> Sign In to Rate
                                    </a>
                                </div>

                            <?php elseif ($already_rated): ?>
                                <!-- Already rated state -->
                                <p class="sr-form-title">Your Rating</p>
                                <p class="sr-form-subtitle">Thanks for contributing to our school community!</p>
                                <div class="sr-already-rated">
                                    <div class="sr-rated-badge">
                                        <i class="fas fa-check-circle"></i>
                                        Rating Submitted
                                    </div>
                                    <p style="font-size:.88rem;color:var(--sr-muted);">
                                        You have already submitted your rating.<br>
                                        Thank you for your feedback!
                                    </p>
                                    <div class="sr-stars-row justify-content-center mt-2">
                                        <?= render_stars($existing['rating'] ?? 5, '28px') ?>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- Rating form -->
                                <p class="sr-form-title">Rate Our School</p>
                                <p class="sr-form-subtitle">
                                    Hello, <strong><?= htmlspecialchars(explode(' ', $student_name)[0]) ?></strong>!
                                    How would you rate your overall school experience?
                                </p>

                                <form id="sr-form" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" novalidate>

                                    <!-- Star picker (flex row-reverse so CSS sibling trick works) -->
                                    <div class="sr-star-picker" role="group" aria-label="Star rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" id="sr-star-<?= $i ?>" name="rating" value="<?= $i ?>"
                                                <?= (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : '' ?>>
                                            <label for="sr-star-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                                                <i class="fas fa-star" aria-hidden="true"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>

                                    <p class="sr-star-label" id="sr-star-label">Click a star to rate</p>

                                    <!-- Feedback textarea -->
                                    <label for="sr-feedback" style="font-size:.85rem;font-weight:600;color:var(--sr-slate);display:block;margin-bottom:6px;">
                                        <i class="far fa-comment-dots me-1"></i>
                                        Feedback <span style="color:var(--sr-muted);font-weight:400;">(optional)</span>
                                    </label>
                                    <textarea
                                        id="sr-feedback"
                                        name="feedback"
                                        class="sr-textarea"
                                        placeholder="Share your experience, suggestions, or anything you'd like the school to know…"
                                        maxlength="1000"
                                        rows="4"
                                        aria-describedby="sr-char-count"><?= htmlspecialchars($_POST['feedback'] ?? '') ?></textarea>
                                    <p class="sr-char-counter" id="sr-char-count">
                                        <span id="sr-char-num">0</span> / 1000
                                    </p>

                                    <!-- Submit -->
                                    <button type="submit" class="sr-btn" id="sr-submit-btn" disabled>
                                        <span class="sr-spinner" id="sr-spinner"></span>
                                        <i class="fas fa-paper-plane" id="sr-btn-icon"></i>
                                        <span id="sr-btn-text">Select a Rating First</span>
                                    </button>

                                </form>
                            <?php endif; ?>

                        </div><!-- /sr-form-card -->
                    </div>

                </div><!-- /row -->

                <!-- ── Recent Reviews ──────────────────────────────────── -->
                <?php if (!empty($reviews)): ?>
                    <div class="sr-reviews-section">
                        <p class="sr-section-heading">Recent Feedback</p>
                        <p class="sr-section-sub">
                            What students are saying about Buyoan National High School.
                        </p>

                        <div class="sr-review-grid">
                            <?php foreach ($reviews as $rev):
                                $initials = strtoupper(substr($rev['user_identifier'], 0, 2));
                                $date_fmt = date('M j, Y', strtotime($rev['created_at']));
                            ?>
                                <div class="sr-review-card">
                                    <div class="sr-review-header">
                                        <div class="sr-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
                                        <div class="sr-review-meta">
                                            <strong><?= htmlspecialchars(anonymise_id($rev['user_identifier'])) ?></strong>
                                            <span><?= htmlspecialchars($date_fmt) ?></span>
                                        </div>
                                    </div>
                                    <div class="sr-review-stars">
                                        <?= render_stars((float)$rev['rating'], '14px') ?>
                                    </div>
                                    <p class="sr-review-text">
                                        <?= nl2br(htmlspecialchars(mb_strimwidth($rev['feedback'], 0, 220, '…'))) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($total_count === 0): ?>
                    <div class="sr-reviews-section sr-empty">
                        <i class="far fa-comment-dots d-block"></i>
                        <p>No feedback yet. Be the first to share your thoughts!</p>
                    </div>
                <?php endif; ?>

            </div>
        </section>

    </main>

    <!-- Footer Placeholder -->
    <div id="footer-placeholder"></div>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">

        <div class="container">

            <div class="footer-main">
                <div class="row align-items-start">

                    <div class="col-lg-5">
                        <div class="brand-section">
                            <a href="index.html" class="logo d-flex align-items-center mb-4">
                                <span class="sitename">Buyoan National HighSchool</span>
                            </a>
                            <p class="brand-description">Crafting exceptional digital experiences through thoughtful design and innovative solutions that elevate your brand presence.</p>

                            <div class="contact-info mt-5">
                                <div class="contact-item">
                                    <i class="bi bi-geo-alt"></i>
                                    <span>123 Creative Boulevard, Design District, NY 10012</span>
                                </div>
                                <div class="contact-item">
                                    <i class="bi bi-telephone"></i>
                                    <span>+1 (555) 987-6543</span>
                                </div>
                                <div class="contact-item">
                                    <i class="bi bi-envelope"></i>
                                    <span><a href="mailto:hello@example.com">hello@example.com</a></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="footer-nav-wrapper">
                            <div class="row">

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Studio</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Our Story</a>
                                            <a href="#">Design Process</a>
                                            <a href="#">Portfolio</a>
                                            <a href="#">Case Studies</a>
                                            <a href="#">Awards</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Services</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Brand Identity</a>
                                            <a href="#">Web Design</a>
                                            <a href="#">Mobile Apps</a>
                                            <a href="#">Digital Strategy</a>
                                            <a href="#">Consultation</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Resources</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Design Blog</a>
                                            <a href="#">Style Guide</a>
                                            <a href="#">Free Assets</a>
                                            <a href="#">Tutorials</a>
                                            <a href="#">Inspiration</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Connect</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Start Project</a>
                                            <a href="#">Schedule Call</a>
                                            <a href="#">Join Newsletter</a>
                                            <a href="#">Follow Updates</a>
                                            <a href="#">Partnership</a>
                                        </nav>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="footer-social">
                <div class="row align-items-center">

                    <div class="col-lg-6">
                        <div class="newsletter-section">
                            <h5>Stay Inspired</h5>
                            <p>Subscribe to receive design insights and creative inspiration delivered monthly.</p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="social-section">
                            <div class="social-links">
                                <a href="#" aria-label="Dribbble" class="social-link">
                                    <i class="bi bi-dribbble"></i>
                                    <span>Dribbble</span>
                                </a>
                                <a href="#" aria-label="Behance" class="social-link">
                                    <i class="bi bi-behance"></i>
                                    <span>Behance</span>
                                </a>
                                <a href="#" aria-label="Instagram" class="social-link">
                                    <i class="bi bi-instagram"></i>
                                    <span>Instagram</span>
                                </a>
                                <a href="#" aria-label="LinkedIn" class="social-link">
                                    <i class="bi bi-linkedin"></i>
                                    <span>LinkedIn</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div class="footer-bottom">
            <div class="container">
                <div class="bottom-content">
                    <div class="row align-items-center">

                        <div class="col-lg-6">
                            <div class="copyright">
                                <p>© <span class="sitename">MyWebsite</span>. All rights reserved.</p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="legal-links">
                                <a href="#">Privacy Policy</a>
                                <a href="#">Terms of Service</a>
                                <a href="#">Cookie Policy</a>
                                <div class="credits">
                                    <!-- All the links in the footer should remain intact. -->
                                    <!-- You can delete the links only if you've purchased the pro version. -->
                                    <!-- Licensing information: https://bootstrapmade.com/license/ -->
                                    <!-- Purchase the pro version with working PHP/AJAX contact form: [buy-url] -->
                                    Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        </footer>

        <!-- Scroll Top -->
        <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-up-short"></i>
        </a>

        <!-- Preloader -->
        <div id="preloader"></div>

        <!-- ── Vendor JS (unchanged from existing system) ──────────────────── -->
        <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
        <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
        <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
        <script src="assets/js/main.js"></script>

        <!-- ── Navigation loader (unchanged) ──────────────────────────────── -->
        <script>
            fetch('nav.php')
                .then(r => r.text())
                .then(html => {
                    document.getElementById('nav-placeholder').innerHTML = html;
                })
                .catch(err => console.error('[NavLoader]', err));
        </script>

        <!-- ── Modals loader (unchanged) ──────────────────────────────────── -->
        <script>
            fetch('footer.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('footer-placeholder').innerHTML = data;
                })
                .catch(error => console.error('Error loading footer:', error));
        </script>

        <script>
            fetch('modals.php')
                .then(r => r.text())
                .then(html => {
                    document.body.insertAdjacentHTML('beforeend', html);
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');
                    if (loginBtn) loginBtn.addEventListener('click', e => {
                        e.preventDefault();
                        new bootstrap.Modal(document.getElementById('loginModal')).show();
                    });
                    if (signupBtn) signupBtn.addEventListener('click', e => {
                        e.preventDefault();
                        new bootstrap.Modal(document.getElementById('signupModal')).show();
                    });
                })
                .catch(err => console.error('[ModalLoader]', err));
        </script>

        <!-- ── Rating Page JS ──────────────────────────────────────────────── -->
        <script>
            (function() {
                'use strict';

                /* ── Animate distribution bars on page load ── */
                function animateBars() {
                    document.querySelectorAll('.sr-dist-row').forEach(function(row) {
                        var pct = parseInt(row.dataset.pct, 10) || 0;
                        var fill = row.querySelector('.sr-dist-fill');
                        if (fill) {
                            // Delay slightly so transition is visible
                            requestAnimationFrame(function() {
                                setTimeout(function() {
                                    fill.style.width = pct + '%';
                                }, 120);
                            });
                        }
                    });
                }

                /* ── Star picker labels ─────────────────────── */
                var starLabels = {
                    1: '😞  Poor — Needs major improvement',
                    2: '😐  Fair — Below expectations',
                    3: '🙂  Good — Meets expectations',
                    4: '😊  Very Good — Exceeded expectations',
                    5: '🌟  Excellent — Outstanding experience!'
                };

                var radios = document.querySelectorAll('input[name="rating"]');
                var labelEl = document.getElementById('sr-star-label');
                var submitBtn = document.getElementById('sr-submit-btn');
                var btnText = document.getElementById('sr-btn-text');
                var btnIcon = document.getElementById('sr-btn-icon');

                function onRatingChange(val) {
                    if (labelEl) labelEl.textContent = starLabels[val] || '';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.background = 'var(--sr-green)';
                    }
                    if (btnText) btnText.textContent = 'Submit My Rating';
                    if (btnIcon) {
                        btnIcon.className = 'fas fa-paper-plane';
                    }
                }

                radios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        onRatingChange(parseInt(this.value, 10));
                    });
                    // Restore state on page reload with selected value
                    if (radio.checked) onRatingChange(parseInt(radio.value, 10));
                });

                /* ── Character counter ─────────────────────── */
                var textarea = document.getElementById('sr-feedback');
                var charNum = document.getElementById('sr-char-num');

                if (textarea && charNum) {
                    function updateCount() {
                        var len = textarea.value.length;
                        charNum.textContent = len;
                        charNum.style.color = len > 900 ? '#ef4444' : (len > 750 ? '#f59e0b' : '');
                    }
                    textarea.addEventListener('input', updateCount);
                    updateCount();
                }

                /* ── Form submission: show spinner ─────────── */
                var form = document.getElementById('sr-form');
                var spinner = document.getElementById('sr-spinner');

                if (form) {
                    form.addEventListener('submit', function() {
                        if (submitBtn && !submitBtn.disabled) {
                            submitBtn.disabled = true;
                            if (spinner) spinner.style.display = 'block';
                            if (btnIcon) btnIcon.style.display = 'none';
                            if (btnText) btnText.textContent = 'Submitting…';
                        }
                    });
                }

                /* ── Kick off animations ───────────────────── */
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', animateBars);
                } else {
                    animateBars();
                }
            }());
        </script>

</body>

</html>