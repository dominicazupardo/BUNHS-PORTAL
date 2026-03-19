<?php
include '../../db_connection.php';

// ============================================================
//  HELPER: ensure extended columns exist (idempotent)
// ============================================================
function ensure_event_columns($conn)
{
    $checks = [
        "location"             => "VARCHAR(255) DEFAULT NULL",
        "image"                => "VARCHAR(255) DEFAULT NULL",
        "organizer_name"       => "VARCHAR(255) DEFAULT NULL",
        "organizer_position"   => "VARCHAR(255) DEFAULT NULL",
        "organizer_contact"    => "VARCHAR(255) DEFAULT NULL",
        "team_based"           => "TINYINT(1) DEFAULT 0",
    ];
    foreach ($checks as $col => $def) {
        $r = $conn->query("SHOW COLUMNS FROM events LIKE '$col'");
        if ($r->num_rows == 0) {
            $conn->query("ALTER TABLE events ADD COLUMN $col $def");
        }
    }
}

function ensure_feature_tables($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS event_highlights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        highlight VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        time_slot VARCHAR(100) NOT NULL,
        activity VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        registrant_type VARCHAR(50) DEFAULT 'student',
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        UNIQUE KEY uq_application (event_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        group_name VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE,
        UNIQUE KEY uq_group_student (group_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        teacher_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_event_columns($conn);
ensure_feature_tables($conn);

// ============================================================
//  EXISTING FUNCTIONS (unchanged)
// ============================================================
function insert_news($conn)
{
    $title             = $_POST['title'];
    $short_description = $_POST['short_description'];
    $content           = $_POST['content'];
    $category          = $_POST['category'];
    $news_date         = $_POST['news_date'];
    $author            = $_POST['author'];
    if (empty($news_date)) $news_date = date("Y-m-d");
    if (empty($author))    $author    = "Unknown";
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir  = "../../assets/img/blog/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = basename($_FILES["image"]["name"]);
        }
    }
    $stmt = $conn->prepare("INSERT INTO news (title, short_description, content, image, category, news_date, author, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $title, $short_description, $content, $image, $category, $news_date, $author);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function delete_news($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function insert_event($conn)
{
    $title            = $_POST['event_title'];
    $description      = $_POST['event_description'];
    $event_date       = $_POST['event_date'];
    $category         = $_POST['event_category'];
    $event_start_time = isset($_POST['event_start_time']) ? $_POST['event_start_time'] : null;
    $event_end_time   = isset($_POST['event_end_time'])   ? $_POST['event_end_time']   : null;
    $event_days       = isset($_POST['event_days'])       ? intval($_POST['event_days']) : 1;
    $team_based       = isset($_POST['team_based'])       ? 1 : 0;
    $location         = isset($_POST['event_location'])   ? $_POST['event_location']   : null;
    $organizer_name   = isset($_POST['organizer_name'])   ? $_POST['organizer_name']   : null;
    $organizer_pos    = isset($_POST['organizer_position']) ? $_POST['organizer_position'] : null;
    $organizer_contact = isset($_POST['organizer_contact']) ? $_POST['organizer_contact'] : null;

    if ($event_days < 1) $event_days = 1;

    // Handle image upload
    $image = '';
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
        $target_dir  = "../../assets/img/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $ext         = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $filename    = 'event_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            $image = $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, category, event_start_time, event_end_time, event_days, team_based, location, image, organizer_name, organizer_position, organizer_contact, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssiisssss", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $image, $organizer_name, $organizer_pos, $organizer_contact);
    $success = $stmt->execute();
    $new_id  = $conn->insert_id;
    $stmt->close();

    if ($success && $new_id) {
        // Save highlights
        if (!empty($_POST['highlights'])) {
            $conn->query("DELETE FROM event_highlights WHERE event_id = $new_id");
            foreach ($_POST['highlights'] as $i => $hl) {
                $hl = trim($hl);
                if ($hl === '') continue;
                $s = $conn->prepare("INSERT INTO event_highlights (event_id, highlight, sort_order) VALUES (?,?,?)");
                $s->bind_param("isi", $new_id, $hl, $i);
                $s->execute();
                $s->close();
            }
        }
        // Save schedule rows
        if (!empty($_POST['schedule_time']) && !empty($_POST['schedule_activity'])) {
            $conn->query("DELETE FROM event_schedule WHERE event_id = $new_id");
            foreach ($_POST['schedule_time'] as $i => $time) {
                $time = trim($time);
                $act  = isset($_POST['schedule_activity'][$i]) ? trim($_POST['schedule_activity'][$i]) : '';
                $desc = isset($_POST['schedule_desc'][$i])     ? trim($_POST['schedule_desc'][$i])     : '';
                if ($time === '' && $act === '') continue;
                $s = $conn->prepare("INSERT INTO event_schedule (event_id, time_slot, activity, description, sort_order) VALUES (?,?,?,?,?)");
                $s->bind_param("isssi", $new_id, $time, $act, $desc, $i);
                $s->execute();
                $s->close();
            }
        }
    }
    return $success;
}

function update_event_details($conn, $event_id)
{
    $event_id         = intval($event_id);
    $title            = $_POST['event_title'];
    $description      = $_POST['event_description'];
    $event_date       = $_POST['event_date'];
    $category         = $_POST['event_category'];
    $event_start_time = isset($_POST['event_start_time']) ? $_POST['event_start_time'] : null;
    $event_end_time   = isset($_POST['event_end_time'])   ? $_POST['event_end_time']   : null;
    $event_days       = isset($_POST['event_days'])       ? intval($_POST['event_days']) : 1;
    $team_based       = isset($_POST['team_based'])       ? 1 : 0;
    $location         = isset($_POST['event_location'])   ? $_POST['event_location']   : null;
    $organizer_name   = isset($_POST['organizer_name'])   ? $_POST['organizer_name']   : null;
    $organizer_pos    = isset($_POST['organizer_position']) ? $_POST['organizer_position'] : null;
    $organizer_contact = isset($_POST['organizer_contact']) ? $_POST['organizer_contact'] : null;

    $image_set = '';
    $image_val = '';
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
        $target_dir  = "../../assets/img/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $ext         = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $filename    = 'event_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            $image_val = $filename;
            $image_set = ', image=?';
        }
    }

    if ($image_set) {
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, category=?, event_start_time=?, event_end_time=?, event_days=?, team_based=?, location=?, image=?, organizer_name=?, organizer_position=?, organizer_contact=? WHERE id=?");
        $stmt->bind_param("ssssssiissssi", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $image_val, $organizer_name, $organizer_pos, $organizer_contact, $event_id);
    } else {
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, category=?, event_start_time=?, event_end_time=?, event_days=?, team_based=?, location=?, organizer_name=?, organizer_position=?, organizer_contact=? WHERE id=?");
        $stmt->bind_param("ssssssiissssi", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $organizer_name, $organizer_pos, $organizer_contact, $event_id);
    }
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Re-save highlights
        $conn->query("DELETE FROM event_highlights WHERE event_id = $event_id");
        if (!empty($_POST['highlights'])) {
            foreach ($_POST['highlights'] as $i => $hl) {
                $hl = trim($hl);
                if ($hl === '') continue;
                $s = $conn->prepare("INSERT INTO event_highlights (event_id, highlight, sort_order) VALUES (?,?,?)");
                $s->bind_param("isi", $event_id, $hl, $i);
                $s->execute();
                $s->close();
            }
        }
        // Re-save schedule
        $conn->query("DELETE FROM event_schedule WHERE event_id = $event_id");
        if (!empty($_POST['schedule_time'])) {
            foreach ($_POST['schedule_time'] as $i => $time) {
                $time = trim($time);
                $act  = isset($_POST['schedule_activity'][$i]) ? trim($_POST['schedule_activity'][$i]) : '';
                $desc = isset($_POST['schedule_desc'][$i])     ? trim($_POST['schedule_desc'][$i])     : '';
                if ($time === '' && $act === '') continue;
                $s = $conn->prepare("INSERT INTO event_schedule (event_id, time_slot, activity, description, sort_order) VALUES (?,?,?,?,?)");
                $s->bind_param("isssi", $event_id, $time, $act, $desc, $i);
                $s->execute();
                $s->close();
            }
        }
    }
    return $success;
}

function get_events_by_month($conn, $year, $month)
{
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE YEAR(event_date)=? AND MONTH(event_date)=? ORDER BY event_date ASC");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_all_events($conn)
{
    $result = $conn->query("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events ORDER BY event_date ASC");
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    return $events;
}

function get_category_counts($conn)
{
    $categories = ['Academic', 'Sports', 'Cultural', 'Workshops', 'Conferences'];
    $counts = [];
    foreach ($categories as $cat) {
        $s = $conn->prepare("SELECT COUNT(*) as c FROM events WHERE category=?");
        $s->bind_param("s", $cat);
        $s->execute();
        $r = $s->get_result();
        $counts[$cat] = $r->fetch_assoc()['c'];
        $s->close();
    }
    return $counts;
}

function get_upcoming_events($conn, $limit = 10)
{
    $today = date("Y-m-d");
    $limit = intval($limit);
    $sql   = "SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date >= '$today' OR DATE_ADD(event_date, INTERVAL (event_days-1) DAY) >= '$today' ORDER BY event_date ASC LIMIT $limit";
    $result = $conn->query($sql);
    $events = [];
    if ($result) while ($row = $result->fetch_assoc()) $events[] = $row;
    return $events;
}

function get_events_happening_today($conn)
{
    $today = date("Y-m-d");
    $stmt  = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date=? ORDER BY event_start_time ASC");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_featured_events($conn)
{
    $today        = date("Y-m-d");
    $today_events = get_events_happening_today($conn);
    if (!empty($today_events)) return $today_events;
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date > ? ORDER BY event_date ASC LIMIT 5");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    if (!empty($events)) return $events;
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events ORDER BY event_date DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_featured_event($conn)
{
    $events = get_featured_events($conn);
    return !empty($events) ? $events[0] : null;
}

function delete_event($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// ============================================================
//  NEW: Applications / Groups / Teachers helpers
// ============================================================
function get_all_applications($conn, $event_id = null)
{
    if ($event_id) {
        $stmt = $conn->prepare("SELECT ea.*, e.title AS event_title FROM event_applications ea JOIN events e ON ea.event_id=e.id WHERE ea.event_id=? ORDER BY ea.applied_at DESC");
        $stmt->bind_param("i", $event_id);
    } else {
        $stmt = $conn->prepare("SELECT ea.*, e.title AS event_title FROM event_applications ea JOIN events e ON ea.event_id=e.id ORDER BY ea.applied_at DESC");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function update_application_status($conn, $app_id, $status)
{
    $allowed = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $allowed)) return false;
    $stmt = $conn->prepare("UPDATE event_applications SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $app_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_groups_for_event($conn, $event_id)
{
    $stmt = $conn->prepare("SELECT * FROM event_groups WHERE event_id=? ORDER BY id ASC");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        // Load members
        $ms = $conn->prepare("SELECT * FROM group_members WHERE group_id=?");
        $ms->bind_param("i", $row['id']);
        $ms->execute();
        $row['members'] = $ms->get_result()->fetch_all(MYSQLI_ASSOC);
        $ms->close();
        // Load teachers
        $ts = $conn->prepare("SELECT * FROM group_teachers WHERE group_id=?");
        $ts->bind_param("i", $row['id']);
        $ts->execute();
        $row['teachers'] = $ts->get_result()->fetch_all(MYSQLI_ASSOC);
        $ts->close();
        $groups[] = $row;
    }
    $stmt->close();
    return $groups;
}

function get_approved_applicants($conn, $event_id)
{
    $stmt = $conn->prepare("SELECT student_id, student_name FROM event_applications WHERE event_id=? AND status='Approved' ORDER BY student_name ASC");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function get_event_by_id($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    // highlights
    $s = $conn->prepare("SELECT highlight FROM event_highlights WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $id);
    $s->execute();
    $row['highlights'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    // schedule
    $s = $conn->prepare("SELECT * FROM event_schedule WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $id);
    $s->execute();
    $row['schedule'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $row;
}

// ============================================================
//  AJAX REQUEST HANDLER
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // ---------- existing actions ----------
        if ($action == 'add_event') {
            $success = insert_event($conn);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Event created!'] : ['status' => 'error', 'message' => 'Error creating event.']);
            exit;
        }
        if ($action == 'update_event' && isset($_POST['event_id'])) {
            $success = update_event_details($conn, $_POST['event_id']);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Event updated!'] : ['status' => 'error', 'message' => 'Error updating event.']);
            exit;
        }
        if ($action == 'delete_event' && isset($_POST['id'])) {
            $success = delete_event($conn, $_POST['id']);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Deleted.'] : ['status' => 'error', 'message' => 'Error.']);
            exit;
        }
        if ($action == 'get_events' && isset($_POST['year'], $_POST['month'])) {
            $events = get_events_by_month($conn, $_POST['year'], $_POST['month']);
            echo json_encode(['status' => 'success', 'events' => $events]);
            exit;
        }
        if ($action == 'get_all_events') {
            echo json_encode(['status' => 'success', 'events' => get_all_events($conn)]);
            exit;
        }
        if ($action == 'get_upcoming_events') {
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
            echo json_encode(['status' => 'success', 'events' => get_upcoming_events($conn, $limit)]);
            exit;
        }
        if ($action == 'get_featured_events') {
            echo json_encode(['status' => 'success', 'events' => get_featured_events($conn)]);
            exit;
        }
        if ($action == 'delete' && isset($_POST['id'])) {
            $success = delete_news($conn, $_POST['id']);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Deleted.'] : ['status' => 'error', 'message' => 'Error.']);
            exit;
        }
        if ($action == 'get_event' && isset($_POST['event_id'])) {
            $ev = get_event_by_id($conn, intval($_POST['event_id']));
            echo json_encode($ev ? ['status' => 'success', 'event' => $ev] : ['status' => 'error', 'message' => 'Not found.']);
            exit;
        }

        // ---------- Application actions ----------
        if ($action == 'get_applications') {
            $eid = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
            echo json_encode(['status' => 'success', 'applications' => get_all_applications($conn, $eid)]);
            exit;
        }
        if ($action == 'update_application_status' && isset($_POST['app_id'], $_POST['status'])) {
            $success = update_application_status($conn, intval($_POST['app_id']), $_POST['status']);
            echo json_encode($success ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Update failed.']);
            exit;
        }

        // ---------- Group actions ----------
        if ($action == 'create_group' && isset($_POST['event_id'], $_POST['group_name'])) {
            $eid  = intval($_POST['event_id']);
            $name = trim($_POST['group_name']);
            $stmt = $conn->prepare("INSERT INTO event_groups (event_id, group_name) VALUES (?,?)");
            $stmt->bind_param("is", $eid, $name);
            $ok   = $stmt->execute();
            $gid  = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'group_id' => $gid, 'group_name' => $name] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'delete_group' && isset($_POST['group_id'])) {
            $gid  = intval($_POST['group_id']);
            $stmt = $conn->prepare("DELETE FROM event_groups WHERE id=?");
            $stmt->bind_param("i", $gid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'add_member' && isset($_POST['group_id'], $_POST['student_id'], $_POST['student_name'])) {
            $gid   = intval($_POST['group_id']);
            $sid   = trim($_POST['student_id']);
            $sname = trim($_POST['student_name']);
            $stmt  = $conn->prepare("INSERT IGNORE INTO group_members (group_id, student_id, student_name) VALUES (?,?,?)");
            $stmt->bind_param("iss", $gid, $sid, $sname);
            $ok    = $stmt->execute();
            $mid   = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'member_id' => $mid] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'remove_member' && isset($_POST['member_id'])) {
            $mid  = intval($_POST['member_id']);
            $stmt = $conn->prepare("DELETE FROM group_members WHERE id=?");
            $stmt->bind_param("i", $mid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'add_teacher' && isset($_POST['group_id'], $_POST['teacher_name'])) {
            $gid  = intval($_POST['group_id']);
            $name = trim($_POST['teacher_name']);
            $stmt = $conn->prepare("INSERT INTO group_teachers (group_id, teacher_name) VALUES (?,?)");
            $stmt->bind_param("is", $gid, $name);
            $ok   = $stmt->execute();
            $tid  = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'teacher_id' => $tid] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'remove_teacher' && isset($_POST['teacher_id'])) {
            $tid  = intval($_POST['teacher_id']);
            $stmt = $conn->prepare("DELETE FROM group_teachers WHERE id=?");
            $stmt->bind_param("i", $tid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'get_groups' && isset($_POST['event_id'])) {
            $groups = get_groups_for_event($conn, intval($_POST['event_id']));
            echo json_encode(['status' => 'success', 'groups' => $groups]);
            exit;
        }
        if ($action == 'get_approved_applicants' && isset($_POST['event_id'])) {
            $applicants = get_approved_applicants($conn, intval($_POST['event_id']));
            echo json_encode(['status' => 'success', 'applicants' => $applicants]);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
        $success = insert_news($conn);
        echo json_encode($success ? ['status' => 'success', 'message' => 'News post created!'] : ['status' => 'error', 'message' => 'Error.']);
    }
    exit;
}

// Handle regular POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['action'])) {
        $success = insert_news($conn);
        if ($success) echo "<script>alert('News post created successfully!'); window.location.reload();</script>";
        else          echo "<script>alert('Error creating news post.');</script>";
    }
}

// Data for the page
$featured_events  = get_featured_events($conn);
$today            = date("Y-m-d");
$featured_event   = !empty($featured_events) ? $featured_events[0] : null;
$is_current_event = !empty($featured_events) && $featured_events[0]['event_date'] == $today;
$category_counts  = get_category_counts($conn);
$all_events_list  = get_all_events($conn);
$all_applications = get_all_applications($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - School Admin Dashboard</title>
    <link rel="stylesheet" href="../admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="../../assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="../../assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --moss-green-primary: #4A5D23;
            --moss-green-light: #6B7F3A;
            --moss-green-lighter: #7A8F4A;
            --moss-green-lightest: #8A9F5A;
            --white: #FFFFFF;
            --gray-light: #F8F9FA;
            --text-primary: #212529;
            --text-secondary: #6C757D;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px
        }

        .toast-notification {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
            color: #fff;
            font-weight: 500;
            min-width: 300px;
            max-width: 400px;
            animation: slideInRight .4s ease-out, fadeOut .4s ease-in 3.6s forwards;
            backdrop-filter: blur(10px)
        }

        .toast-notification.success {
            background: linear-gradient(135deg, #28a745, #20c997)
        }

        .toast-notification.error {
            background: linear-gradient(135deg, #dc3545, #e74c3c)
        }

        .toast-notification.warning {
            background: linear-gradient(135deg, #ffc107, #ffb300);
            color: #212529
        }

        .toast-notification.info {
            background: linear-gradient(135deg, #17a2b8, #1abc9c)
        }

        .toast-notification i {
            font-size: 20px;
            flex-shrink: 0
        }

        .toast-notification .toast-message {
            flex: 1;
            font-size: 14px
        }

        .toast-notification .toast-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            font-size: 18px;
            opacity: .7;
            transition: opacity .2s
        }

        .toast-notification .toast-close:hover {
            opacity: 1
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0
            }

            to {
                transform: translateX(0);
                opacity: 1
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1
            }

            to {
                opacity: 0;
                transform: translateX(100%)
            }
        }

        .modal-content {
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .15);
            overflow: hidden
        }

        .modal-header {
            border-bottom: 1px solid #E9ECEF;
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, var(--moss-green-primary), var(--moss-green-light));
            color: #fff
        }

        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.25rem
        }

        .modal-header .btn-close {
            filter: invert(1);
            opacity: .8
        }

        .modal-body {
            padding: 2rem
        }

        .modal-footer {
            border-top: 1px solid #E9ECEF;
            padding: 1.5rem 2rem;
            background: #f8f9fa
        }

        .form-field-wrapper {
            position: relative;
            margin-bottom: 1.25rem
        }

        .form-field-wrapper .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
            z-index: 2;
            transition: color .3s ease
        }

        .form-field-wrapper textarea+.field-icon {
            top: 20px;
            transform: none
        }

        .form-field-wrapper .form-control,
        .form-field-wrapper .form-select {
            padding: 0.875rem 1rem 0.875rem 42px;
            border: 2px solid #E9ECEF;
            border-radius: 10px;
            font-size: 1rem;
            transition: all .3s ease;
            background: #fafbfc
        }

        .form-field-wrapper .form-control:focus,
        .form-field-wrapper .form-select:focus {
            border-color: var(--moss-green-primary);
            box-shadow: 0 0 0 4px rgba(74, 93, 35, .15);
            background: #fff;
            outline: none
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: .5rem;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .required-star {
            color: #dc3545;
            font-size: 12px
        }

        .btn-primary {
            background-color: var(--moss-green-primary);
            border-color: var(--moss-green-primary);
            font-weight: 600;
            padding: .75rem 1.75rem;
            border-radius: 10px;
            transition: all .3s ease
        }

        .btn-primary:hover {
            background-color: var(--moss-green-light);
            border-color: var(--moss-green-light);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(74, 93, 35, .35)
        }

        .form-section-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, .05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04)
        }

        .form-section-card .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--moss-green-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: .75rem;
            border-bottom: 2px solid var(--moss-green-lightest)
        }

        .calendar-container {
            max-width: 100%;
            margin: 0 auto
        }

        .calendar-wrapper {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .1);
            overflow: hidden
        }

        .month {
            padding: 20px;
            width: 100%;
            background: linear-gradient(135deg, #1abc9c, #16a085);
            text-align: center
        }

        .month ul {
            margin: 0;
            padding: 0
        }

        .month ul li {
            color: #fff;
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 500
        }

        .month .prev,
        .month .next {
            padding-top: 8px;
            cursor: pointer;
            color: #fff;
            transition: all .3s ease;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%
        }

        .month .prev:hover,
        .month .next:hover {
            background: rgba(255, 255, 255, .2);
            transform: scale(1.1)
        }

        .weekdays {
            margin: 0;
            padding: 12px 0;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-around;
            border-bottom: 1px solid #eee
        }

        .weekdays li {
            display: inline-block;
            width: 13.6%;
            color: #666;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase
        }

        .days {
            padding: 8px;
            background: #fafafa;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start
        }

        .days li {
            list-style-type: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14.28%;
            text-align: center;
            margin-bottom: 4px;
            font-size: 14px;
            color: #555;
            padding: 10px 0;
            position: relative;
            cursor: pointer;
            transition: all .25s ease;
            border-radius: 10px;
            min-height: 44px
        }

        .days li:hover {
            background: linear-gradient(135deg, var(--moss-green-light), var(--moss-green-lighter));
            color: #fff;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(74, 93, 35, .3)
        }

        .days li.other-month {
            color: #ccc
        }

        .days li.today {
            font-weight: bold;
            color: #1abc9c
        }

        .days li.today::after {
            content: '';
            position: absolute;
            bottom: 6px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #1abc9c;
            border-radius: 50%
        }

        .days li .event-dot {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #1abc9c
        }

        .days li .event-dot.academic {
            background-color: #3498db
        }

        .days li .event-dot.sports {
            background-color: #e74c3c
        }

        .days li .event-dot.cultural {
            background-color: #9b59b6
        }

        .days li .event-dot.workshops {
            background-color: #f39c12
        }

        .days li .event-dot.conferences {
            background-color: #1abc9c
        }

        .event-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid rgba(0, 0, 0, .05);
            transition: all .3s ease
        }

        .event-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12)
        }

        .event-item .event-date {
            flex-shrink: 0;
            width: 70px;
            text-align: center;
            padding: 12px;
            background: linear-gradient(135deg, #1abc9c, #16a085);
            border-radius: 12px;
            color: #fff
        }

        .event-item .event-date .day {
            display: block;
            font-size: 28px;
            font-weight: 700;
            line-height: 1
        }

        .event-item .event-date .month {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0;
            background: transparent
        }

        .event-item .event-content {
            flex: 1
        }

        .event-item .event-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333
        }

        .event-item .event-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 10px;
            flex-wrap: wrap
        }

        .event-item .event-meta p {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .85rem;
            color: #666;
            margin: 0
        }

        .event-item .event-meta i {
            color: #1abc9c
        }

        .event-item .btn-event {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1abc9c;
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
            transition: gap .3s ease
        }

        .event-item .btn-event:hover {
            gap: 10px
        }

        .event-item-category {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase
        }

        .event-item-category.academic {
            background-color: #3498db;
            color: #fff
        }

        .event-item-category.sports {
            background-color: #e74c3c;
            color: #fff
        }

        .event-item-category.cultural {
            background-color: #9b59b6;
            color: #fff
        }

        .event-item-category.workshops {
            background-color: #f39c12;
            color: #fff
        }

        .event-item-category.conferences {
            background-color: #1abc9c;
            color: #fff
        }

        .event-modal .modal-header {
            background: linear-gradient(135deg, #1abc9c, #16a085);
            color: #fff;
            border-radius: 0
        }

        .event-modal .event-date-header {
            background: linear-gradient(135deg, #1abc9c, #16a085);
            color: #fff;
            padding: 20px 24px;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .events-list-modal {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 8px
        }

        .event-list-item {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-left: 4px solid #1abc9c;
            transition: all .3s ease;
            position: relative
        }

        .event-list-item:hover {
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, .1)
        }

        .event-list-item h6 {
            margin: 0 0 6px;
            color: #333;
            font-weight: 600
        }

        .event-list-item p {
            margin: 0;
            font-size: 13px;
            color: #666
        }

        .event-list-item .delete-event-btn {
            position: absolute;
            right: 12px;
            top: 12px;
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            opacity: .5;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .event-list-item .delete-event-btn:hover {
            opacity: 1;
            background: rgba(220, 53, 69, .1)
        }

        .events-page-sidebar .sidebar-item {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
            border: 1px solid rgba(0, 0, 0, .05)
        }

        .events-page-sidebar .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1abc9c
        }

        .categories ul {
            list-style: none;
            padding: 0;
            margin: 0
        }

        .categories ul li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0
        }

        .categories ul li:last-child {
            border-bottom: none
        }

        .categories ul li a {
            display: flex;
            justify-content: space-between;
            color: #555;
            text-decoration: none;
            font-weight: 500
        }

        .categories ul li a:hover {
            color: #1abc9c
        }

        .categories ul li a span {
            background: #f0f0f0;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: .8rem
        }

        .pagination-wrapper {
            margin-top: 30px
        }

        .pagination .page-link {
            border: none;
            padding: 10px 16px;
            margin: 0 4px;
            border-radius: 8px;
            color: #555;
            font-weight: 500
        }

        .pagination .page-link:hover {
            background: #1abc9c;
            color: #fff
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1abc9c, #16a085);
            color: #fff
        }

        .page-content {
            margin-left: 0;
            width: calc(100vw - 260px);
            max-width: 100%;
            padding: 0 20px
        }

        .text-muted {
            text-align: center !important
        }

        .dashboard-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            flex-direction: column;
            gap: 16px
        }

        .dashboard-loading .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top-color: #4A5D23;
            border-radius: 50%;
            animation: spin 1s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .nav-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 16px
        }

        .featured-event-carousel {
            position: relative;
            overflow: hidden
        }

        .featured-event-slide {
            display: none;
            animation: fadeIn .5s ease-in-out
        }

        .featured-event-slide.active {
            display: block
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px)
            }

            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @media(max-width:768px) {
            .event-item {
                flex-direction: column;
                gap: 12px
            }

            .event-item .event-date {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 12px
            }
        }

        /* ---- NEW: Applications & Groups UI ---- */
        .admin-section-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
            padding: 28px;
            margin-bottom: 30px;
        }

        .admin-section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--moss-green-primary);
            border-bottom: 3px solid var(--moss-green-lightest);
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-section-title i {
            color: var(--moss-green-light);
        }

        .applications-table th {
            background: linear-gradient(135deg, var(--moss-green-primary), var(--moss-green-light));
            color: #fff;
            font-weight: 600;
            font-size: .85rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .applications-table td {
            vertical-align: middle;
            font-size: .9rem;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
        }

        .status-badge.Pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.Approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.Rejected {
            background: #f8d7da;
            color: #842029;
        }

        .btn-approve {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: .8rem;
            cursor: pointer;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: .8rem;
            cursor: pointer;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .group-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            border: 1px solid #e9ecef;
        }

        .group-card .group-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--moss-green-primary);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .group-card .member-tag,
        .group-card .teacher-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: .82rem;
            margin: 3px;
        }

        .group-card .teacher-tag {
            background: #e8f5e9;
            border-color: #a5d6a7;
        }

        .group-card .remove-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: .75rem;
            padding: 0 0 0 4px;
        }

        .event-select-filter {
            max-width: 320px;
            margin-bottom: 20px;
        }

        .highlight-row,
        .schedule-row-input {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .highlight-row input,
        .schedule-row-input input {
            flex: 1;
        }

        .btn-remove-row {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
        }

        .btn-add-row {
            font-size: .85rem;
            color: var(--moss-green-primary);
            background: none;
            border: 1px dashed var(--moss-green-light);
            border-radius: 8px;
            padding: 6px 14px;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-add-row:hover {
            background: #f1f5e9;
        }

        .manage-event-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            font-size: .85rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-manage-event {
            background: linear-gradient(135deg, var(--moss-green-primary), var(--moss-green-light));
            color: #fff;
            border: none;
        }

        .btn-manage-event:hover {
            opacity: .9;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>
    <div id="navigation-container">
        <div class="dashboard-loading">
            <div class="spinner"></div>
            <p>Loading navigation...</p>
        </div>
    </div>

    <main class="main page-content" id="main-content" style="display:none;">
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">Announcements & Events</h1>
                            <p class="mb-0">Manage events, applications, and group assignments at Buyoan National High School.</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="../admin_dashboard.php">Home</a></li>
                        <li class="current">Announcements</li>
                    </ol>
                </div>
            </nav>
        </div>

        <?php
        $bannerImage = '';
        if ($featured_event) {
            switch (strtolower($featured_event['category'])) {
                case 'academic':
                    $bannerImage = '../../admin_account/admin_assets/pics/academics.jpg';
                    break;
                case 'sports':
                    $bannerImage = '../../admin_account/admin_assets/pics/sports.jpg';
                    break;
                case 'cultural':
                    $bannerImage = '../../admin_account/admin_assets/pics/culture.jpg';
                    break;
                case 'workshops':
                    $bannerImage = '../../admin_account/admin_assets/pics/workshop.jpg';
                    break;
                case 'conferences':
                    $bannerImage = '../../admin_account/admin_assets/pics/conference.jpg';
                    break;
            }
        }
        $bannerStyle = $bannerImage
            ? "background:linear-gradient(135deg,rgba(0,0,0,.6),rgba(0,0,0,.4)),url('$bannerImage');background-size:cover;background-position:center;"
            : "background:linear-gradient(135deg,#1abc9c,#16a085);";
        ?>

        <section class="featured-event-section section" style="margin:0;padding:0;">
            <div class="container-fluid" style="margin:0;padding:0;max-width:100%;">
                <div class="featured-event-banner" style="<?php echo $bannerStyle; ?> padding:40px 20px;margin:20px 0;border-radius:0;color:#fff;text-align:center;">
                    <?php if ($featured_event): ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <?php if ($is_current_event): ?>
                                    <span class="badge bg-warning text-dark mb-3" style="font-size:14px;padding:8px 16px;"><i class="fas fa-calendar-check me-2"></i>HAPPENING NOW</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark mb-3" style="font-size:14px;padding:8px 16px;"><i class="fas fa-star me-2"></i>UPCOMING EVENT</span>
                                <?php endif; ?>
                                <h2 style="font-size:2.5rem;font-weight:700;margin:15px 0;"><?php echo htmlspecialchars($featured_event['title']); ?></h2>
                                <p style="font-size:1.2rem;margin-bottom:15px;">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <?php echo (new DateTime($featured_event['event_date']))->format('F j, Y'); ?>
                                </p>
                                <?php if ($featured_event['description']): ?>
                                    <p style="font-size:1rem;opacity:.9;max-width:600px;margin:0 auto 20px;"><?php echo htmlspecialchars($featured_event['description']); ?></p>
                                <?php endif; ?>
                                <span class="event-item-category <?php echo strtolower($featured_event['category']); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:8px 20px;border-radius:25px;font-size:14px;font-weight:600;text-transform:uppercase;background:#fff;color:#1abc9c;">
                                    <?php echo htmlspecialchars($featured_event['category']); ?>
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <h2 style="font-size:2rem;font-weight:700;margin:15px 0;"><i class="fa-regular fa-calendar-check"></i> No Upcoming Events</h2>
                                <p style="font-size:1rem;opacity:.9;">Check back later for upcoming events at Buyoan National High School.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ======================================================
         EVENT MODAL (add event on calendar click)
    ====================================================== -->
        <div class="modal fade event-modal" id="eventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="event-date-header">
                        <h5 class="modal-title">Add Event</h5>
                        <div class="event-date-display" id="eventDateDisplay"></div>
                    </div>
                    <div class="modal-body">
                        <form action="" method="POST" id="eventForm" enctype="multipart/form-data">
                            <input type="hidden" id="eventDate" name="event_date">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="text" class="form-control" id="eventTitle" name="event_title" placeholder="Event title" required>
                                        <i class="fas fa-calendar-check field-icon"></i>
                                    </div>
                                    <label class="form-label">Event Title <span class="required-star">*</span></label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <select class="form-select" id="eventCategory" name="event_category" required>
                                            <option value="">Category</option>
                                            <option value="Academic">Academic</option>
                                            <option value="Sports">Sports</option>
                                            <option value="Cultural">Cultural</option>
                                            <option value="Workshops">Workshops</option>
                                            <option value="Conferences">Conferences</option>
                                        </select>
                                        <i class="fas fa-tag field-icon"></i>
                                    </div>
                                    <label class="form-label">Category <span class="required-star">*</span></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-field-wrapper">
                                    <input type="text" class="form-control" name="event_location" placeholder="e.g. Main Auditorium">
                                    <i class="fas fa-map-marker-alt field-icon"></i>
                                </div>
                                <label class="form-label">Location</label>
                            </div>
                            <div class="mb-3">
                                <div class="form-field-wrapper">
                                    <textarea class="form-control" id="eventDescription" name="event_description" placeholder="Event description" rows="3"></textarea>
                                    <i class="fas fa-align-left field-icon"></i>
                                </div>
                                <label class="form-label">Description</label>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="time" class="form-control" name="event_start_time">
                                        <i class="fas fa-clock field-icon"></i>
                                    </div>
                                    <label class="form-label">Start Time</label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="time" class="form-control" name="event_end_time">
                                        <i class="fas fa-clock field-icon"></i>
                                    </div>
                                    <label class="form-label">End Time</label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="number" class="form-control" id="eventDays" name="event_days" min="1" value="1">
                                        <i class="fas fa-calendar-day field-icon"></i>
                                    </div>
                                    <label class="form-label">Duration (days)</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Image</label>
                                <input type="file" class="form-control" name="event_image" accept="image/*">
                            </div>
                            <!-- Organizer -->
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="text" class="form-control" name="organizer_name" placeholder="Organizer name">
                                        <i class="fas fa-user field-icon"></i>
                                    </div>
                                    <label class="form-label">Organizer Name</label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="text" class="form-control" name="organizer_position" placeholder="Position / Title">
                                        <i class="fas fa-id-badge field-icon"></i>
                                    </div>
                                    <label class="form-label">Position</label>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-field-wrapper">
                                        <input type="text" class="form-control" name="organizer_contact" placeholder="Email or phone">
                                        <i class="fas fa-phone field-icon"></i>
                                    </div>
                                    <label class="form-label">Contact Info</label>
                                </div>
                            </div>
                            <!-- Highlights -->
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-star me-1"></i>Event Highlights</label>
                                <div id="highlightsContainer">
                                    <div class="highlight-row">
                                        <input type="text" class="form-control form-control-sm" name="highlights[]" placeholder="Highlight item">
                                        <button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-row mt-1" onclick="addHighlightRow()"><i class="fas fa-plus me-1"></i>Add Highlight</button>
                            </div>
                            <!-- Schedule -->
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-list-ol me-1"></i>Event Schedule</label>
                                <div id="scheduleContainer">
                                    <div class="schedule-row-input">
                                        <input type="text" class="form-control form-control-sm" name="schedule_time[]" placeholder="Time (e.g. 9:00 AM - 10:00 AM)" style="width:200px;flex:none;">
                                        <input type="text" class="form-control form-control-sm" name="schedule_activity[]" placeholder="Activity">
                                        <input type="text" class="form-control form-control-sm" name="schedule_desc[]" placeholder="Description (optional)">
                                        <button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>
                                    </div>
                                </div>
                                <button type="button" class="btn-add-row mt-1" onclick="addScheduleRow()"><i class="fas fa-plus me-1"></i>Add Schedule Row</button>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="teamBased" name="team_based">
                                    <label class="form-check-label" for="teamBased"><i class="fas fa-users me-1"></i> Team-based Event</label>
                                </div>
                            </div>
                        </form>
                        <div class="mt-4">
                            <h6 class="fw-bold">Events on this date:</h6>
                            <div class="events-list-modal" id="eventsListForDate"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" form="eventForm" class="btn btn-primary" id="eventSubmitBtn"><i class="fas fa-save me-2"></i>Add Event</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================
         EDIT EVENT DETAILS MODAL
    ====================================================== -->
        <div class="modal fade" id="editEventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Event Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editEventForm" enctype="multipart/form-data">
                            <input type="hidden" id="editEventId" name="event_id">
                            <input type="hidden" name="event_date" id="editEventDate">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Event Title <span class="required-star">*</span></label>
                                    <input type="text" class="form-control" id="editEventTitle" name="event_title" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Category <span class="required-star">*</span></label>
                                    <select class="form-select" id="editEventCategory" name="event_category" required>
                                        <option value="Academic">Academic</option>
                                        <option value="Sports">Sports</option>
                                        <option value="Cultural">Cultural</option>
                                        <option value="Workshops">Workshops</option>
                                        <option value="Conferences">Conferences</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" id="editEventLocation" name="event_location">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" class="form-control" id="editEventStartTime" name="event_start_time">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" class="form-control" id="editEventEndTime" name="event_end_time">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="editEventDescription" name="event_description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Image <small class="text-muted">(leave blank to keep existing)</small></label>
                                <input type="file" class="form-control" name="event_image" accept="image/*">
                                <div id="currentImagePreview" class="mt-2"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Organizer Name</label>
                                    <input type="text" class="form-control" id="editOrgName" name="organizer_name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Organizer Position</label>
                                    <input type="text" class="form-control" id="editOrgPosition" name="organizer_position">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Organizer Contact</label>
                                    <input type="text" class="form-control" id="editOrgContact" name="organizer_contact">
                                </div>
                            </div>
                            <!-- Highlights -->
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-star me-1"></i>Event Highlights</label>
                                <div id="editHighlightsContainer"></div>
                                <button type="button" class="btn-add-row mt-1" onclick="addEditHighlightRow()"><i class="fas fa-plus me-1"></i>Add Highlight</button>
                            </div>
                            <!-- Schedule -->
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-list-ol me-1"></i>Event Schedule</label>
                                <div id="editScheduleContainer"></div>
                                <button type="button" class="btn-add-row mt-1" onclick="addEditScheduleRow()"><i class="fas fa-plus me-1"></i>Add Row</button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveEditEventBtn"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================
         MAIN EVENTS LIST + CALENDAR
    ====================================================== -->
        <section id="events-2" class="events-2 section">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="events-list" id="eventsListContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                <p class="mt-3 text-muted">Loading events...</p>
                            </div>
                        </div>
                        <div class="pagination-wrapper">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1"><i class="bi bi-chevron-left"></i></a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="events-page-sidebar">
                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Upcoming Events</h3>
                                <div class="calendar-container">
                                    <div class="calendar-wrapper">
                                        <div class="month" id="calendarMonth">
                                            <ul>
                                                <li class="prev" onclick="changeMonth(-1)">&#10094;</li>
                                                <li class="next" onclick="changeMonth(1)">&#10095;</li>
                                                <li id="monthYearDisplay"></li>
                                            </ul>
                                        </div>
                                        <ul class="weekdays">
                                            <li>Su</li>
                                            <li>Mo</li>
                                            <li>Tu</li>
                                            <li>We</li>
                                            <li>Th</li>
                                            <li>Fr</li>
                                            <li>Sa</li>
                                        </ul>
                                        <ul class="days" id="calendarDays"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Event Categories</h3>
                                <div class="categories">
                                    <ul>
                                        <li><a href="#">Academic <span>(<?php echo $category_counts['Academic']; ?>)</span></a></li>
                                        <li><a href="#">Sports <span>(<?php echo $category_counts['Sports']; ?>)</span></a></li>
                                        <li><a href="#">Cultural <span>(<?php echo $category_counts['Cultural']; ?>)</span></a></li>
                                        <li><a href="#">Workshops <span>(<?php echo $category_counts['Workshops']; ?>)</span></a></li>
                                        <li><a href="#">Conferences <span>(<?php echo $category_counts['Conferences']; ?>)</span></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================
         SECTION: EVENT JOIN APPLICATIONS
    ====================================================== -->
        <section class="container mb-5">
            <div class="admin-section-card">
                <div class="admin-section-title">
                    <i class="fas fa-clipboard-list"></i> Event Join Applications
                </div>

                <!-- Filter by event -->
                <div class="event-select-filter">
                    <label class="form-label mb-1"><i class="fas fa-filter me-1"></i>Filter by Event</label>
                    <select class="form-select" id="appEventFilter" onchange="loadApplications()">
                        <option value="">All Events</option>
                        <?php foreach ($all_events_list as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover applications-table" id="applicationsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Event</th>
                                <th>Applied</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsBody">
                            <?php if (empty($all_applications)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No applications yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_applications as $i => $app): ?>
                                    <tr id="app-row-<?php echo $app['id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($app['student_id']); ?></code></td>
                                        <td><?php echo htmlspecialchars($app['email'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($app['phone'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($app['event_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                        <td><span class="status-badge <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span></td>
                                        <td>
                                            <?php if ($app['status'] !== 'Approved'): ?>
                                                <button class="btn-approve me-1" onclick="updateAppStatus(<?php echo $app['id']; ?>,'Approved')"><i class="fas fa-check me-1"></i>Approve</button>
                                            <?php endif; ?>
                                            <?php if ($app['status'] !== 'Rejected'): ?>
                                                <button class="btn-reject" onclick="updateAppStatus(<?php echo $app['id']; ?>,'Rejected')"><i class="fas fa-times me-1"></i>Reject</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ======================================================
         SECTION: GROUP MANAGEMENT
    ====================================================== -->
        <section class="container mb-5">
            <div class="admin-section-card">
                <div class="admin-section-title">
                    <i class="fas fa-users"></i> Group Management
                </div>

                <div class="row align-items-end mb-4">
                    <div class="col-md-5">
                        <label class="form-label"><i class="fas fa-calendar-alt me-1"></i>Select Event</label>
                        <select class="form-select" id="groupEventSelect" onchange="loadGroupsForEvent()">
                            <option value="">— Choose event —</option>
                            <?php foreach ($all_events_list as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="fas fa-users me-1"></i>New Group Name</label>
                        <input type="text" class="form-control" id="newGroupName" placeholder="e.g. Group A">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="createGroup()"><i class="fas fa-plus me-1"></i>Create Group</button>
                    </div>
                </div>

                <div id="groupsContainer">
                    <p class="text-muted text-center py-3">Select an event to manage its groups.</p>
                </div>
            </div>
        </section>

    </main><!-- end #main-content -->

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="../../assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../admin_assets/js/admin_script.js"></script>

    <script>
        // ============================================================
        //  TOAST
        // ============================================================
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast-notification ' + type;
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            toast.innerHTML = `<i class="${icons[type]}"></i><span class="toast-message">${message}</span><button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
            container.appendChild(toast);
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, duration);
        }

        // ============================================================
        //  NAV LOADING (unchanged from original)
        // ============================================================
        function loadNavigation() {
            const container = document.getElementById('navigation-container');
            const currentPath = window.location.pathname;
            const navPath = currentPath.includes('/announcements/') ? '../admin_nav.php' : 'admin_nav.php';
            fetch(navPath)
                .then(r => {
                    if (!r.ok) throw new Error();
                    return r.text();
                })
                .then(data => {
                    container.innerHTML = data;
                    initializeNavigation();
                    document.getElementById('main-content').style.display = 'block';
                })
                .catch(() => {
                    container.innerHTML = '<div class="nav-error"><i class="fas fa-exclamation-triangle"></i><h3>Unable to Load Navigation</h3><button class="btn-retry" onclick="loadNavigation()">Try Again</button></div>';
                });
        }

        function initializeNavigation() {
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) mainDiv.appendChild(pageContent);
            fixAllNavLinks();
            initDropdowns();
        }

        function getAdminBase() {
            const parts = window.location.pathname.split('/');
            const idx = parts.indexOf('admin_account');
            if (idx !== -1) return parts.slice(0, idx + 1).join('/') + '/';
            return window.location.pathname.split('/').slice(0, -1).join('/') + '/';
        }

        function fixAllNavLinks() {
            const adminBase = getAdminBase();
            document.querySelectorAll('.sidebar a[href], .topbar a[href], .user-menu a[href]').forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('http') || href.startsWith('/')) return;
                if (href.startsWith('admin_account/')) link.setAttribute('href', adminBase + href.replace('admin_account/', ''));
                else if (!href.startsWith('../') && !href.startsWith('./')) link.setAttribute('href', adminBase + href);
            });
            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                item.setAttribute('href', getAdminBase() + 'announcements/' + item.getAttribute('data-page'));
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                const fresh = toggle.cloneNode(true);
                toggle.parentNode.replaceChild(fresh, toggle);
                fresh.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    const isActive = dropdown.classList.contains('active');
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                    if (!isActive) dropdown.classList.add('active');
                });
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('.dropdown')) document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            });
        }

        // ============================================================
        //  DYNAMIC ROW HELPERS (highlights & schedule)
        // ============================================================
        function addHighlightRow() {
            const c = document.getElementById('highlightsContainer');
            const div = document.createElement('div');
            div.className = 'highlight-row';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="highlights[]" placeholder="Highlight item"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addScheduleRow() {
            const c = document.getElementById('scheduleContainer');
            const div = document.createElement('div');
            div.className = 'schedule-row-input';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="schedule_time[]" placeholder="Time" style="width:200px;flex:none;"><input type="text" class="form-control form-control-sm" name="schedule_activity[]" placeholder="Activity"><input type="text" class="form-control form-control-sm" name="schedule_desc[]" placeholder="Description (optional)"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addEditHighlightRow(val = '') {
            const c = document.getElementById('editHighlightsContainer');
            const div = document.createElement('div');
            div.className = 'highlight-row';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="highlights[]" value="${escHtml(val)}" placeholder="Highlight item"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addEditScheduleRow(time = '', act = '', desc = '') {
            const c = document.getElementById('editScheduleContainer');
            const div = document.createElement('div');
            div.className = 'schedule-row-input';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="schedule_time[]" value="${escHtml(time)}" placeholder="Time" style="width:200px;flex:none;"><input type="text" class="form-control form-control-sm" name="schedule_activity[]" value="${escHtml(act)}" placeholder="Activity"><input type="text" class="form-control form-control-sm" name="schedule_desc[]" value="${escHtml(desc)}" placeholder="Description (optional)"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function removeRow(btn) {
            btn.parentElement.remove();
        }

        function escHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ============================================================
        //  AJAX HELPER
        // ============================================================
        function ajaxPost(params) {
            return fetch('', {
                method: 'POST',
                body: new URLSearchParams(params),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            }).then(r => r.json());
        }

        function ajaxFormData(formData) {
            formData.append('_method', 'post');
            return fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json());
        }

        // ============================================================
        //  CALENDAR + EVENTS (from original, intact)
        // ============================================================
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let eventsData = {};
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        function initCalendar() {
            loadEventsForMonth(currentYear, currentMonth + 1);
            loadUpcomingEvents();
            renderCalendar(currentYear, currentMonth);
        }

        function loadEventsForMonth(year, month) {
            ajaxPost({
                action: 'get_events',
                year,
                month
            }).then(data => {
                if (data.status === 'success') {
                    eventsData = {};
                    data.events.forEach(ev => {
                        const k = ev.event_date;
                        if (!eventsData[k]) eventsData[k] = [];
                        eventsData[k].push(ev);
                    });
                    renderCalendar(currentYear, currentMonth);
                }
            }).catch(e => console.error(e));
        }

        function formatEventDateRange(dateStr, days) {
            const start = new Date(dateStr);
            const d = parseInt(days) || 1;
            if (d === 1) return '1day';
            const end = new Date(start);
            end.setDate(start.getDate() + d - 1);
            const sm = start.getMonth(),
                em = end.getMonth(),
                sy = start.getFullYear(),
                ey = end.getFullYear();
            if (sm === em && sy === ey) return monthNames[sm] + ' ' + start.getDate() + '-' + end.getDate() + ', ' + sy;
            if (sy === ey) return monthNames[sm] + ' ' + start.getDate() + ' - ' + monthNames[em] + ' ' + end.getDate() + ', ' + sy;
            return monthNames[sm] + ' ' + start.getDate() + ', ' + sy + ' - ' + monthNames[em] + ' ' + end.getDate() + ', ' + ey;
        }

        function formatEventTime(s, e) {
            const fmt = t => {
                if (!t) return '';
                const [h, m] = t.split(':');
                const hr = parseInt(h);
                return (hr % 12 || 12) + ':' + m + (hr >= 12 ? ' PM' : ' AM');
            };
            if (s && e) return fmt(s) + ' - ' + fmt(e);
            return fmt(s || e);
        }

        function createEventHTML(event) {
            const d = new Date(event.event_date);
            const cat = event.category.toLowerCase();
            const dateRange = formatEventDateRange(event.event_date, event.event_days);
            const time = formatEventTime(event.event_start_time, event.event_end_time);
            const btnText = (event.team_based == 1 || event.team_based === true) ? 'Join Now' : 'Learn More';
            return `<div class="event-item">
        <div class="event-date"><span class="day">${d.getDate()}</span><span class="month">${monthNames[d.getMonth()].toUpperCase().slice(0,3)}</span></div>
        <div class="event-content">
            <h3>${escHtml(event.title)}</h3>
            <div class="event-meta">
                ${time ? `<p><i class="bi bi-clock"></i> ${time}</p>` : ''}
                <p><i class="bi bi-calendar-event"></i> ${dateRange}</p>
            </div>
            ${event.description ? `<p>${escHtml(event.description)}</p>` : ''}
            <div class="d-flex gap-2 flex-wrap mt-2">
                <a href="event-details.php?id=${event.id}" class="btn-event">${btnText} <i class="bi bi-arrow-right"></i></a>
                <button class="manage-event-btn btn-manage-event" onclick="openEditModal(${event.id})"><i class="fas fa-edit me-1"></i>Edit Details</button>
            </div>
        </div>
    </div>`;
        }

        function loadUpcomingEvents() {
            ajaxPost({
                action: 'get_upcoming_events',
                limit: 10
            }).then(data => {
                const c = document.getElementById('eventsListContainer');
                if (data.status === 'success' && data.events.length > 0) {
                    c.innerHTML = data.events.map(createEventHTML).join('');
                } else {
                    c.innerHTML = '<div class="text-center py-5"><p class="text-muted">No upcoming events. Click on a date in the calendar to add one!</p></div>';
                }
            }).catch(() => {
                document.getElementById('eventsListContainer').innerHTML = '<div class="text-center py-5"><p class="text-muted">Error loading events.</p></div>';
            });
        }

        function renderCalendar(year, month) {
            const disp = document.getElementById('monthYearDisplay');
            const days = document.getElementById('calendarDays');
            disp.innerHTML = monthNames[month] + '<br><span style="font-size:18px">' + year + '</span>';
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrev = new Date(year, month, 0).getDate();
            const today = new Date();
            let html = '';
            for (let i = firstDay - 1; i >= 0; i--) html += `<li class="other-month">${daysInPrev-i}</li>`;
            for (let i = 1; i <= daysInMonth; i++) {
                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(i).padStart(2, '0');
                const isToday = (year === today.getFullYear() && month === today.getMonth() && i === today.getDate());
                const hasEvents = eventsData[dateStr] && eventsData[dateStr].length > 0;
                let cls = isToday ? ' today' : '';
                let dots = '';
                if (hasEvents) eventsData[dateStr].forEach(ev => {
                    dots += `<span class="event-dot ${ev.category.toLowerCase()}"></span>`;
                });
                html += `<li${cls} onclick="openEventModal('${dateStr}')">${i}${dots}</li>`;
            }
            const total = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            for (let i = 1; i <= (total - firstDay - daysInMonth); i++) html += `<li class="other-month">${i}</li>`;
            days.innerHTML = html;
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            loadEventsForMonth(currentYear, currentMonth + 1);
        }

        function openEventModal(dateStr) {
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            document.getElementById('eventDate').value = dateStr;
            document.getElementById('eventDateDisplay').textContent = new Date(dateStr).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            loadEventsForDate(dateStr);
            modal.show();
        }

        function loadEventsForDate(dateStr) {
            const c = document.getElementById('eventsListForDate');
            if (eventsData[dateStr] && eventsData[dateStr].length > 0) {
                c.innerHTML = eventsData[dateStr].map(ev =>
                    `<div class="event-list-item category-${ev.category.toLowerCase()} position-relative">
                <button class="delete-event-btn" onclick="deleteEvent(${ev.id},'${dateStr}')"><i class="fas fa-trash"></i></button>
                <h6>${escHtml(ev.title)}</h6>
                <span class="event-item-category ${ev.category.toLowerCase()}">${ev.category}</span>
                ${ev.description?`<p class="mt-2">${escHtml(ev.description)}</p>`:''}
            </div>`
                ).join('');
            } else {
                c.innerHTML = '<p class="text-muted">No events on this date.</p>';
            }
        }

        function deleteEvent(eventId, dateStr) {
            if (!confirm('Delete this event?')) return;
            ajaxPost({
                action: 'delete_event',
                id: eventId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Event deleted.', 'success');
                    loadEventsForMonth(currentYear, currentMonth + 1);
                    loadUpcomingEvents();
                    loadEventsForDate(dateStr);
                } else showToast('Error: ' + data.message, 'error');
            });
        }

        function insertEventDynamically(event) {
            const c = document.getElementById('eventsListContainer');
            const spinner = c.querySelector('.spinner-border');
            if (spinner) c.innerHTML = '';
            const noMsg = c.querySelector('.text-muted');
            if (noMsg && noMsg.textContent.includes('No upcoming')) c.innerHTML = '';
            c.insertAdjacentHTML('afterbegin', createEventHTML(event));
        }

        // Submit add-event form
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const title = document.getElementById('eventTitle').value;
            const cat = document.getElementById('eventCategory').value;
            const date = document.getElementById('eventDate').value;
            if (!title || !cat || !date) {
                alert('Please fill in all required fields.');
                return;
            }
            const btn = document.getElementById('eventSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            const fd = new FormData(this);
            fd.append('action', 'add_event');
            ajaxFormData(fd).then(data => {
                    if (data.status === 'success') {
                        showToast(`Event "${title}" added!`, 'success');
                        this.reset();
                        document.getElementById('eventDays').value = '1';
                        loadEventsForMonth(currentYear, currentMonth + 1);
                        loadEventsForDate(date);
                        loadUpcomingEvents();
                        bootstrap.Modal.getInstance(document.getElementById('eventModal'))?.hide();
                    } else showToast('Error: ' + data.message, 'error');
                }).catch(() => showToast('An error occurred.', 'error'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Add Event';
                });
        });

        // ============================================================
        //  EDIT EVENT MODAL
        // ============================================================
        function openEditModal(eventId) {
            ajaxPost({
                action: 'get_event',
                event_id: eventId
            }).then(data => {
                if (data.status !== 'success') {
                    showToast('Could not load event.', 'error');
                    return;
                }
                const ev = data.event;
                document.getElementById('editEventId').value = ev.id;
                document.getElementById('editEventDate').value = ev.event_date;
                document.getElementById('editEventTitle').value = ev.title || '';
                document.getElementById('editEventCategory').value = ev.category || 'Academic';
                document.getElementById('editEventLocation').value = ev.location || '';
                document.getElementById('editEventStartTime').value = ev.event_start_time || '';
                document.getElementById('editEventEndTime').value = ev.event_end_time || '';
                document.getElementById('editEventDescription').value = ev.description || '';
                document.getElementById('editOrgName').value = ev.organizer_name || '';
                document.getElementById('editOrgPosition').value = ev.organizer_position || '';
                document.getElementById('editOrgContact').value = ev.organizer_contact || '';

                // Image preview
                const prev = document.getElementById('currentImagePreview');
                prev.innerHTML = ev.image ? `<img src="../../assets/img/events/${escHtml(ev.image)}" style="max-height:100px;border-radius:8px;" alt="Current image"> <small class="text-muted ms-2">${escHtml(ev.image)}</small>` : '<small class="text-muted">No image uploaded.</small>';

                // Highlights
                const hc = document.getElementById('editHighlightsContainer');
                hc.innerHTML = '';
                if (ev.highlights && ev.highlights.length > 0) ev.highlights.forEach(h => addEditHighlightRow(h.highlight));
                else addEditHighlightRow();

                // Schedule
                const sc = document.getElementById('editScheduleContainer');
                sc.innerHTML = '';
                if (ev.schedule && ev.schedule.length > 0) ev.schedule.forEach(s => addEditScheduleRow(s.time_slot, s.activity, s.description));
                else addEditScheduleRow();

                new bootstrap.Modal(document.getElementById('editEventModal')).show();
            });
        }

        document.getElementById('saveEditEventBtn').addEventListener('click', function() {
            const form = document.getElementById('editEventForm');
            const fd = new FormData(form);
            fd.append('action', 'update_event');
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            ajaxFormData(fd).then(data => {
                    if (data.status === 'success') {
                        showToast('Event updated successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editEventModal'))?.hide();
                        loadUpcomingEvents();
                        loadEventsForMonth(currentYear, currentMonth + 1);
                    } else showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                }).catch(() => showToast('An error occurred.', 'error'))
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
                });
        });

        // ============================================================
        //  APPLICATIONS
        // ============================================================
        function loadApplications() {
            const eid = document.getElementById('appEventFilter').value;
            const params = {
                action: 'get_applications'
            };
            if (eid) params.event_id = eid;
            ajaxPost(params).then(data => {
                if (data.status !== 'success') return;
                const tbody = document.getElementById('applicationsBody');
                if (!data.applications.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No applications found.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.applications.map((a, i) => `
            <tr id="app-row-${a.id}">
                <td>${i+1}</td>
                <td>${escHtml(a.student_name)}</td>
                <td><code>${escHtml(a.student_id)}</code></td>
                <td>${escHtml(a.email||'—')}</td>
                <td>${escHtml(a.phone||'—')}</td>
                <td>${escHtml(a.event_title)}</td>
                <td>${new Date(a.applied_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</td>
                <td><span class="status-badge ${a.status}">${a.status}</span></td>
                <td>
                    ${a.status!=='Approved'?`<button class="btn-approve me-1" onclick="updateAppStatus(${a.id},'Approved')"><i class="fas fa-check me-1"></i>Approve</button>`:''}
                    ${a.status!=='Rejected'?`<button class="btn-reject" onclick="updateAppStatus(${a.id},'Rejected')"><i class="fas fa-times me-1"></i>Reject</button>`:''}
                </td>
            </tr>`).join('');
            });
        }

        function updateAppStatus(appId, status) {
            ajaxPost({
                action: 'update_application_status',
                app_id: appId,
                status
            }).then(data => {
                if (data.status === 'success') {
                    showToast(`Application ${status}.`, status === 'Approved' ? 'success' : 'warning');
                    loadApplications();
                } else showToast('Error updating status.', 'error');
            });
        }

        // ============================================================
        //  GROUP MANAGEMENT
        // ============================================================
        function loadGroupsForEvent() {
            const eid = document.getElementById('groupEventSelect').value;
            if (!eid) {
                document.getElementById('groupsContainer').innerHTML = '<p class="text-muted text-center py-3">Select an event to manage its groups.</p>';
                return;
            }
            ajaxPost({
                action: 'get_groups',
                event_id: eid
            }).then(data => {
                if (data.status !== 'success') return;
                renderGroups(data.groups, eid);
            });
        }

        function renderGroups(groups, eventId) {
            const c = document.getElementById('groupsContainer');
            if (!groups.length) {
                c.innerHTML = '<p class="text-muted text-center py-3">No groups yet. Create one above.</p>';
                return;
            }
            c.innerHTML = groups.map(g => `
        <div class="group-card" id="group-card-${g.id}">
            <div class="group-title">
                <span><i class="fas fa-layer-group me-2"></i>${escHtml(g.group_name)}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup(${g.id})"><i class="fas fa-trash me-1"></i>Delete Group</button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <strong class="d-block mb-2"><i class="fas fa-user-graduate me-1"></i>Members</strong>
                    <div id="members-${g.id}">
                        ${g.members.map(m=>`<span class="member-tag">${escHtml(m.student_name)} <small class="text-muted">(${escHtml(m.student_id)})</small><button class="remove-btn" onclick="removeMember(${m.id},${g.id},${eventId})">×</button></span>`).join('')}
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <select class="form-select form-select-sm" id="memberSelect-${g.id}" style="max-width:220px;">
                            <option value="">— Add approved student —</option>
                        </select>
                        <button class="btn btn-sm btn-primary" onclick="addMemberToGroup(${g.id},${eventId})"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <strong class="d-block mb-2"><i class="fas fa-chalkboard-teacher me-1"></i>Teachers / Coaches</strong>
                    <div id="teachers-${g.id}">
                        ${g.teachers.map(t=>`<span class="teacher-tag">${escHtml(t.teacher_name)}<button class="remove-btn" onclick="removeTeacher(${t.id},${g.id},${eventId})">×</button></span>`).join('')}
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <input type="text" class="form-control form-control-sm" id="teacherInput-${g.id}" placeholder="Teacher / Coach name" style="max-width:200px;">
                        <button class="btn btn-sm btn-success" onclick="addTeacher(${g.id},${eventId})"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            </div>
        </div>`).join('');

            // Populate member dropdowns
            ajaxPost({
                action: 'get_approved_applicants',
                event_id: eventId
            }).then(data => {
                if (data.status !== 'success') return;
                groups.forEach(g => {
                    const sel = document.getElementById(`memberSelect-${g.id}`);
                    if (!sel) return;
                    const assignedIds = g.members.map(m => m.student_id);
                    data.applicants.forEach(a => {
                        if (!assignedIds.includes(a.student_id)) {
                            const opt = document.createElement('option');
                            opt.value = a.student_id;
                            opt.dataset.name = a.student_name;
                            opt.textContent = `${a.student_name} (${a.student_id})`;
                            sel.appendChild(opt);
                        }
                    });
                });
            });
        }

        function createGroup() {
            const eid = document.getElementById('groupEventSelect').value;
            const name = document.getElementById('newGroupName').value.trim();
            if (!eid) {
                showToast('Please select an event first.', 'warning');
                return;
            }
            if (!name) {
                showToast('Please enter a group name.', 'warning');
                return;
            }
            ajaxPost({
                action: 'create_group',
                event_id: eid,
                group_name: name
            }).then(data => {
                if (data.status === 'success') {
                    showToast(`Group "${name}" created!`, 'success');
                    document.getElementById('newGroupName').value = '';
                    loadGroupsForEvent();
                } else showToast('Error: ' + data.message, 'error');
            });
        }

        function deleteGroup(groupId) {
            if (!confirm('Delete this group and all its members?')) return;
            ajaxPost({
                action: 'delete_group',
                group_id: groupId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Group deleted.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function addMemberToGroup(groupId, eventId) {
            const sel = document.getElementById(`memberSelect-${groupId}`);
            const sid = sel.value;
            const sname = sel.options[sel.selectedIndex]?.dataset?.name || '';
            if (!sid) {
                showToast('Select a student.', 'warning');
                return;
            }
            ajaxPost({
                action: 'add_member',
                group_id: groupId,
                student_id: sid,
                student_name: sname
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Student added!', 'success');
                    loadGroupsForEvent();
                } else showToast('Error adding student.', 'error');
            });
        }

        function removeMember(memberId, groupId, eventId) {
            if (!confirm('Remove this student from the group?')) return;
            ajaxPost({
                action: 'remove_member',
                member_id: memberId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Member removed.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function addTeacher(groupId, eventId) {
            const inp = document.getElementById(`teacherInput-${groupId}`);
            const name = inp.value.trim();
            if (!name) {
                showToast('Enter a teacher/coach name.', 'warning');
                return;
            }
            ajaxPost({
                action: 'add_teacher',
                group_id: groupId,
                teacher_name: name
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Teacher added!', 'success');
                    inp.value = '';
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function removeTeacher(teacherId, groupId, eventId) {
            if (!confirm('Remove this teacher/coach?')) return;
            ajaxPost({
                action: 'remove_teacher',
                teacher_id: teacherId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Teacher removed.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        // ============================================================
        //  INIT
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            loadNavigation();
            initCalendar();
        });
    </script>
</body>

</html>