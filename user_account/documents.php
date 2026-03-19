<?php

/**
 * Student Documents
 * Document request and management page for students
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';

// Include database connection
include '../db_connection.php';

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Buyoan National High School</title>
    <link rel="stylesheet" href="../admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <style>
        /* Documents Base Styles */
        :root {
            --primary-color: #8a9a5b;
            --primary-dark: #6d7a48;
            --secondary-color: #10b981;
            --sidebar-width: 280px;
            --topbar-height: 70px;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* sidebar loaded from Student_nav.php */

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            background: var(--bg-light);
        }

        /* Top Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .header-left p {
            color: #64748b;
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-date {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            color: #64748b;
            font-size: 14px;
        }

        /* Request Button */
        .btn-request {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-request:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(138, 154, 91, 0.4);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-content h3 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stat-content p {
            font-size: 13px;
            color: #64748b;
        }

        /* Documents Table */
        .documents-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .documents-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .documents-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .documents-filters {
            display: flex;
            gap: 12px;
        }

        .filter-select {
            padding: 10px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            color: #64748b;
            background: white;
            cursor: pointer;
            outline: none;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
        }

        .documents-table {
            width: 100%;
            border-collapse: collapse;
        }

        .documents-table th {
            padding: 16px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .documents-table td {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #1e293b;
        }

        .documents-table tr:last-child td {
            border-bottom: none;
        }

        .documents-table tr:hover {
            background: #f8fafc;
        }

        .document-type {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .document-name {
            font-weight: 600;
        }

        .document-date {
            color: #64748b;
            font-size: 13px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.processing {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-badge.ready {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge.completed {
            background: rgba(138, 154, 91, 0.15);
            color: var(--primary-dark);
        }

        .action-btn {
            padding: 8px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(138, 154, 91, 0.3);
        }

        /* Empty State */
        .empty-state {
            padding: 60px 24px;
            text-align: center;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: #94a3b8;
        }

        .empty-state h4 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .documents-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .header-left h1 {
                font-size: 22px;
            }

            .dashboard-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .documents-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <h1>Documents</h1>
                <p>Request and track your school documents</p>
            </div>
            <div class="header-right">
                <div class="header-date">
                    <i class="fas fa-calendar"></i>
                    <span id="currentDate"></span>
                </div>
                <button class="btn-request" onclick="openRequestModal()">
                    <i class="fas fa-plus"></i>
                    Request Document
                </button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>2</h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-content">
                    <h3>1</h3>
                    <p>Processing</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>5</h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>8</h3>
                    <p>Total Requests</p>
                </div>
            </div>
        </div>

        <!-- Documents Card -->
        <div class="documents-card">
            <div class="documents-header">
                <h3>Document Requests</h3>
                <div class="documents-filters">
                    <select class="filter-select">
                        <option>All Status</option>
                        <option>Pending</option>
                        <option>Processing</option>
                        <option>Ready</option>
                        <option>Completed</option>
                    </select>
                    <select class="filter-select">
                        <option>All Types</option>
                        <option>Form 137</option>
                        <option>Good Moral</option>
                        <option>Transcript</option>
                        <option>Enrollment</option>
                    </select>
                </div>
            </div>

            <table class="documents-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="document-type">
                                <div class="document-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="document-name">Form 137</div>
                                    <div class="document-date">School Year 2024-2025</div>
                                </div>
                            </div>
                        </td>
                        <td>Oct 20, 2025</td>
                        <td>
                            <span class="status-badge pending">
                                <i class="fas fa-clock"></i>
                                Pending
                            </span>
                        </td>
                        <td>
                            <button class="action-btn">View Details</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="document-type">
                                <div class="document-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="document-name">Good Moral Certificate</div>
                                    <div class="document-date">Current School Year</div>
                                </div>
                            </div>
                        </td>
                        <td>Oct 18, 2025</td>
                        <td>
                            <span class="status-badge processing">
                                <i class="fas fa-spinner"></i>
                                Processing
                            </span>
                        </td>
                        <td>
                            <button class="action-btn">View Details</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="document-type">
                                <div class="document-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="document-name">Enrollment Form</div>
                                    <div class="document-date">SY 2024-2025</div>
                                </div>
                            </div>
                        </td>
                        <td>Sep 15, 2025</td>
                        <td>
                            <span class="status-badge ready">
                                <i class="fas fa-check"></i>
                                Ready
                            </span>
                        </td>
                        <td>
                            <button class="action-btn primary">Download</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="document-type">
                                <div class="document-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="document-name">Form 137</div>
                                    <div class="document-date">SY 2023-2024</div>
                                </div>
                            </div>
                        </td>
                        <td>Aug 10, 2025</td>
                        <td>
                            <span class="status-badge completed">
                                <i class="fas fa-check-circle"></i>
                                Completed
                            </span>
                        </td>
                        <td>
                            <button class="action-btn">View</button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="document-type">
                                <div class="document-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="document-name">Good Moral Certificate</div>
                                    <div class="document-date">SY 2023-2024</div>
                                </div>
                            </div>
                        </td>
                        <td>Jul 20, 2025</td>
                        <td>
                            <span class="status-badge completed">
                                <i class="fas fa-check-circle"></i>
                                Completed
                            </span>
                        </td>
                        <td>
                            <button class="action-btn">View</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Open request modal (placeholder)
        function openRequestModal() {
            alert('Document request modal would open here');
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

                    var current = window.location.pathname.split('/').pop() || 'documents.php';
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