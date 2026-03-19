<?php

/**
 * clubs_api.php
 * ─────────────────────────────────────────────────────────────────
 * REST-like API endpoint for all club-related AJAX operations.
 *
 * Supported actions (GET):
 *   ?action=get_club&id={clubId}          — full club detail + members w/ online status
 *   ?action=get_clubs                     — list all clubs
 *
 * Supported actions (POST, JSON body):
 *   approve_request  { request_id }
 *   reject_request   { request_id }
 *   join_club        { club_id }
 *   add_member       { club_id, user_id }
 *   remove_member    { club_id, user_id }
 *   promote_member   { club_id, user_id }
 *
 * All mutating actions require a valid CSRF token in the request body.
 * ─────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ──────────────────────────────────────────────────────────────────
   SESSION & AUTH
────────────────────────────────────────────────────────────────── */
session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';
$can_manage = in_array($user_role, ['admin', 'sub_admin'], true);

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ──────────────────────────────────────────────────────────────────
   DATABASE
────────────────────────────────────────────────────────────────── */
$host    = 'localhost';
$db_name = 'school_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    json_out(['success' => false, 'message' => 'Database connection failed.'], 500);
}

/* ──────────────────────────────────────────────────────────────────
   ENSURE student_online_status TABLE EXISTS
   Tracks last-seen time for each student login session.
────────────────────────────────────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS student_online_status (
    student_id  VARCHAR(50)  NOT NULL PRIMARY KEY,
    last_seen   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_online   TINYINT(1)   NOT NULL DEFAULT 0,
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ──────────────────────────────────────────────────────────────────
   HELPERS
────────────────────────────────────────────────────────────────── */
/**
 * Return human-readable "last seen" string from a datetime string.
 */
function last_seen_label(?string $dt): string
{
    if (!$dt) return 'Unknown';
    $diff = time() - strtotime($dt);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return round($diff / 60) . ' min ago';
    if ($diff < 86400)   return round($diff / 3600) . ' hr ago';
    if ($diff < 604800)  return round($diff / 86400) . ' day' . (round($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('M j, Y', strtotime($dt));
}

/**
 * Determine online status: online if last_seen within 5 minutes, active within 1 hour.
 */
function online_status(?string $dt, ?int $is_online): array
{
    if (!$dt) return ['label' => 'Offline', 'class' => 'offline'];
    $diff = time() - strtotime($dt);
    if ($is_online && $diff < 300) return ['label' => 'Online',  'class' => 'online'];
    if ($diff < 3600)              return ['label' => 'Active',  'class' => 'active'];
    return ['label' => 'Offline', 'class' => 'offline'];
}

/* ──────────────────────────────────────────────────────────────────
   CSRF VALIDATION (mutating requests only)
────────────────────────────────────────────────────────────────── */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ──────────────────────────────────────────────────────────────────
   ROUTING
────────────────────────────────────────────────────────────────── */
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {

        /* ────────────────────────────────────────────────────
           GET CLUB DETAIL + MEMBERS (with online status)
        ──────────────────────────────────────────────────── */
        case 'get_club':
            $club_id = (int)($_GET['id'] ?? 0);
            if (!$club_id) json_out(['success' => false, 'message' => 'Invalid club ID.'], 400);

            // Fetch club + adviser/leader names
            $stmt = $pdo->prepare("
                SELECT c.*,
                       u.full_name   AS adviser_name,
                       l.full_name   AS leader_name,
                       vl.full_name  AS vice_leader_name,
                       (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id) AS member_count
                FROM clubs c
                LEFT JOIN users u  ON c.adviser_id      = u.id
                LEFT JOIN users l  ON c.leader_id        = l.id
                LEFT JOIN users vl ON c.vice_leader_id   = vl.id
                WHERE c.id = ?
                LIMIT 1
            ");
            $stmt->execute([$club_id]);
            $club = $stmt->fetch();

            if (!$club) json_out(['success' => false, 'message' => 'Club not found.'], 404);

            // Format date
            if (!empty($club['created_at'])) {
                $club['created_at'] = date('M j, Y', strtotime($club['created_at']));
            }

            // ── Fetch members with profile data + online status ─────────────────
            // We join:
            //   club_members → users (for name, grade, profile_pic)
            //   student_profile_data (for login/email/phone saved in profile.php)
            //   student_online_status (for last_seen + is_online)
            //
            // student_profile_data uses student_id which maps to users.student_id
            // student_online_status uses student_id as well
            //
            // Fallback: if student_profile_data table doesn't exist yet, we still
            //   get basic member rows (the LEFT JOINs won't throw errors).
            // ────────────────────────────────────────────────────────────────────
            $mStmt = $pdo->prepare("
                SELECT
                    cm.role,
                    u.id,
                    u.full_name,
                    u.profile_pic,
                    u.grade_section,
                    u.student_id        AS student_ref_id,

                    -- Profile data (saved by profile.php)
                    COALESCE(spd.email, u.email, '')          AS login_email,
                    COALESCE(spd.phone, u.phone, '')          AS login_phone,
                    spd.photo                                 AS profile_photo,

                    -- Online presence
                    sos.last_seen,
                    sos.is_online

                FROM club_members cm
                JOIN  users u   ON cm.user_id = u.id

                LEFT JOIN student_profile_data spd
                       ON spd.student_id = u.student_id

                LEFT JOIN student_online_status sos
                       ON sos.student_id = u.student_id

                WHERE cm.club_id = ?
                ORDER BY
                    FIELD(cm.role, 'President', 'Vice President', 'Officer', 'Member'),
                    u.full_name
            ");

            // student_profile_data may not exist; catch the error gracefully
            try {
                $mStmt->execute([$club_id]);
                $members_raw = $mStmt->fetchAll();
            } catch (PDOException $e) {
                // Fallback without profile/online tables
                $mStmt2 = $pdo->prepare("
                    SELECT cm.role, u.id, u.full_name, u.profile_pic, u.grade_section,
                           u.student_id AS student_ref_id,
                           '' AS login_email, '' AS login_phone, NULL AS profile_photo,
                           NULL AS last_seen, 0 AS is_online
                    FROM club_members cm
                    JOIN users u ON cm.user_id = u.id
                    WHERE cm.club_id = ?
                    ORDER BY FIELD(cm.role,'President','Vice President','Officer','Member'), u.full_name
                ");
                $mStmt2->execute([$club_id]);
                $members_raw = $mStmt2->fetchAll();
            }

            // Enrich each member row with display-ready status info
            $members = array_map(function (array $m) {
                $status = online_status($m['last_seen'] ?? null, (int)($m['is_online'] ?? 0));

                // Decide which login credential to display
                $login_display = '';
                if (!empty($m['login_email'])) {
                    $login_display = $m['login_email'];
                } elseif (!empty($m['login_phone'])) {
                    $login_display = $m['login_phone'];
                }

                // Avatar: profile_photo from profile.php > profile_pic from users table
                $avatar = $m['profile_photo'] ?? $m['profile_pic'] ?? null;

                return [
                    'id'            => $m['id'],
                    'full_name'     => $m['full_name'] ?? '—',
                    'role'          => $m['role']       ?? 'Member',
                    'grade_section' => $m['grade_section'] ?? '',
                    'profile_pic'   => $avatar,
                    'login_display' => $login_display,
                    'last_seen'     => last_seen_label($m['last_seen'] ?? null),
                    'status_label'  => $status['label'],
                    'status_class'  => $status['class'],
                    'is_online'     => $status['class'] === 'online',
                ];
            }, $members_raw);

            json_out(['success' => true, 'club' => $club, 'members' => $members]);

            /* ────────────────────────────────────────────────────
           GET ALL CLUBS (for dynamic reload)
        ──────────────────────────────────────────────────── */
        case 'get_clubs':
            $stmt = $pdo->query("
                SELECT c.id, c.name, c.description, c.category, c.status,
                       u.full_name AS adviser_name,
                       (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id) AS member_count
                FROM clubs c
                LEFT JOIN users u ON c.adviser_id = u.id
                WHERE c.status IN ('Active','Inactive')
                ORDER BY c.created_at DESC
            ");
            json_out(['success' => true, 'clubs' => $stmt->fetchAll()]);

        default:
            json_out(['success' => false, 'message' => 'Unknown GET action.'], 400);
    }
}

/* ──────────────────────────────────────────────────────────────────
   POST ACTIONS
────────────────────────────────────────────────────────────────── */
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) json_out(['success' => false, 'message' => 'Invalid request body.'], 400);

    $action = $body['action'] ?? '';
    $csrf   = $body['csrf']   ?? '';

    // Validate CSRF
    if (!verify_csrf($csrf)) {
        json_out(['success' => false, 'message' => 'Invalid or expired CSRF token.'], 403);
    }

    switch ($action) {

        /* ────────────────────────────────────────────────────
           APPROVE CLUB REQUEST
        ──────────────────────────────────────────────────── */
        case 'approve_request':
            if (!$can_manage) json_out(['success' => false, 'message' => 'Unauthorized.'], 403);

            $req_id = (int)($body['request_id'] ?? 0);
            if (!$req_id) json_out(['success' => false, 'message' => 'Invalid request ID.'], 400);

            $stmt = $pdo->prepare("SELECT * FROM club_requests WHERE id = ? AND status = 'Pending' LIMIT 1");
            $stmt->execute([$req_id]);
            $req = $stmt->fetch();

            if (!$req) json_out(['success' => false, 'message' => 'Request not found or already processed.'], 404);

            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("
                    INSERT INTO clubs
                        (name, description, category, adviser_id, leader_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'Active', NOW())
                ");
                $ins->execute([
                    $req['proposed_name'],
                    $req['description'],
                    $req['proposed_category'] ?? 'Other',
                    $req['proposed_adviser_id'],
                    $req['student_id'],
                ]);
                $new_club_id = (int)$pdo->lastInsertId();

                $pdo->prepare("
                    INSERT INTO club_members (club_id, user_id, role, joined_at)
                    VALUES (?, ?, 'President', NOW())
                ")->execute([$new_club_id, $req['student_id']]);

                $pdo->prepare("
                    UPDATE club_requests SET status = 'Approved', processed_at = NOW(),
                    processed_by = ? WHERE id = ?
                ")->execute([$user_id, $req_id]);

                $pdo->prepare("
                    INSERT INTO club_activity_log (actor_id, action_type, description, created_at)
                    VALUES (?, 'new', ?, NOW())
                ")->execute([$user_id, "Club \"{$req['proposed_name']}\" was created and approved."]);

                $pdo->commit();

                $club_row = $pdo->query("
                    SELECT c.*, u.full_name AS adviser_name
                    FROM clubs c
                    LEFT JOIN users u ON c.adviser_id = u.id
                    WHERE c.id = $new_club_id
                ")->fetch();

                json_out(['success' => true, 'club' => $club_row]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                json_out(['success' => false, 'message' => 'Failed to approve request. Please try again.'], 500);
            }

            /* ────────────────────────────────────────────────────
           REJECT CLUB REQUEST
        ──────────────────────────────────────────────────── */
        case 'reject_request':
            if (!$can_manage) json_out(['success' => false, 'message' => 'Unauthorized.'], 403);

            $req_id = (int)($body['request_id'] ?? 0);
            if (!$req_id) json_out(['success' => false, 'message' => 'Invalid request ID.'], 400);

            $stmt = $pdo->prepare("
                UPDATE club_requests
                SET status = 'Rejected', processed_at = NOW(), processed_by = ?
                WHERE id = ? AND status = 'Pending'
            ");
            $stmt->execute([$user_id, $req_id]);

            if ($stmt->rowCount() === 0) {
                json_out(['success' => false, 'message' => 'Request not found or already processed.'], 404);
            }

            $pdo->prepare("
                INSERT INTO club_activity_log (actor_id, action_type, description, created_at)
                VALUES (?, 'new', 'A club request was rejected.', NOW())
            ")->execute([$user_id]);

            json_out(['success' => true]);

            /* ────────────────────────────────────────────────────
           JOIN CLUB (student)
        ──────────────────────────────────────────────────── */
        case 'join_club':
            if (!$user_id) json_out(['success' => false, 'message' => 'Not logged in.'], 401);

            $club_id = (int)($body['club_id'] ?? 0);
            if (!$club_id) json_out(['success' => false, 'message' => 'Invalid club ID.'], 400);

            $check = $pdo->prepare("SELECT id FROM club_members WHERE club_id = ? AND user_id = ?");
            $check->execute([$club_id, $user_id]);
            if ($check->fetch()) json_out(['success' => false, 'message' => 'Already a member of this club.']);

            $pdo->prepare("
                INSERT INTO club_members (club_id, user_id, role, joined_at) VALUES (?, ?, 'Member', NOW())
            ")->execute([$club_id, $user_id]);

            $name_row = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $name_row->execute([$user_id]);
            $uname = $name_row->fetchColumn();

            $club_row = $pdo->prepare("SELECT name FROM clubs WHERE id = ?");
            $club_row->execute([$club_id]);
            $cname = $club_row->fetchColumn();

            $pdo->prepare("
                INSERT INTO club_activity_log (actor_id, action_type, description, created_at)
                VALUES (?, 'join', ?, NOW())
            ")->execute([$user_id, "{$uname} joined {$cname}."]);

            json_out(['success' => true, 'message' => "You have joined $cname!"]);

            /* ────────────────────────────────────────────────────
           ADD MEMBER (admin / leader)
        ──────────────────────────────────────────────────── */
        case 'add_member':
            if (!$can_manage) json_out(['success' => false, 'message' => 'Unauthorized.'], 403);

            $club_id  = (int)($body['club_id'] ?? 0);
            $new_uid  = (int)($body['user_id'] ?? 0);
            if (!$club_id || !$new_uid) json_out(['success' => false, 'message' => 'Missing fields.'], 400);

            $check = $pdo->prepare("SELECT id FROM club_members WHERE club_id = ? AND user_id = ?");
            $check->execute([$club_id, $new_uid]);
            if ($check->fetch()) json_out(['success' => false, 'message' => 'User is already a member.']);

            $pdo->prepare("
                INSERT INTO club_members (club_id, user_id, role, joined_at) VALUES (?, ?, 'Member', NOW())
            ")->execute([$club_id, $new_uid]);

            json_out(['success' => true]);

            /* ────────────────────────────────────────────────────
           REMOVE MEMBER
        ──────────────────────────────────────────────────── */
        case 'remove_member':
            if (!$can_manage) json_out(['success' => false, 'message' => 'Unauthorized.'], 403);

            $club_id = (int)($body['club_id'] ?? 0);
            $rem_uid = (int)($body['user_id'] ?? 0);
            if (!$club_id || !$rem_uid) json_out(['success' => false, 'message' => 'Missing fields.'], 400);

            $pdo->prepare("DELETE FROM club_members WHERE club_id = ? AND user_id = ?")
                ->execute([$club_id, $rem_uid]);
            json_out(['success' => true]);

            /* ────────────────────────────────────────────────────
           PROMOTE MEMBER TO OFFICER
        ──────────────────────────────────────────────────── */
        case 'promote_member':
            if (!$can_manage) json_out(['success' => false, 'message' => 'Unauthorized.'], 403);

            $club_id   = (int)($body['club_id'] ?? 0);
            $prom_uid  = (int)($body['user_id'] ?? 0);
            if (!$club_id || !$prom_uid) json_out(['success' => false, 'message' => 'Missing fields.'], 400);

            $pdo->prepare("
                UPDATE club_members SET role = 'Officer' WHERE club_id = ? AND user_id = ?
            ")->execute([$club_id, $prom_uid]);

            $n = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $n->execute([$prom_uid]);
            $nm = $n->fetchColumn();

            $pdo->prepare("
                INSERT INTO club_activity_log (actor_id, action_type, description, created_at)
                VALUES (?, 'promote', ?, NOW())
            ")->execute([$user_id, "{$nm} was promoted to Officer."]);

            json_out(['success' => true]);

        default:
            json_out(['success' => false, 'message' => 'Unknown POST action.'], 400);
    }
}

json_out(['success' => false, 'message' => 'Method not allowed.'], 405);
