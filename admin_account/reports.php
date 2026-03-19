<?php
/* ═══════════════════════════════════════════════════════════════
   reports.php  –  Admin Report Management Dashboard (Dynamic)
   All stats, tables, category counts, resolved list, and
   progress bars are driven live from the MySQL database.
   ═══════════════════════════════════════════════════════════════ */

session_start();

/* ── Guard: admin / principal only ──
   Uncomment and adjust the block below once your login system is in place.
   Leave it commented out during development so the page loads directly.

if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
*/

/* ── DB config ── */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bunhs_db_important'); // ← update this if still wrong

/* ── Connect (graceful – page renders even without a DB) ── */
$conn     = null;
$db_error = null;
$conn_attempt = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn_attempt->connect_errno) {
    $db_error = 'Cannot connect to MySQL: ' . $conn_attempt->connect_error;
} else {
    // Find the real DB name if the configured one doesn't exist
    $db_list = [];
    $db_res  = $conn_attempt->query("SHOW DATABASES LIKE '%bunhs%'");
    if ($db_res) {
        while ($r = $db_res->fetch_row()) {
            $db_list[] = $r[0];
        }
    }
    // Try the configured name first, then fall back to first match
    $db_to_use = DB_NAME;
    if (!in_array($db_to_use, $db_list) && !empty($db_list)) {
        $db_to_use = $db_list[0];
    }
    if ($conn_attempt->select_db($db_to_use)) {
        $conn = $conn_attempt;
        $conn->set_charset('utf8mb4');
        if ($db_to_use !== DB_NAME) {
            $db_error = 'notice:Found DB as "' . $db_to_use . '" (configured name "' . DB_NAME . '" not found). Update DB_NAME in reports.php to remove this notice.';
        }
    } else {
        $db_error = 'Database "' . DB_NAME . '" not found. Available bunhs databases: ' . (empty($db_list) ? 'none matched — check DB_NAME in reports.php' : implode(', ', $db_list));
    }
}

/* ══════════════════════════════════════════════════════════════
   AJAX HANDLER  –  status updates from modal action buttons
   ══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database not connected.']);
        exit;
    }

    $ajax_action = $_POST['ajax_action'];
    $report_id   = trim($_POST['report_id'] ?? '');

    $status_map = [
        'set_investigating' => 'Investigating',
        'set_resolved'      => 'Resolved',
        'set_archived'      => 'Archived',
        'set_pending'       => 'Pending',
    ];

    if (isset($status_map[$ajax_action]) && $report_id !== '') {
        $new_status = $status_map[$ajax_action];
        $stmt = $conn->prepare('UPDATE reports SET status = ? WHERE report_id = ?');
        $stmt->bind_param('ss', $new_status, $report_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'new_status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action or missing report ID.']);
    }

    $conn->close();
    exit;
}

/* ══════════════════════════════════════════════════════════════
   FILTER / SEARCH  (optional GET params)
   ══════════════════════════════════════════════════════════════ */
$filter_status = trim($_GET['status']   ?? '');
$filter_type   = trim($_GET['type']     ?? '');
$filter_search = trim($_GET['search']   ?? '');

$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($filter_status !== '') {
    $where_parts[] = 'status = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_status;
}
if ($filter_type !== '') {
    $where_parts[] = 'report_type = ?';
    $bind_types   .= 's';
    $bind_vals[]   = $filter_type;
}
if ($filter_search !== '') {
    $where_parts[] = '(reporter_name LIKE ? OR person_reported LIKE ? OR report_category LIKE ? OR report_id LIKE ?)';
    $like = '%' . $filter_search . '%';
    $bind_types   .= 'ssss';
    $bind_vals[]   = $like;
    $bind_vals[]   = $like;
    $bind_vals[]   = $like;
    $bind_vals[]   = $like;
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

/* ── Fetch filtered reports ── */
$reports        = [];
$stat_total     = 0;
$stat_s2s = 0;
$stat_teacher   = 0;
$stat_pending   = 0;
$stat_investing = 0;
$stat_resolved  = 0;
$stat_archived  = 0;
$stat_this_week = 0;
$cat_counts     = [];
$resolved_list  = [];
$pbar_s2s = $pbar_teacher = $pbar_pending = $pbar_resolved = 0;

if ($conn) {
    $sql  = "SELECT * FROM reports $where_sql ORDER BY submission_date DESC";
    $stmt = $conn->prepare($sql);
    if ($bind_types) {
        $stmt->bind_param($bind_types, ...$bind_vals);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();

    /* ── Global stats ── */
    $stats_row = $conn->query("
        SELECT
            COUNT(*)                                                            AS total,
            SUM(report_type = 'Student vs Student')                             AS s2s,
            SUM(report_type = 'Student vs Teacher')                             AS teacher,
            SUM(status = 'Pending')                                             AS pending,
            SUM(status = 'Investigating')                                       AS investigating,
            SUM(status = 'Resolved')                                            AS resolved,
            SUM(status = 'Archived')                                            AS archived,
            SUM(DATE(submission_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))   AS this_week
        FROM reports
    ")->fetch_assoc();

    $stat_total     = (int)($stats_row['total']         ?? 0);
    $stat_s2s       = (int)($stats_row['s2s']           ?? 0);
    $stat_teacher   = (int)($stats_row['teacher']       ?? 0);
    $stat_pending   = (int)($stats_row['pending']       ?? 0);
    $stat_investing = (int)($stats_row['investigating'] ?? 0);
    $stat_resolved  = (int)($stats_row['resolved']      ?? 0);
    $stat_archived  = (int)($stats_row['archived']      ?? 0);
    $stat_this_week = (int)($stats_row['this_week']     ?? 0);

    /* ── Category counts ── */
    $cat_res = $conn->query("SELECT report_category, COUNT(*) AS cnt FROM reports GROUP BY report_category ORDER BY cnt DESC");
    while ($row = $cat_res->fetch_assoc()) {
        $cat_counts[$row['report_category']] = (int)$row['cnt'];
    }

    /* ── Resolved list (5 most recent) ── */
    $res_q = $conn->query("
        SELECT report_id, reporter_name, report_category, report_type, submission_date
        FROM reports WHERE status = 'Resolved'
        ORDER BY submission_date DESC LIMIT 5
    ");
    while ($row = $res_q->fetch_assoc()) {
        $resolved_list[] = $row;
    }

    /* ── Progress percentages ── */
    $pbar_s2s      = $stat_total > 0 ? round($stat_s2s      / $stat_total * 100) : 0;
    $pbar_teacher  = $stat_total > 0 ? round($stat_teacher  / $stat_total * 100) : 0;
    $pbar_pending  = $stat_total > 0 ? round($stat_pending  / $stat_total * 100) : 0;
    $pbar_resolved = $stat_total > 0 ? round($stat_resolved / $stat_total * 100) : 0;

    $conn->close();
}

/* ══════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
   ══════════════════════════════════════════════════════════════ */
function badge(string $status): string
{
    $map = [
        'Pending'       => ['badge-pending',  'fa-circle',          'Pending'],
        'Investigating' => ['badge-investing', 'fa-magnifying-glass', 'Investigating'],
        'Resolved'      => ['badge-resolved',  'fa-check',           'Resolved'],
        'Archived'      => ['badge-archived',  'fa-box-archive',     'Archived'],
    ];
    [$cls, $icon, $lbl] = $map[$status] ?? ['badge-pending', 'fa-circle', $status];
    return "<span class=\"badge {$cls}\"><i class=\"fa-solid {$icon}\"></i> {$lbl}</span>";
}

function against_tag(string $type): string
{
    return $type === 'Student vs Teacher'
        ? '<span class="against-tag against-teacher"><i class="fa-solid fa-chalkboard-user"></i> Teacher</span>'
        : '<span class="against-tag against-student"><i class="fa-solid fa-user"></i> Student</span>';
}

function avatar_initials(string $name): string
{
    $parts = explode(' ', trim($name));
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

/* ── Category metadata ── */
$cat_dots = [
    'Bullying (Physical / Verbal / Relational)'      => 'dot-red',
    'Cyberbullying'                                  => 'dot-orange',
    'Physical Assault or Violence'                   => 'dot-red',
    'Sexual Harassment'                              => 'dot-pink',
    'Extortion'                                      => 'dot-yellow',
    'Theft or Stealing'                              => 'dot-orange',
    'Vandalism of Personal Property'                 => 'dot-teal',
    'Discrimination (Gender / Religion / Disability)' => 'dot-purple',
    'Corporal Punishment'                            => 'dot-red',
    'Verbal Abuse / Public Humiliation'              => 'dot-orange',
    'Sexual Harassment / Grooming'                   => 'dot-pink',
    'Unfair Grading Practices'                       => 'dot-blue',
    'Emotional / Psychological Abuse'                => 'dot-purple',
    'Unjust Academic Requirements'                   => 'dot-teal',
    'Negligence / Dereliction of Duty'               => 'dot-yellow',
    'Solicitation / Grade Exchange'                  => 'dot-green',
];

$s2s_categories = [
    'Bullying (Physical / Verbal / Relational)',
    'Cyberbullying',
    'Physical Assault or Violence',
    'Sexual Harassment',
    'Extortion',
    'Theft or Stealing',
    'Vandalism of Personal Property',
    'Discrimination (Gender / Religion / Disability)',
];

$teacher_categories = [
    'Corporal Punishment',
    'Verbal Abuse / Public Humiliation',
    'Sexual Harassment / Grooming',
    'Unfair Grading Practices',
    'Emotional / Psychological Abuse',
    'Unjust Academic Requirements',
    'Negligence / Dereliction of Duty',
    'Solicitation / Grade Exchange',
];

$av_colors = ['av-1', 'av-2', 'av-3', 'av-4', 'av-5', 'av-6', 'av-7', 'av-8', 'av-9'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard – Reports</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reports-dashboard {
            padding: 24px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .reports-dashboard .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .reports-dashboard .page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .reports-dashboard .page-header h1 i {
            color: #e53e3e;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ── Filter Bar ── */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
            align-items: center;
            background: #fff;
            border-radius: 12px;
            padding: 14px 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05), 0 2px 8px rgba(0, 0, 0, .04);
        }

        .filter-bar input,
        .filter-bar select {
            padding: 8px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.84rem;
            color: #2d3748;
            background: #f7fafc;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, background .2s;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: #8a9a5b;
            background: #fff;
        }

        .filter-bar input {
            min-width: 220px;
        }

        .filter-count {
            margin-left: auto;
            font-size: 0.8rem;
            color: #718096;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Buttons ── */
        .btn-rpt {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-rpt-primary {
            background: #e53e3e;
            color: #fff;
        }

        .btn-rpt-primary:hover {
            background: #c53030;
            transform: translateY(-1px);
        }

        .btn-rpt-secondary {
            background: #edf2f7;
            color: #4a5568;
        }

        .btn-rpt-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        .btn-rpt-danger {
            background: #fff5f5;
            color: #c53030;
        }

        .btn-rpt-danger:hover {
            background: #fed7d7;
        }

        .btn-rpt-sm {
            padding: 5px 11px;
            font-size: 0.78rem;
            border-radius: 6px;
        }

        .btn-rpt-view {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .btn-rpt-view:hover {
            background: #bee3f8;
        }

        .btn-rpt-invest {
            background: #fffbeb;
            color: #b7791f;
        }

        .btn-rpt-invest:hover {
            background: #feebc8;
        }

        .btn-rpt-resolve {
            background: #f0fff4;
            color: #276749;
        }

        .btn-rpt-resolve:hover {
            background: #c6f6d5;
        }

        .btn-rpt-archive {
            background: #faf5ff;
            color: #6b46c1;
        }

        .btn-rpt-archive:hover {
            background: #e9d8fd;
        }

        .btn-rpt:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ── Bento Grid ── */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-auto-rows: auto;
            gap: 18px;
        }

        .bento-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06), 0 4px 16px rgba(0, 0, 0, .06);
            transition: box-shadow .25s, transform .25s;
            position: relative;
            overflow: hidden;
        }

        .bento-card:hover {
            box-shadow: 0 4px 24px rgba(0, 0, 0, .12);
            transform: translateY(-2px);
        }

        .bento-stat-total {
            grid-column: span 3;
        }

        .bento-stat-s2s {
            grid-column: span 3;
        }

        .bento-stat-teacher {
            grid-column: span 3;
        }

        .bento-stat-pending {
            grid-column: span 3;
        }

        .bento-recent {
            grid-column: span 8;
        }

        .bento-categories {
            grid-column: span 4;
        }

        .bento-resolved {
            grid-column: span 4;
        }

        .bento-quick-actions {
            grid-column: span 8;
        }

        /* stat cards */
        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 14px;
        }

        .icon-red {
            background: #fff5f5;
            color: #e53e3e;
        }

        .icon-orange {
            background: #fffaf0;
            color: #dd6b20;
        }

        .icon-blue {
            background: #ebf8ff;
            color: #3182ce;
        }

        .icon-yellow {
            background: #fffff0;
            color: #d69e2e;
        }

        .icon-green {
            background: #f0fff4;
            color: #38a169;
        }

        .icon-purple {
            background: #faf5ff;
            color: #805ad5;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1a202c;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stat-change {
            margin-top: 10px;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.up {
            color: #e53e3e;
        }

        .stat-change.neutral {
            color: #718096;
        }

        .bento-card-accent {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            border-radius: 0 16px 0 80px;
            opacity: .06;
        }

        .accent-red {
            background: #e53e3e;
        }

        .accent-orange {
            background: #dd6b20;
        }

        .accent-blue {
            background: #3182ce;
        }

        .accent-yellow {
            background: #d69e2e;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #718096;
            font-size: .9rem;
        }

        /* badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .badge-pending {
            background: #fffbeb;
            color: #b7791f;
        }

        .badge-investing {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .badge-resolved {
            background: #f0fff4;
            color: #276749;
        }

        .badge-archived {
            background: #faf5ff;
            color: #6b46c1;
        }

        /* table */
        .reports-table-wrapper {
            overflow-x: auto;
            margin-top: 4px;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }

        .reports-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #a0aec0;
            border-bottom: 1px solid #edf2f7;
            white-space: nowrap;
        }

        .reports-table tbody tr {
            transition: background .15s;
        }

        .reports-table tbody tr:hover {
            background: #f7fafc;
        }

        .reports-table tbody td {
            padding: 10px 12px;
            color: #4a5568;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        .report-id {
            font-weight: 700;
            color: #2d3748;
            font-family: monospace;
            font-size: 0.8rem;
        }

        .reporter-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .av-1 {
            background: #e53e3e;
        }

        .av-2 {
            background: #dd6b20;
        }

        .av-3 {
            background: #3182ce;
        }

        .av-4 {
            background: #805ad5;
        }

        .av-5 {
            background: #38a169;
        }

        .av-6 {
            background: #d69e2e;
        }

        .av-7 {
            background: #d53f8c;
        }

        .av-8 {
            background: #319795;
        }

        .av-9 {
            background: #2b6cb0;
        }

        .reporter-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.83rem;
        }

        .reporter-grade {
            font-size: 0.73rem;
            color: #a0aec0;
        }

        .against-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .against-student {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .against-teacher {
            background: #fff5f5;
            color: #c53030;
        }

        .action-group {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
            font-size: 0.9rem;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            display: block;
            color: #e2e8f0;
        }

        /* categories */
        .category-group {
            margin-bottom: 16px;
        }

        .category-group-title {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #a0aec0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .category-group-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #edf2f7;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f7fafc;
            font-size: 0.82rem;
            color: #4a5568;
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-item-name {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .category-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .dot-red {
            background: #e53e3e;
        }

        .dot-orange {
            background: #dd6b20;
        }

        .dot-blue {
            background: #3182ce;
        }

        .dot-purple {
            background: #805ad5;
        }

        .dot-green {
            background: #38a169;
        }

        .dot-yellow {
            background: #d69e2e;
        }

        .dot-pink {
            background: #d53f8c;
        }

        .dot-teal {
            background: #319795;
        }

        .category-count {
            font-size: 0.75rem;
            font-weight: 700;
            color: #718096;
            background: #edf2f7;
            padding: 1px 7px;
            border-radius: 10px;
        }

        .category-count.zero {
            color: #cbd5e0;
            background: #f7fafc;
        }

        /* resolved list */
        .resolved-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .resolved-item:last-child {
            border-bottom: none;
        }

        .resolved-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .resolved-info {
            flex: 1;
            min-width: 0;
        }

        .resolved-name {
            font-size: 0.83rem;
            font-weight: 600;
            color: #2d3748;
        }

        .resolved-meta {
            font-size: 0.75rem;
            color: #a0aec0;
            margin-top: 1px;
        }

        /* progress */
        .progress-row {
            margin-bottom: 10px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.77rem;
            color: #718096;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .progress-bar-bg {
            height: 6px;
            border-radius: 10px;
            background: #edf2f7;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width .7s ease;
        }

        .fill-red {
            background: #e53e3e;
        }

        .fill-blue {
            background: #3182ce;
        }

        .fill-yellow {
            background: #d69e2e;
        }

        .fill-green {
            background: #38a169;
        }

        /* quick actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 4px;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 10px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            background: #f7fafc;
            cursor: pointer;
            transition: all .2s;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            text-align: center;
        }

        .quick-action-btn:hover {
            border-color: #cbd5e0;
            background: #edf2f7;
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            font-size: 1.3rem;
        }

        .sub-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 18px;
        }

        .sub-stat-box {
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }

        .sub-stat-val {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .sub-stat-lbl {
            font-size: 0.72rem;
            font-weight: 600;
            margin-top: 2px;
        }

        /* modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            padding: 16px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 18px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .22);
            animation: modalIn .25s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.95) translateY(16px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 22px 24px 0;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .modal-header-info h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0 0 4px;
        }

        .modal-header-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #a0aec0;
        }

        .modal-close {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: none;
            background: #edf2f7;
            color: #4a5568;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: background .15s;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: #e2e8f0;
        }

        .modal-body {
            padding: 20px 24px;
        }

        .modal-divider {
            height: 1px;
            background: #edf2f7;
            margin: 16px 0;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        .modal-field-label {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #a0aec0;
            margin-bottom: 4px;
        }

        .modal-field-value {
            font-size: 0.88rem;
            color: #2d3748;
            font-weight: 500;
            line-height: 1.55;
        }

        .modal-description-box {
            background: #f7fafc;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #4a5568;
            line-height: 1.65;
            border-left: 3px solid #e53e3e;
        }

        .modal-evidence-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #ebf8ff;
            color: #2b6cb0;
            border-radius: 7px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }

        .modal-evidence-link:hover {
            background: #bee3f8;
        }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 20px;
        }

        .modal-footer {
            padding: 0 24px 22px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        /* toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2000;
            background: #1a202c;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.84rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
            transform: translateY(80px);
            opacity: 0;
            transition: transform .35s ease, opacity .35s ease;
            pointer-events: none;
            max-width: 340px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.toast-success {
            border-left: 4px solid #38a169;
        }

        .toast.toast-error {
            border-left: 4px solid #e53e3e;
        }

        /* responsive */
        @media (max-width: 1100px) {

            .bento-stat-total,
            .bento-stat-s2s,
            .bento-stat-teacher,
            .bento-stat-pending {
                grid-column: span 6;
            }

            .bento-recent {
                grid-column: span 12;
            }

            .bento-categories {
                grid-column: span 12;
            }

            .bento-resolved {
                grid-column: span 6;
            }

            .bento-quick-actions {
                grid-column: span 6;
            }
        }

        @media (max-width: 700px) {
            .bento-grid>* {
                grid-column: span 12 !important;
            }

            .quick-actions-grid,
            .sub-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .modal-grid-2 {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                padding: 12px;
            }

            .filter-bar input {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <script>
        fetch('./admin_nav.php')
            .then(r => r.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;
                const mainDiv = document.querySelector('.main');
                const pc = document.querySelector('.page-content');
                if (mainDiv && pc) mainDiv.appendChild(pc);
                initializeDropdowns();
            })
            .catch(e => console.error('Nav error:', e));

        function initializeDropdowns() {
            const isInSubfolder = window.location.pathname.includes('/announcements/');
            const pathPrefix = isInSubfolder ? '../announcements/' : 'announcements/';
            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                item.href = pathPrefix + item.getAttribute('data-page');
            });
            document.querySelectorAll('.dropdown-toggle').forEach(t => {
                t.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const d = this.closest('.dropdown');
                    const was = d.classList.contains('active');
                    document.querySelectorAll('.dropdown').forEach(x => x.classList.remove('active'));
                    if (!was) d.classList.add('active');
                });
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('.dropdown'))
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            });
        }
    </script>

    <section class="page-content">
        <div class="reports-dashboard">

            <!-- DB warning banner -->
            <?php if ($db_error): ?>
                <?php
                $is_notice = str_starts_with($db_error, 'notice:');
                $msg       = $is_notice ? substr($db_error, 7) : $db_error;
                $bg        = $is_notice ? '#f0fff4' : '#fff5f5';
                $border    = $is_notice ? '#c6f6d5' : '#fed7d7';
                $color     = $is_notice ? '#276749' : '#c53030';
                $icon      = $is_notice ? 'fa-circle-check' : 'fa-triangle-exclamation';
                $icolor    = $is_notice ? '#38a169' : '#e53e3e';
                $title     = $is_notice ? 'Database auto-detected.' : 'Database not connected.';
                ?>
                <div style="background:<?= $bg ?>;border:1px solid <?= $border ?>;border-radius:10px;
                padding:12px 18px;margin-bottom:18px;font-size:0.85rem;color:<?= $color ?>;
                display:flex;align-items:center;gap:10px;">
                    <i class="fa-solid <?= $icon ?>" style="color:<?= $icolor ?>;font-size:1.1rem;flex-shrink:0;"></i>
                    <span><strong><?= $title ?></strong> <?= htmlspecialchars($msg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fa-solid fa-triangle-exclamation"></i> Student Report Management</h1>
                <div class="header-actions">
                    <a href="reports.php" class="btn-rpt btn-rpt-secondary">
                        <i class="fa-solid fa-rotate-right"></i> Refresh
                    </a>
                    <button class="btn-rpt btn-rpt-secondary" onclick="exportCSV()">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </button>
                </div>
            </div>

            <!-- Live Filter Bar -->
            <div class="filter-bar">
                <form method="GET" style="display:contents;">
                    <input type="text" name="search"
                        placeholder="🔍  Search by name, ID, or category…"
                        value="<?= htmlspecialchars($filter_search) ?>">

                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Investigating', 'Resolved', 'Archived'] as $s): ?>
                            <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="type">
                        <option value="">All Types</option>
                        <option value="Student vs Student" <?= $filter_type === 'Student vs Student' ? 'selected' : '' ?>>Student vs Student</option>
                        <option value="Student vs Teacher" <?= $filter_type === 'Student vs Teacher' ? 'selected' : '' ?>>Student vs Teacher</option>
                    </select>

                    <button type="submit" class="btn-rpt btn-rpt-primary">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>

                    <?php if ($filter_status || $filter_type || $filter_search): ?>
                        <a href="reports.php" class="btn-rpt btn-rpt-danger">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>

                <span class="filter-count">
                    Showing <strong><?= count($reports) ?></strong>
                    of <strong><?= $stat_total ?></strong> reports
                </span>
            </div>

            <!-- ════ Bento Grid ════ -->
            <div class="bento-grid">

                <!-- 1. Total Reports -->
                <div class="bento-card bento-stat-total">
                    <div class="bento-card-accent accent-red"></div>
                    <div class="stat-icon icon-red"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                    <div class="stat-value"><?= $stat_total ?></div>
                    <div class="stat-label">Total Reports</div>
                    <div class="stat-change up">
                        <i class="fa-solid fa-arrow-up"></i>
                        <?= $stat_this_week ?> new this week
                    </div>
                </div>

                <!-- 2. Student vs Student -->
                <div class="bento-card bento-stat-s2s">
                    <div class="bento-card-accent accent-orange"></div>
                    <div class="stat-icon icon-orange"><i class="fa-solid fa-user-group"></i></div>
                    <div class="stat-value"><?= $stat_s2s ?></div>
                    <div class="stat-label">Student vs Student</div>
                    <div class="stat-change neutral">
                        <i class="fa-solid fa-chart-simple"></i>
                        <?= $stat_total > 0 ? round($stat_s2s / $stat_total * 100) : 0 ?>% of total
                    </div>
                </div>

                <!-- 3. Teacher Misconduct -->
                <div class="bento-card bento-stat-teacher">
                    <div class="bento-card-accent accent-blue"></div>
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="stat-value"><?= $stat_teacher ?></div>
                    <div class="stat-label">Teacher Misconduct</div>
                    <div class="stat-change neutral">
                        <i class="fa-solid fa-chart-simple"></i>
                        <?= $stat_total > 0 ? round($stat_teacher / $stat_total * 100) : 0 ?>% of total
                    </div>
                </div>

                <!-- 4. Pending Review -->
                <div class="bento-card bento-stat-pending">
                    <div class="bento-card-accent accent-yellow"></div>
                    <div class="stat-icon icon-yellow"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="stat-value"><?= $stat_pending ?></div>
                    <div class="stat-label">Pending Review</div>
                    <div class="stat-change up">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <?= $stat_investing ?> under investigation
                    </div>
                </div>

                <!-- 5. Recent Reports Table (large) -->
                <div class="bento-card bento-recent">
                    <p class="card-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> Recent Reports
                        <?php if ($filter_status || $filter_type || $filter_search): ?>
                            <span style="font-size:.74rem;font-weight:500;color:#8a9a5b;margin-left:4px;">
                                — filtered view
                            </span>
                        <?php endif; ?>
                    </p>

                    <div class="reports-table-wrapper">
                        <?php if (empty($reports)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-folder-open"></i>
                                No reports found<?= ($filter_status || $filter_type || $filter_search) ? ' matching your filters.' : '.' ?>
                                <?php if ($filter_status || $filter_type || $filter_search): ?>
                                    <br><a href="reports.php" style="color:#8a9a5b; font-weight:600; font-size:0.82rem;">Clear filters</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <table class="reports-table">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Reporter</th>
                                        <th>Category</th>
                                        <th>Against</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $i => $r):
                                        $av  = $av_colors[$i % count($av_colors)];
                                        $ini = avatar_initials($r['reporter_name']);
                                        $dt  = date('M j, Y', strtotime($r['submission_date']));
                                        $rid = htmlspecialchars($r['report_id']);
                                    ?>
                                        <tr id="row-<?= $rid ?>">
                                            <td><span class="report-id"><?= $rid ?></span></td>
                                            <td>
                                                <div class="reporter-info">
                                                    <div class="avatar <?= $av ?>"><?= htmlspecialchars($ini) ?></div>
                                                    <div>
                                                        <div class="reporter-name"><?= htmlspecialchars($r['reporter_name']) ?></div>
                                                        <div class="reporter-grade"><?= htmlspecialchars($r['reporter_grade']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($r['report_category']) ?></td>
                                            <td><?= against_tag($r['report_type']) ?></td>
                                            <td id="badge-<?= $rid ?>"><?= badge($r['status']) ?></td>
                                            <td><?= $dt ?></td>
                                            <td>
                                                <div class="action-group">
                                                    <button class="btn-rpt btn-rpt-sm btn-rpt-view"
                                                        onclick="openModal(<?= json_encode($r['report_id']) ?>)">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 6. Report Categories (live counts) -->
                <div class="bento-card bento-categories">
                    <p class="card-title"><i class="fa-solid fa-tags"></i> Report Categories</p>

                    <div class="category-group">
                        <div class="category-group-title">Student vs Student</div>
                        <?php foreach ($s2s_categories as $cat):
                            $cnt = $cat_counts[$cat] ?? 0;
                            $dot = $cat_dots[$cat] ?? 'dot-red';
                        ?>
                            <div class="category-item">
                                <span class="category-item-name">
                                    <span class="category-dot <?= $dot ?>"></span>
                                    <?= htmlspecialchars($cat) ?>
                                </span>
                                <span class="category-count <?= $cnt === 0 ? 'zero' : '' ?>"><?= $cnt ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="category-group">
                        <div class="category-group-title">Student vs Teacher</div>
                        <?php foreach ($teacher_categories as $cat):
                            $cnt = $cat_counts[$cat] ?? 0;
                            $dot = $cat_dots[$cat] ?? 'dot-blue';
                        ?>
                            <div class="category-item">
                                <span class="category-item-name">
                                    <span class="category-dot <?= $dot ?>"></span>
                                    <?= htmlspecialchars($cat) ?>
                                </span>
                                <span class="category-count <?= $cnt === 0 ? 'zero' : '' ?>"><?= $cnt ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 7. Resolved Reports (live list + progress bars) -->
                <div class="bento-card bento-resolved">
                    <p class="card-title">
                        <i class="fa-solid fa-circle-check"></i> Resolved Reports
                        <span style="margin-left:auto;font-size:.75rem;background:#f0fff4;
                             color:#276749;padding:2px 8px;border-radius:10px;font-weight:700;">
                            <?= $stat_resolved ?>
                        </span>
                    </p>

                    <?php if (empty($resolved_list)): ?>
                        <div class="empty-state" style="padding:20px 0;">
                            <i class="fa-solid fa-circle-check" style="color:#c6f6d5;"></i>
                            <p>No resolved reports yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($resolved_list as $rv): ?>
                            <div class="resolved-item">
                                <div class="resolved-icon icon-green"><i class="fa-solid fa-check"></i></div>
                                <div class="resolved-info">
                                    <div class="resolved-name">
                                        <?= htmlspecialchars($rv['reporter_name']) ?> — <?= htmlspecialchars($rv['report_category']) ?>
                                    </div>
                                    <div class="resolved-meta">
                                        <?= htmlspecialchars($rv['report_type']) ?> ·
                                        <?= date('M j, Y', strtotime($rv['submission_date'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Live progress bars -->
                    <div style="margin-top:16px;">
                        <div class="progress-row">
                            <div class="progress-label">
                                <span>Student vs Student</span><span><?= $pbar_s2s ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill fill-red" style="width:<?= $pbar_s2s ?>%"></div>
                            </div>
                        </div>
                        <div class="progress-row">
                            <div class="progress-label">
                                <span>Teacher Misconduct</span><span><?= $pbar_teacher ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill fill-blue" style="width:<?= $pbar_teacher ?>%"></div>
                            </div>
                        </div>
                        <div class="progress-row">
                            <div class="progress-label">
                                <span>Pending</span><span><?= $pbar_pending ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill fill-yellow" style="width:<?= $pbar_pending ?>%"></div>
                            </div>
                        </div>
                        <div class="progress-row">
                            <div class="progress-label">
                                <span>Resolved</span><span><?= $pbar_resolved ?>%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill fill-green" style="width:<?= $pbar_resolved ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 8. Quick Actions + sub-stats -->
                <div class="bento-card bento-quick-actions">
                    <p class="card-title"><i class="fa-solid fa-bolt"></i> Quick Actions</p>
                    <div class="quick-actions-grid">
                        <button class="quick-action-btn" style="color:#2b6cb0;" onclick="showToast('Open a report with the View button, then use actions in the modal.','error')">
                            <i class="fa-solid fa-eye" style="color:#3182ce;"></i>View Report
                        </button>
                        <button class="quick-action-btn" style="color:#b7791f;" onclick="showToast('Open a report with the View button first.','error')">
                            <i class="fa-solid fa-magnifying-glass" style="color:#d69e2e;"></i>Mark Investigating
                        </button>
                        <button class="quick-action-btn" style="color:#276749;" onclick="showToast('Open a report with the View button first.','error')">
                            <i class="fa-solid fa-circle-check" style="color:#38a169;"></i>Resolve Report
                        </button>
                        <button class="quick-action-btn" style="color:#6b46c1;" onclick="showToast('Open a report with the View button first.','error')">
                            <i class="fa-solid fa-box-archive" style="color:#805ad5;"></i>Archive Report
                        </button>
                    </div>

                    <!-- Live sub-stats strip -->
                    <div class="sub-stats">
                        <?php
                        $sub_stats = [
                            ['Pending',       $stat_pending,    '#b7791f', '#fffbeb'],
                            ['Investigating', $stat_investing,  '#2b6cb0', '#ebf8ff'],
                            ['Resolved',      $stat_resolved,   '#276749', '#f0fff4'],
                            ['Archived',      $stat_archived,   '#6b46c1', '#faf5ff'],
                        ];
                        foreach ($sub_stats as [$lbl, $val, $clr, $bg]):
                        ?>
                            <div class="sub-stat-box" style="background:<?= $bg ?>;">
                                <div class="sub-stat-val" style="color:<?= $clr ?>;"><?= $val ?></div>
                                <div class="sub-stat-lbl" style="color:<?= $clr ?>;"><?= $lbl ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /bento-grid -->
        </div><!-- /reports-dashboard -->
    </section>

    <!-- ════ View Report Modal ════ -->
    <div class="modal-overlay" id="reportModal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-header-info">
                    <h2 id="modal-title">Report Details</h2>
                    <p id="modal-id">—</p>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="modal-grid-2">
                    <div class="modal-field">
                        <div class="modal-field-label">Reporter Name</div>
                        <div class="modal-field-value" id="modal-reporter">—</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">Person Reported</div>
                        <div class="modal-field-value" id="modal-reported">—</div>
                    </div>
                </div>
                <div class="modal-grid-2">
                    <div class="modal-field">
                        <div class="modal-field-label">Report Category</div>
                        <div class="modal-field-value" id="modal-category">—</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">Report Type</div>
                        <div class="modal-field-value" id="modal-type">—</div>
                    </div>
                </div>
                <div class="modal-grid-2">
                    <div class="modal-field">
                        <div class="modal-field-label">Incident Date</div>
                        <div class="modal-field-value" id="modal-incident-date">—</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-field-label">Date Submitted</div>
                        <div class="modal-field-value" id="modal-date">—</div>
                    </div>
                </div>
                <div class="modal-grid-2" style="margin-bottom:14px;">
                    <div class="modal-field">
                        <div class="modal-field-label">Current Status</div>
                        <div id="modal-status">—</div>
                    </div>
                    <div class="modal-field" id="modal-evidence-wrap" style="display:none;">
                        <div class="modal-field-label">Evidence</div>
                        <a id="modal-evidence-link" href="#" target="_blank" class="modal-evidence-link">
                            <i class="fa-solid fa-paperclip"></i> View File
                        </a>
                    </div>
                </div>
                <div class="modal-divider"></div>
                <div class="modal-field">
                    <div class="modal-field-label">Description / Incident Details</div>
                    <div class="modal-description-box" id="modal-description">—</div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-rpt btn-rpt-archive" id="btn-archive" onclick="setStatus('set_archived')">
                    <i class="fa-solid fa-box-archive"></i> Archive
                </button>
                <button class="btn-rpt btn-rpt-invest" id="btn-invest" onclick="setStatus('set_investigating')">
                    <i class="fa-solid fa-magnifying-glass"></i> Mark as Investigating
                </button>
                <button class="btn-rpt btn-rpt-resolve" id="btn-resolve" onclick="setStatus('set_resolved')">
                    <i class="fa-solid fa-circle-check"></i> Resolve
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">
        <i class="fa-solid fa-circle-check" id="toast-icon"></i>
        <span id="toast-msg"></span>
    </div>

    <script src="admin_assets/js/admin_script.js"></script>
    <script>
        /* ══════════════════════════════════════════════
   PHP → JS: inject ALL report data as JSON
══════════════════════════════════════════════ */
        const reportData = <?= json_encode(
                                array_column(
                                    array_map(function ($r) {
                                        return [
                                            'report_id'      => $r['report_id'],
                                            'reporter'       => $r['reporter_name'] . ($r['reporter_grade'] ? ' (' . $r['reporter_grade'] . ')' : ''),
                                            'reported'       => $r['person_reported'],
                                            'category'       => $r['report_category'],
                                            'type'           => $r['report_type'],
                                            'incident_date'  => $r['incident_date'] ? date('M j, Y', strtotime($r['incident_date'])) : '—',
                                            'date'           => date('M j, Y, g:i A', strtotime($r['submission_date'])),
                                            'status'         => $r['status'],
                                            'description'    => $r['description'],
                                            'evidence'       => $r['evidence_path'] ?? null,
                                        ];
                                    }, $reports),
                                    null,
                                    'report_id'
                                ),
                                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
                            ) ?>;

        let activeReportId = null;

        /* badge HTML map */
        const badgeHTML = {
            'Pending': '<span class="badge badge-pending"><i class="fa-solid fa-circle"></i> Pending</span>',
            'Investigating': '<span class="badge badge-investing"><i class="fa-solid fa-magnifying-glass"></i> Investigating</span>',
            'Resolved': '<span class="badge badge-resolved"><i class="fa-solid fa-check"></i> Resolved</span>',
            'Archived': '<span class="badge badge-archived"><i class="fa-solid fa-box-archive"></i> Archived</span>',
        };

        /* ── Open modal ── */
        function openModal(reportId) {
            const r = reportData[reportId];
            if (!r) return;
            activeReportId = reportId;

            document.getElementById('modal-title').textContent = r.category + ' Report';
            document.getElementById('modal-id').textContent = reportId + ' · Submitted ' + r.date;
            document.getElementById('modal-reporter').textContent = r.reporter;
            document.getElementById('modal-reported').textContent = r.reported;
            document.getElementById('modal-category').textContent = r.category;
            document.getElementById('modal-type').textContent = r.type;
            document.getElementById('modal-incident-date').textContent = r.incident_date;
            document.getElementById('modal-date').textContent = r.date;
            document.getElementById('modal-status').innerHTML = badgeHTML[r.status] || r.status;
            document.getElementById('modal-description').textContent = r.description;

            /* evidence */
            const evWrap = document.getElementById('modal-evidence-wrap');
            if (r.evidence) {
                document.getElementById('modal-evidence-link').href = '../uploads/report_evidence/' + r.evidence;
                evWrap.style.display = 'block';
            } else {
                evWrap.style.display = 'none';
            }

            /* disable already-set status buttons */
            document.getElementById('btn-invest').disabled = (r.status === 'Investigating');
            document.getElementById('btn-resolve').disabled = (r.status === 'Resolved');
            document.getElementById('btn-archive').disabled = (r.status === 'Archived');

            document.getElementById('reportModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('reportModal').classList.remove('active');
            activeReportId = null;
        }

        document.getElementById('reportModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        /* ── AJAX status update ── */
        function setStatus(action) {
            if (!activeReportId) return;

            const labels = {
                set_investigating: 'Mark as "Investigating"?',
                set_resolved: 'Mark as Resolved?',
                set_archived: 'Archive this report?',
                set_pending: 'Reset to Pending?',
            };
            if (!confirm(labels[action] || 'Confirm?')) return;

            fetch(window.location.pathname, {
                    method: 'POST',
                    body: new URLSearchParams({
                        ajax_action: action,
                        report_id: activeReportId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        /* update badge in table */
                        const cell = document.getElementById('badge-' + activeReportId);
                        if (cell) cell.innerHTML = badgeHTML[data.new_status] || data.new_status;
                        /* update cached data + modal */
                        if (reportData[activeReportId]) reportData[activeReportId].status = data.new_status;
                        document.getElementById('modal-status').innerHTML = badgeHTML[data.new_status] || data.new_status;
                        /* re-toggle buttons */
                        document.getElementById('btn-invest').disabled = (data.new_status === 'Investigating');
                        document.getElementById('btn-resolve').disabled = (data.new_status === 'Resolved');
                        document.getElementById('btn-archive').disabled = (data.new_status === 'Archived');
                        showToast('Status updated to "' + data.new_status + '"', 'success');
                    } else {
                        showToast('Failed to update. Please try again.', 'error');
                    }
                })
                .catch(() => showToast('Network error. Check your connection.', 'error'));
        }

        /* ── Export CSV ── */
        function exportCSV() {
            const header = ['Report ID', 'Reporter', 'Grade/Section', 'Person Reported', 'Category', 'Type', 'Status', 'Incident Date', 'Submitted'];
            const rows = Object.values(reportData).map(r => [
                r.report_id ?? '',
                (r.reporter || '').split(' (')[0],
                (r.reporter || '').match(/\(([^)]+)\)/)?.[1] ?? '',
                r.reported ?? '',
                r.category ?? '',
                r.type ?? '',
                r.status ?? '',
                r.incident_date ?? '',
                r.date ?? '',
            ]);
            const csv = [header, ...rows].map(row => row.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\r\n');
            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = Object.assign(document.createElement('a'), {
                href: URL.createObjectURL(blob),
                download: 'reports_' + new Date().toISOString().slice(0, 10) + '.csv'
            });
            link.click();
            URL.revokeObjectURL(link.href);
            showToast('CSV exported!', 'success');
        }

        /* ── Toast ── */
        function showToast(msg, type = 'success') {
            const el = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            document.getElementById('toast-msg').textContent = msg;
            icon.className = type === 'success' ?
                'fa-solid fa-circle-check' :
                'fa-solid fa-circle-xmark';
            el.className = 'toast toast-' + type + ' show';
            clearTimeout(el._t);
            el._t = setTimeout(() => el.className = 'toast', 3400);
        }
    </script>
</body>

</html>