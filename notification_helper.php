<?php
/**
 * notification_helper.php
 * ─────────────────────────────────────────────────────────────
 * Include this file in any module where sub-admins perform edits.
 * Call log_admin_notification() immediately after a successful DB write.
 *
 * Usage:
 *   require_once '../notification_helper.php';
 *   log_admin_notification($conn, 'Student Admin', 'students', 'Edited student record ID 42.');
 */

/**
 * Write one row to admin_notifications.
 *
 * @param mysqli $conn           Active MySQLi connection
 * @param string $module         Human-readable module name (e.g. 'students')
 * @param string $description    What was done (e.g. 'Edited student record ID 42.')
 * @param string|null $role      Override role; defaults to $_SESSION value
 */
function log_admin_notification(
    mysqli $conn,
    string $module,
    string $description,
    ?string $role = null
): void {
    // Only log when a sub-admin (or admin) is authenticated
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $sub_admin_id    = (int) $_SESSION['user_id'];
    $sub_admin_name  = $_SESSION['username']  ?? 'Unknown';
    $sub_admin_email = $_SESSION['user_email'] ?? '';
    $effective_role  = $role ?? ($_SESSION['user_role'] ?? $_SESSION['user_type'] ?? 'Admin');

    // If email not in session, try a quick DB lookup
    if (empty($sub_admin_email)) {
        $tbl = ($_SESSION['user_type'] === 'admin') ? 'admin' : 'sub_admin';
        $col = ($_SESSION['user_type'] === 'admin') ? 'personal_email' : 'email';
        $s   = $conn->prepare("SELECT `{$col}` FROM `{$tbl}` WHERE id = ? LIMIT 1");
        if ($s) {
            $s->bind_param('i', $sub_admin_id);
            $s->execute();
            $r = $s->get_result()->fetch_assoc();
            $sub_admin_email = $r[$col] ?? '';
            $s->close();
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO admin_notifications
            (sub_admin_id, sub_admin_name, sub_admin_email, role, edited_module, edit_description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param(
            'isssss',
            $sub_admin_id,
            $sub_admin_name,
            $sub_admin_email,
            $effective_role,
            $module,
            $description
        );
        $stmt->execute();
        $stmt->close();
    }
}
