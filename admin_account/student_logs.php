<?php
session_start();
include '../db_connection.php';

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS student_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$offset    = ($page - 1) * $perPage;

$where  = '';
$params = [];
$types  = '';

if (!empty($search)) {
    $where    = "WHERE admin_name LIKE ? OR action LIKE ? OR student_id LIKE ?";
    $sp       = "%{$search}%";
    $params   = [$sp, $sp, $sp];
    $types    = 'sss';
}

$countStmt = $conn->prepare("SELECT COUNT(*) as c FROM student_logs {$where}");
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $conn->prepare("SELECT * FROM student_logs {$where} ORDER BY timestamp DESC LIMIT ? OFFSET ?");
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stat counts per action type
$statsStmt = $conn->prepare("SELECT action, COUNT(*) as cnt FROM student_logs GROUP BY action");
$statsStmt->execute();
$statsRaw = $statsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$statsStmt->close();
$statsMap = [];
foreach ($statsRaw as $s) $statsMap[$s['action']] = $s['cnt'];

$totalLogs   = array_sum($statsMap);
$totalAdds   = ($statsMap['Add Student'] ?? 0) + ($statsMap['Add Student (CSV)'] ?? 0);
$totalEdits  = $statsMap['Edit Student'] ?? 0;
$totalDeletes = $statsMap['Delete Student'] ?? 0;

$actionColors = [
    'Add Student'       => ['bg' => '#dcfce7', 'color' => '#15803d', 'dot' => '#22c55e'],
    'Add Student (CSV)' => ['bg' => '#dcfce7', 'color' => '#15803d', 'dot' => '#22c55e'],
    'Edit Student'      => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'dot' => '#3b82f6'],
    'Delete Student'    => ['bg' => '#fee2e2', 'color' => '#dc2626', 'dot' => '#ef4444'],
];

function badgeHtml($action, $colors)
{
    $c = $colors[$action] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'dot' => '#94a3b8'];
    return "<span class=\"action-badge\" style=\"--badge-bg:{$c['bg']};--badge-color:{$c['color']};--badge-dot:{$c['dot']};\">"
        . "<span class=\"badge-dot\"></span>"
        . htmlspecialchars($action)
        . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — School Admin</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="admin_assets/cs/student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        .page-content {
            font-family: 'DM Sans', sans-serif;
            background: #f4f3f0;
            min-height: 100vh;
            padding: 28px 32px 48px;
            color: #1a1a2e;
        }

        /* ── Page Header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }

        .page-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .page-header-eyebrow {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #8b7cf8;
        }

        .page-header-title {
            font-size: 1.65rem;
            font-weight: 700;
            color: #0f0e17;
            letter-spacing: -.02em;
            line-height: 1.1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            border: 1px solid #e4e2de;
            color: #0f0e17;
            font-family: inherit;
            font-size: .82rem;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: 10px;
            text-decoration: none;
            transition: background .15s, box-shadow .15s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
        }

        .back-btn:hover {
            background: #f8f7f4;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1);
        }

        .back-btn i {
            font-size: .8rem;
            color: #8b7cf8;
        }

        /* ── Bento Grid ── */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: auto auto auto;
            gap: 14px;
        }

        /* ── Bento Cell Base ── */
        .bento-cell {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e8e6e1;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .05);
            padding: 20px 22px;
            transition: box-shadow .2s;
        }

        .bento-cell:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, .09);
        }

        /* ── Stat Cards (row 1) ── */
        .stat-card {
            position: relative;
            overflow: hidden;
        }

        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 14px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -.04em;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            font-size: .75rem;
            font-weight: 600;
            color: #9190a0;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .stat-card .stat-bg {
            position: absolute;
            right: -12px;
            bottom: -12px;
            font-size: 5rem;
            opacity: .05;
            pointer-events: none;
        }

        .stat-total {
            background: #0f0e17;
            color: #fff;
            border-color: #0f0e17;
        }

        .stat-total .stat-icon {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .stat-total .stat-label {
            color: rgba(255, 255, 255, .5);
        }

        .stat-add .stat-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .stat-add .stat-value {
            color: #15803d;
        }

        .stat-edit .stat-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-edit .stat-value {
            color: #1d4ed8;
        }

        .stat-delete .stat-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-delete .stat-value {
            color: #dc2626;
        }

        /* ── Search + Filter Cell (row 2, spans 4) ── */
        .search-cell {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 16px 22px;
        }

        .notice-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 30px;
            padding: 6px 14px;
            font-size: .75rem;
            font-weight: 600;
            color: #92400e;
        }

        .notice-pill i {
            font-size: .72rem;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0aec0;
            font-size: .8rem;
            pointer-events: none;
        }

        .search-input {
            font-family: inherit;
            font-size: .84rem;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 9px 14px 9px 34px;
            width: 280px;
            background: #f8f7f4;
            color: #0f0e17;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .search-input:focus {
            border-color: #8b7cf8;
            box-shadow: 0 0 0 3px rgba(139, 124, 248, .12);
            background: #fff;
        }

        .search-input::placeholder {
            color: #c4c2ce;
        }

        .btn-search {
            background: #8b7cf8;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 16px;
            font-family: inherit;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background .15s, box-shadow .15s;
            text-decoration: none;
        }

        .btn-search:hover {
            background: #7c6ef0;
            box-shadow: 0 3px 10px rgba(139, 124, 248, .35);
        }

        .btn-clear {
            background: #f8f7f4;
            color: #6b6a7a;
            border: 1px solid #e4e2de;
            border-radius: 10px;
            padding: 9px 14px;
            font-family: inherit;
            font-size: .82rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-clear:hover {
            background: #efede8;
        }

        /* ── Table Cell (row 3, spans 4) ── */
        .table-cell {
            grid-column: 1 / -1;
            padding: 0;
            overflow: hidden;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .855rem;
        }

        .log-table thead tr {
            background: #f8f7f4;
            border-bottom: 1px solid #e8e6e1;
        }

        .log-table th {
            padding: 13px 20px;
            text-align: left;
            font-weight: 600;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: #9190a0;
        }

        .log-table td {
            padding: 13px 20px;
            border-bottom: 1px solid #f4f3f0;
            color: #2d2c3e;
            vertical-align: middle;
        }

        .log-table tbody tr:last-child td {
            border-bottom: none;
        }

        .log-table tbody tr {
            transition: background .12s;
        }

        .log-table tbody tr:hover td {
            background: #faf8ff;
        }

        .row-num {
            font-family: 'DM Mono', monospace;
            font-size: .75rem;
            color: #c4c2ce;
            font-weight: 500;
        }

        .admin-cell {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .admin-avatar {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #8b7cf8, #c084fc);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
        }

        .admin-name {
            font-weight: 500;
            color: #0f0e17;
            font-size: .84rem;
        }

        /* Action Badge */
        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--badge-bg);
            color: var(--badge-color);
            padding: 4px 11px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            background: var(--badge-dot);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .student-id-code {
            font-family: 'DM Mono', monospace;
            font-size: .78rem;
            background: #f4f3f0;
            color: #5a5870;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1px solid #e8e6e1;
        }

        .ts-date {
            font-weight: 500;
            color: #0f0e17;
            display: block;
            font-size: .83rem;
        }

        .ts-time {
            font-size: .73rem;
            color: #9190a0;
            margin-top: 1px;
            display: block;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: #b0aec0;
        }

        .empty-state i {
            font-size: 2.4rem;
            margin-bottom: 12px;
            display: block;
            color: #dddae8;
        }

        .empty-state p {
            font-size: .88rem;
        }

        /* ── Pagination Cell (row 4) ── */
        .pagination-cell {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 22px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-info {
            font-size: .78rem;
            color: #9190a0;
            font-weight: 500;
        }

        .pagination-btns {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-btn {
            border: 1px solid #e4e2de;
            background: #fff;
            color: #374151;
            padding: 7px 13px;
            border-radius: 9px;
            cursor: pointer;
            font-size: .8rem;
            font-weight: 600;
            text-decoration: none;
            font-family: inherit;
            transition: all .14s;
            display: inline-flex;
            align-items: center;
        }

        .page-btn:hover {
            background: #f4f3f0;
            border-color: #ccc;
        }

        .page-btn.active {
            background: #0f0e17;
            color: #fff;
            border-color: #0f0e17;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-content {
                padding: 18px 16px 36px;
            }
        }

        @media (max-width: 560px) {
            .bento-grid {
                grid-template-columns: 1fr 1fr;
            }

            .search-cell {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-input {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>
    <script>
        fetch('admin_nav.php').then(r => r.text()).then(data => {
            document.getElementById('navigation-container').innerHTML = data;
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) mainDiv.appendChild(pageContent);
        }).catch(e => console.error(e));
    </script>

    <section class="page-content">

        <!-- Top Bar -->
        <div class="page-header">
            <div class="page-header-left">
                <span class="page-header-eyebrow"><i class="fas fa-history"></i>&ensp;Audit Trail</span>
                <h1 class="page-header-title">Activity Logs</h1>
            </div>
            <a href="students.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
        </div>

        <!-- Bento Grid -->
        <div class="bento-grid">

            <!-- Stat: Total -->
            <div class="bento-cell stat-card stat-total">
                <div class="stat-icon"><i class="fas fa-database"></i></div>
                <div class="stat-value"><?php echo number_format($totalLogs); ?></div>
                <div class="stat-label">Total Events</div>
                <i class="fas fa-database stat-bg"></i>
            </div>

            <!-- Stat: Adds -->
            <div class="bento-cell stat-card stat-add">
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                <div class="stat-value"><?php echo number_format($totalAdds); ?></div>
                <div class="stat-label">Students Added</div>
                <i class="fas fa-user-plus stat-bg"></i>
            </div>

            <!-- Stat: Edits -->
            <div class="bento-cell stat-card stat-edit">
                <div class="stat-icon"><i class="fas fa-pen"></i></div>
                <div class="stat-value"><?php echo number_format($totalEdits); ?></div>
                <div class="stat-label">Records Edited</div>
                <i class="fas fa-pen stat-bg"></i>
            </div>

            <!-- Stat: Deletes -->
            <div class="bento-cell stat-card stat-delete">
                <div class="stat-icon"><i class="fas fa-trash"></i></div>
                <div class="stat-value"><?php echo number_format($totalDeletes); ?></div>
                <div class="stat-label">Records Deleted</div>
                <i class="fas fa-trash stat-bg"></i>
            </div>

            <!-- Search + Notice Bar -->
            <div class="bento-cell search-cell">
                <span class="notice-pill">
                    <i class="fas fa-lock"></i> Logs are read-only — cannot be edited or deleted
                </span>
                <form method="get" class="search-form">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="search-input"
                            placeholder="Search admin, action, student ID…"
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="student_logs.php" class="btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="bento-cell table-cell">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Administrator</th>
                            <th>Action</th>
                            <th>Student ID</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $i => $log): ?>
                                <tr>
                                    <td><span class="row-num"><?php echo str_pad($offset + $i + 1, 3, '0', STR_PAD_LEFT); ?></span></td>
                                    <td>
                                        <div class="admin-cell">
                                            <div class="admin-avatar">
                                                <?php echo strtoupper(substr($log['admin_name'], 0, 2)); ?>
                                            </div>
                                            <span class="admin-name"><?php echo htmlspecialchars($log['admin_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo badgeHtml($log['action'], $actionColors); ?></td>
                                    <td><span class="student-id-code"><?php echo htmlspecialchars($log['student_id']); ?></span></td>
                                    <td>
                                        <span class="ts-date"><?php echo date('M d, Y', strtotime($log['timestamp'])); ?></span>
                                        <span class="ts-time"><?php echo date('h:i A', strtotime($log['timestamp'])); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No activity logs found<?php echo !empty($search) ? ' matching your search' : ''; ?>.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="bento-cell pagination-cell">
                    <span class="page-info">
                        Showing <?php echo ($offset + 1) . '-' . min($offset + $perPage, $total); ?> of <?php echo number_format($total); ?> entries
                    </span>
                    <div class="pagination-btns">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"
                                class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /bento-grid -->
    </section>

    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>