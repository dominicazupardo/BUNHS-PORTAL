<?php
include 'db_connection.php';

// Function to get all events
function get_all_events($conn)
{
    $result = $conn->query("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events ORDER BY event_date ASC");
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    return $events;
}

// Function to get upcoming events (from today onwards, including events still ongoing)
function get_upcoming_events($conn, $limit = 10)
{
    $today = date("Y-m-d");
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE event_date >= ? OR DATE_ADD(event_date, INTERVAL (COALESCE(event_days, 1) - 1) DAY) >= ? ORDER BY event_date ASC LIMIT ?");
    $stmt->bind_param("ssi", $today, $today, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// Function to get events for a specific month
function get_events_by_month($conn, $year, $month)
{
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE YEAR(event_date) = ? AND MONTH(event_date) = ? ORDER BY event_date ASC");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// Function to get event counts by category
function get_category_counts($conn)
{
    $categories = ['Academic', 'Sports', 'Cultural', 'Workshops', 'Conferences'];
    $counts = [];
    foreach ($categories as $category) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE category = ?");
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $counts[$category] = $row['count'];
        $stmt->close();
    }
    return $counts;
}

// Function to get featured event
function get_featured_event($conn)
{
    $today = date("Y-m-d");
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE event_date = ? ORDER BY event_date ASC LIMIT 1");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE event_date > ? ORDER BY event_date ASC LIMIT 1");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events ORDER BY event_date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    return null;
}

$upcoming_events = get_upcoming_events($conn, 10);
$category_counts = get_category_counts($conn);
$featured_event = get_featured_event($conn);
$today = date("Y-m-d");
$is_current_event = false;
if ($featured_event && $featured_event['event_date'] == $today) {
    $is_current_event = true;
}

$currentMonth = date('n');
$currentYear = date('Y');
$monthEvents = get_events_by_month($conn, $currentYear, $currentMonth);
$eventsData = [];
foreach ($monthEvents as $event) {
    $dateKey = $event['event_date'];
    if (!isset($eventsData[$dateKey])) {
        $eventsData[$dateKey] = [];
    }
    $eventsData[$dateKey][] = $event;
}
$eventsDataJson = json_encode($eventsData);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Events - Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <link href="assets/css/main.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

    <style>
        /* ── Core Variables ───────────────────────────────────── */
        :root {
            --moss: #4a7c59;
            --moss-dark: #355c42;
            --moss-light: #6a9c79;
            --moss-xlight: #e8f0ea;
            --moss-pale: #f2f7f3;
            --white: #ffffff;
            --ink: #1e2a22;
            --ink-light: #4a5a50;
            --ink-muted: #8a9e90;
            --border: rgba(74, 124, 89, 0.15);
            --shadow-sm: 0 2px 8px rgba(74, 124, 89, 0.08);
            --shadow-md: 0 6px 24px rgba(74, 124, 89, 0.12);
            --shadow-lg: 0 16px 48px rgba(74, 124, 89, 0.16);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
        }

        /* ── Base ─────────────────────────────────────────────── */
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--white);
            color: var(--ink);
        }

        /* ── Section Layout ───────────────────────────────────── */
        #events-2 {
            padding: 60px 0 80px;
            background: var(--white);
        }

        /* ── Section Header ───────────────────────────────────── */
        .events-section-header {
            margin-bottom: 40px;
        }

        .events-section-header .section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--moss);
            background: var(--moss-xlight);
            padding: 6px 14px;
            border-radius: 30px;
            margin-bottom: 12px;
        }

        .events-section-header .section-label::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--moss);
        }

        .events-section-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 2rem;
            color: var(--ink);
            margin: 0;
            line-height: 1.2;
        }

        .events-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-muted);
            margin-top: 8px;
        }

        /* ── Event Cards ──────────────────────────────────────── */
        .events-list {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 18px;
        }

        .events-list .event-item {
            display: flex;
            flex-direction: column;
            gap: 0;
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            flex: 1 1 calc(50% - 9px);
            min-width: 260px;
        }

        .events-list .event-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            height: 3px;
            background: var(--moss);
            border-radius: 4px 4px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .events-list .event-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(74, 124, 89, 0.3);
        }

        .events-list .event-item:hover::before {
            opacity: 1;
        }

        /* Date block */
        .events-list .event-item .event-date {
            flex-shrink: 0;
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: var(--moss-pale);
            border-bottom: 1.5px solid var(--border);
            transition: background 0.3s ease;
        }

        .events-list .event-item:hover .event-date {
            background: var(--moss);
        }

        .events-list .event-item .event-date .day {
            display: block;
            font-family: 'DM Serif Display', serif;
            font-size: 32px;
            font-weight: 400;
            line-height: 1;
            color: var(--moss-dark);
            transition: color 0.3s ease;
        }

        .events-list .event-item:hover .event-date .day {
            color: var(--white);
        }

        .events-list .event-item .event-date .month {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--moss);
            padding: 0;
            background: transparent;
            margin-top: 2px;
            transition: color 0.3s ease;
        }

        .events-list .event-item:hover .event-date .month {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Content block */
        .events-list .event-item .event-content {
            flex: 1;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
        }

        .events-list .event-item .event-content h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.15rem;
            font-weight: 400;
            color: var(--ink);
            margin: 0;
            line-height: 1.3;
        }

        /* Category badge */
        .event-category-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            width: fit-content;
        }

        .event-category-tag.academic {
            background: #dbeafe;
            color: #1e40af;
        }

        .event-category-tag.sports {
            background: #fee2e2;
            color: #991b1b;
        }

        .event-category-tag.cultural {
            background: #ede9fe;
            color: #5b21b6;
        }

        .event-category-tag.workshops {
            background: #fef3c7;
            color: #92400e;
        }

        .event-category-tag.conferences {
            background: var(--moss-xlight);
            color: var(--moss-dark);
        }

        .events-list .event-item .event-meta {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        .events-list .event-item .event-meta p {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--ink-muted);
            margin: 0;
            font-weight: 500;
        }

        .events-list .event-item .event-meta i {
            color: var(--moss);
            font-size: 13px;
        }

        .events-list .event-item .event-content>p {
            font-size: 13.5px;
            color: var(--ink-light);
            margin: 0;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* CTA button */
        .events-list .event-item .btn-event {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--moss-dark);
            font-weight: 700;
            font-size: 12.5px;
            text-decoration: none;
            transition: all 0.25s ease;
            margin-top: 4px;
            width: fit-content;
            padding: 6px 14px;
            border-radius: 30px;
            background: var(--moss-xlight);
            border: 1.5px solid var(--border);
        }

        .events-list .event-item .btn-event:hover {
            background: var(--moss);
            color: var(--white);
            border-color: var(--moss);
            gap: 10px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
            background: var(--moss-pale);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--border);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--moss-light);
            opacity: 0.5;
            margin-bottom: 16px;
        }

        .empty-state p {
            color: var(--ink-muted);
            font-size: 15px;
            margin: 0;
        }

        /* ── Sidebar ──────────────────────────────────────────── */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .sidebar-item {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            overflow: hidden;
        }

        .sidebar-item-header {
            padding: 16px 20px;
            border-bottom: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--moss-pale);
        }

        .sidebar-item-header .header-icon {
            width: 32px;
            height: 32px;
            background: var(--moss);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sidebar-item-header h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 1rem;
            color: var(--ink);
            margin: 0;
            font-weight: 400;
        }

        /* ── Calendar ─────────────────────────────────────────── */
        .calendar-container {
            max-width: 100%;
        }

        .calendar-wrapper {
            border-radius: 0;
            overflow: hidden;
        }

        .month {
            padding: 18px 20px;
            width: 100%;
            background: var(--moss);
            text-align: center;
        }

        .month ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .month ul li {
            color: white;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .month .prev,
        .month .next {
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 18px;
            background: rgba(255, 255, 255, 0.1);
        }

        .month .prev:hover,
        .month .next:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
        }

        .weekdays {
            margin: 0;
            padding: 10px 8px 6px;
            background: var(--moss-pale);
            display: flex;
            border-bottom: 1.5px solid var(--border);
        }

        .weekdays li {
            display: inline-block;
            width: 14.28%;
            color: var(--moss-dark);
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .days {
            padding: 8px;
            background: var(--white);
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            list-style: none;
        }

        .days li {
            list-style-type: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14.28%;
            text-align: center;
            margin-bottom: 2px;
            font-size: 13px;
            color: var(--ink-light);
            padding: 10px 0;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: var(--radius-sm);
            min-height: 42px;
            font-weight: 500;
        }

        .days li:hover {
            background: var(--moss);
            color: white;
        }

        .days li.other-month {
            color: #cdd8cf;
            font-weight: 400;
        }

        .days li.other-month:hover {
            background: var(--moss-xlight);
            color: var(--ink-muted);
        }

        .days li.today {
            font-weight: 800;
            color: var(--white);
            background: var(--moss-dark);
            border-radius: var(--radius-sm);
        }

        .days li.today::after {
            display: none;
        }

        .days li .event-dot {
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--moss);
        }

        .days li.today .event-dot {
            background: rgba(255, 255, 255, 0.7);
        }

        .days li .event-dot.academic {
            background: #3b82f6;
        }

        .days li .event-dot.sports {
            background: #ef4444;
        }

        .days li .event-dot.cultural {
            background: #8b5cf6;
        }

        .days li .event-dot.workshops {
            background: #f59e0b;
        }

        .days li .event-dot.conferences {
            background: var(--moss);
        }

        /* ── Featured Event ───────────────────────────────────── */
        .featured-event-content {
            padding: 20px;
        }

        .featured-event-content h4 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.05rem;
            color: var(--ink);
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .featured-event-content p {
            font-size: 13px;
            color: var(--ink-muted);
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .featured-event-content p i {
            color: var(--moss);
        }

        .featured-event-content .featured-desc {
            font-size: 13px;
            color: var(--ink-light);
            line-height: 1.6;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1.5px solid var(--border);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Event Categories ─────────────────────────────────── */
        .categories ul {
            list-style: none;
            padding: 16px 20px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .categories ul li a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--ink-light);
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
        }

        .categories ul li a:hover {
            background: var(--moss-pale);
            color: var(--moss-dark);
            border-color: var(--border);
            padding-left: 18px;
        }

        .categories ul li a::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .categories ul li:nth-child(1) a::before {
            background: #3b82f6;
        }

        .categories ul li:nth-child(2) a::before {
            background: #ef4444;
        }

        .categories ul li:nth-child(3) a::before {
            background: #8b5cf6;
        }

        .categories ul li:nth-child(4) a::before {
            background: #f59e0b;
        }

        .categories ul li:nth-child(5) a::before {
            background: var(--moss);
        }

        .categories ul li a span {
            font-size: 11px;
            font-weight: 700;
            color: var(--white);
            background: var(--moss);
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: auto;
        }

        /* ── Modal ────────────────────────────────────────────── */
        .event-modal .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .event-modal .event-date-header {
            background: var(--moss);
            color: white;
            padding: 22px 28px;
            display: flex;
            align-items: flex-start;
            flex-direction: column;
            gap: 4px;
        }

        .event-modal .event-date-header h5 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.2rem;
            font-weight: 400;
            margin: 0;
        }

        .event-modal #eventDateDisplay {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
        }

        .events-list-modal {
            max-height: 340px;
            overflow-y: auto;
            padding: 4px 2px;
        }

        .event-list-item {
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            background: var(--moss-pale);
            border-left: 4px solid var(--moss);
            transition: all 0.25s ease;
        }

        .event-list-item:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .event-list-item h6 {
            margin: 0 0 8px 0;
            color: var(--ink);
            font-weight: 700;
            font-size: 14px;
        }

        .event-list-item p {
            margin: 0;
            font-size: 12.5px;
            color: var(--ink-muted);
        }

        .event-list-item p i {
            color: var(--moss);
            margin-right: 4px;
        }

        .modal-footer .btn-secondary {
            background: var(--moss-pale);
            border: 1.5px solid var(--border);
            color: var(--moss-dark);
            font-weight: 600;
            border-radius: 30px;
            padding: 8px 22px;
            font-size: 13px;
        }

        .modal-footer .btn-secondary:hover {
            background: var(--moss);
            color: white;
            border-color: var(--moss);
        }

        /* ── Category Tags (modal) ────────────────────────────── */
        .event-item-category {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .event-item-category.academic {
            background: #dbeafe;
            color: #1e40af;
        }

        .event-item-category.sports {
            background: #fee2e2;
            color: #991b1b;
        }

        .event-item-category.cultural {
            background: #ede9fe;
            color: #5b21b6;
        }

        .event-item-category.workshops {
            background: #fef3c7;
            color: #92400e;
        }

        .event-item-category.conferences {
            background: var(--moss-xlight);
            color: var(--moss-dark);
        }

        /* ── Pagination ───────────────────────────────────────── */
        .pagination-wrapper {
            margin-top: 32px;
        }

        .pagination .page-link {
            border: 1.5px solid var(--border);
            color: var(--moss-dark);
            border-radius: var(--radius-sm) !important;
            margin: 0 3px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .pagination .page-item.active .page-link {
            background: var(--moss);
            border-color: var(--moss);
            color: white;
        }

        .pagination .page-link:hover {
            background: var(--moss-pale);
        }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 992px) {
            .events-list .event-item {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 768px) {
            .events-list {
                flex-direction: column;
            }

            .events-list .event-item {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body class="events-page">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
            <a href="index.html" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>
            <div id="nav-placeholder"></div>
    </header>

    <main class="main">
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">Events</h1>
                            <p class="mb-0">"Stay updated with the latest events and activities at Buyoan National High School — where students, teachers, and the community come together to celebrate learning, achievement, and school spirit."</p>
                        </div>
                    </div>
                    <nav class="breadcrumbs">
                        <div class="container">
                            <ol>
                                <li><a href="index.html">Home</a></li>
                                <li class="current">Events</li>
                            </ol>
                        </div>
                    </nav>
                </div>

                <section id="events-2" class="events-2 section">
                    <div class="container">
                        <div class="row g-4">

                            <!-- ── Left: Events List ───────────────────── -->
                            <div class="col-lg-8">
                                <div class="events-section-header">
                                    <div class="section-label">Upcoming Events</div>
                                    <h2>What's Happening</h2>
                                    <div class="events-count-badge" id="events-count-badge" style="display:none;"><i class="bi bi-calendar2-check" style="color:var(--moss)"></i> <span id="events-count-text"></span></div>
                                </div>

                                <div class="events-list" id="events-list-container">
                                    <!-- Events are loaded dynamically via JS -->
                                    <div class="empty-state" id="events-loading-state">
                                        <i class="bi bi-hourglass-split d-block"></i>
                                        <p>Loading events...</p>
                                    </div>
                                </div>

                                <?php if (count($upcoming_events) > 10): ?>
                                    <div class="pagination-wrapper">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1"><i class="bi bi-chevron-left"></i></a></li>
                                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                                            <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ── Right: Sidebar ─────────────────────── -->
                            <div class="col-lg-4">
                                <div class="sidebar">

                                    <!-- Calendar -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="bi bi-calendar3"></i></div>
                                            <h3>Event Calendar</h3>
                                        </div>
                                        <div class="calendar-container">
                                            <div class="calendar-wrapper">
                                                <div class="month" id="calendarMonth">
                                                    <ul>
                                                        <li class="prev" onclick="changeMonth(-1)">&#10094;</li>
                                                        <li id="monthYearDisplay"></li>
                                                        <li class="next" onclick="changeMonth(1)">&#10095;</li>
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

                                    <!-- Featured Event -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="bi bi-star"></i></div>
                                            <h3>Featured Event</h3>
                                        </div>
                                        <div class="featured-event-content">
                                            <?php if ($featured_event): ?>
                                                <?php $featuredDate = new DateTime($featured_event['event_date']); ?>
                                                <?php $featuredCat = strtolower($featured_event['category']); ?>
                                                <span class="event-category-tag <?php echo $featuredCat; ?>" style="margin-bottom:12px"><?php echo htmlspecialchars($featured_event['category']); ?></span>
                                                <h4><?php echo htmlspecialchars($featured_event['title']); ?></h4>
                                                <p><i class="bi bi-calendar-event"></i> <?php echo $featuredDate->format('F j, Y'); ?></p>
                                                <?php if ($featured_event['description']): ?><p class="featured-desc"><?php echo htmlspecialchars($featured_event['description']); ?></p><?php endif; ?>
                                            <?php else: ?>
                                                <h4>No Featured Event</h4>
                                                <p>Check back later for upcoming events.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Event Categories -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                                            <h3>Event Categories</h3>
                                        </div>
                                        <div class="categories">
                                            <ul>
                                                <li><a href="#">Academic <span><?php echo $category_counts['Academic']; ?></span></a></li>
                                                <li><a href="#">Sports <span><?php echo $category_counts['Sports']; ?></span></a></li>
                                                <li><a href="#">Cultural <span><?php echo $category_counts['Cultural']; ?></span></a></li>
                                                <li><a href="#">Workshops <span><?php echo $category_counts['Workshops']; ?></span></a></li>
                                                <li><a href="#">Conferences <span><?php echo $category_counts['Conferences']; ?></span></a></li>
                                            </ul>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </section>
    </main>

    <!-- Footer Placeholder -->
    <div id="footer-placeholder"></div>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));
    </script>

    <!-- Include Footer -->
    <script>
        fetch('footer.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading footer:', error));
    </script>

    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(response => response.text())
            .then(data => {
                document.body.insertAdjacentHTML('beforeend', data);
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');
                    if (loginBtn) {
                        loginBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('loginModal')).show();
                        });
                    }
                    if (signupBtn) {
                        signupBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('signupModal')).show();
                        });
                    }
                });
            })
            .catch(error => console.error('Error loading modals:', error));
    </script>

    <!-- Dynamic Events Loader -->
    <script>
        const ANNOUNCEMENTS_URL = 'admin_account/announcements/create_announcement.php';
        const monthNamesShort = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const monthNamesFull = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        function formatTime12h(time) {
            if (!time) return '';
            const parts = time.split(':');
            const h = parseInt(parts[0]);
            const m = parts[1];
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = (h % 12) || 12;
            return h12 + ':' + m + ' ' + ampm;
        }

        function buildEventDateRange(event) {
            const startDate = new Date(event.event_date + 'T00:00:00');
            const days = event.event_days ? parseInt(event.event_days) : 1;
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + days - 1);

            const sM = startDate.getMonth();
            const sY = startDate.getFullYear();
            const eM = endDate.getMonth();
            const eY = endDate.getFullYear();

            if (sM === eM && sY === eY) {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + '–' + endDate.getDate() + ', ' + sY;
            } else if (sY === eY) {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + ' – ' + monthNamesFull[eM] + ' ' + endDate.getDate() + ', ' + sY;
            } else {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + ', ' + sY + ' – ' + monthNamesFull[eM] + ' ' + endDate.getDate() + ', ' + eY;
            }
        }

        function renderEventCard(event) {
            const startDate = new Date(event.event_date + 'T00:00:00');
            const day = String(startDate.getDate()).padStart(2, '0');
            const mon = monthNamesShort[startDate.getMonth()];
            const catClass = event.category ? event.category.toLowerCase() : '';
            const dateRange = buildEventDateRange(event);
            const days = event.event_days ? parseInt(event.event_days) : 1;
            const startTimeStr = formatTime12h(event.event_start_time);
            const endTimeStr = formatTime12h(event.event_end_time);
            let timeLine = '';
            if (startTimeStr && endTimeStr) timeLine = startTimeStr + ' – ' + endTimeStr;
            else if (startTimeStr) timeLine = startTimeStr;
            else if (endTimeStr) timeLine = endTimeStr;
            const buttonText = event.team_based == 1 ? 'Join Now' : 'Learn More';
            const desc = event.description ? `<p>${escapeHtml(event.description)}</p>` : '';
            const timeHtml = timeLine ? `<p><i class="bi bi-clock"></i> ${timeLine}</p>` : '';
            const daysHtml = days > 1 ? `<p><i class="bi bi-layers"></i> ${days} days</p>` : '';

            return `
            <div class="event-item">
                <div class="event-date">
                    <span class="day">${day}</span>
                    <span class="month">${mon}</span>
                </div>
                <div class="event-content">
                    <span class="event-category-tag ${catClass}">${escapeHtml(event.category)}</span>
                    <h3>${escapeHtml(event.title)}</h3>
                    <div class="event-meta">
                        ${timeHtml}
                        <p><i class="bi bi-calendar-event"></i> ${dateRange}</p>
                        ${daysHtml}
                    </div>
                    ${desc}
                    <a href="#" class="btn-event">${buttonText} <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>`;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function loadUpcomingEvents() {
            const container = document.getElementById('events-list-container');
            const countBadge = document.getElementById('events-count-badge');
            const countText = document.getElementById('events-count-text');

            fetch(ANNOUNCEMENTS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'get_upcoming_events',
                        limit: 10
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.events && data.events.length > 0) {
                        container.innerHTML = data.events.map(renderEventCard).join('');
                        const n = data.events.length;
                        countText.textContent = n + ' event' + (n > 1 ? 's' : '') + ' scheduled';
                        countBadge.style.display = 'inline-flex';
                    } else {
                        container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-calendar-x d-block"></i>
                        <p>No upcoming events scheduled. Check back soon!</p>
                    </div>`;
                        countBadge.style.display = 'none';
                    }
                })
                .catch(() => {
                    container.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-exclamation-circle d-block"></i>
                    <p>Could not load events. Please refresh the page.</p>
                </div>`;
                });
        }

        document.addEventListener('DOMContentLoaded', loadUpcomingEvents);
    </script>

    <!-- Event Modal -->
    <div class="modal fade event-modal" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="event-date-header">
                    <h5 class="modal-title" id="eventModalLabel">Events on this date</h5>
                    <div class="event-date-display" id="eventDateDisplay"></div>
                    <div class="modal-body">
                        <div class="events-list-modal" id="eventsListForDate"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>

                    <!-- Calendar JavaScript -->
                    <script>
                        let currentMonth = new Date().getMonth();
                        let currentYear = new Date().getFullYear();
                        let eventsData = <?php echo $eventsDataJson; ?>;
                        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

                        function renderCalendar(year, month) {
                            const monthYearDisplay = document.getElementById('monthYearDisplay');
                            const calendarDays = document.getElementById('calendarDays');
                            monthYearDisplay.innerHTML = monthNames[month] + '<br><span style="font-size:18px">' + year + '</span>';
                            const firstDay = new Date(year, month, 1).getDay();
                            const daysInMonth = new Date(year, month + 1, 0).getDate();
                            const daysInPrevMonth = new Date(year, month, 0).getDate();
                            const today = new Date();
                            let html = '';
                            for (let i = firstDay - 1; i >= 0; i--) {
                                html += '<li class="other-month">' + (daysInPrevMonth - i) + '</li>';
                            }
                            for (let i = 1; i <= daysInMonth; i++) {
                                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(i).padStart(2, '0');
                                const isToday = (year === today.getFullYear() && month === today.getMonth() && i === today.getDate());
                                const hasEvents = eventsData[dateStr] && eventsData[dateStr].length > 0;
                                let classes = isToday ? ' today' : '';
                                let eventDots = '';
                                if (hasEvents) {
                                    eventsData[dateStr].forEach(event => {
                                        eventDots += '<span class="event-dot ' + event.category.toLowerCase() + '"></span>';
                                    });
                                }
                                html += '<li' + classes + ' onclick="openEventModal(\'' + dateStr + '\')">' + i + eventDots + '</li>';
                            }
                            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
                            const remainingDays = totalCells - (firstDay + daysInMonth);
                            for (let i = 1; i <= remainingDays; i++) {
                                html += '<li class="other-month">' + i + '</li>';
                            }
                            calendarDays.innerHTML = html;
                        }

                        function loadEventsForMonth(year, month) {
                            fetch('admin_account/announcements/create_announcement.php', {
                                    method: 'POST',
                                    body: new URLSearchParams({
                                        'action': 'get_events',
                                        'year': year,
                                        'month': month
                                    }),
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        eventsData = {};
                                        data.events.forEach(event => {
                                            const dateKey = event.event_date;
                                            if (!eventsData[dateKey]) eventsData[dateKey] = [];
                                            eventsData[dateKey].push(event);
                                        });
                                        renderCalendar(currentYear, currentMonth);
                                    }
                                })
                                .catch(error => console.error('Error loading events:', error));
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
                            const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
                            const eventDateDisplay = document.getElementById('eventDateDisplay');
                            const date = new Date(dateStr);
                            const formattedDate = date.toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            eventDateDisplay.textContent = formattedDate;
                            loadEventsForDateModal(dateStr);
                            eventModal.show();
                        }

                        function loadEventsForDateModal(dateStr) {
                            const eventsListContainer = document.getElementById('eventsListForDate');
                            if (eventsData[dateStr] && eventsData[dateStr].length > 0) {
                                let html = '';
                                eventsData[dateStr].forEach(event => {
                                    const categoryClass = event.category.toLowerCase();
                                    let eventTime = '';
                                    if (event.event_start_time || event.event_end_time) {
                                        const formatTime = (time) => {
                                            if (!time) return '';
                                            const [hours, minutes] = time.split(':');
                                            const h = parseInt(hours);
                                            return (h % 12 || 12) + ':' + minutes + ' ' + (h >= 12 ? 'PM' : 'AM');
                                        };
                                        const startTime = formatTime(event.event_start_time);
                                        const endTime = formatTime(event.event_end_time);
                                        eventTime = (startTime && endTime) ? startTime + ' - ' + endTime : (startTime || endTime);
                                    }
                                    html += '<div class="event-list-item category-' + categoryClass + '"><h6>' + event.title + '</h6><span class="event-item-category ' + categoryClass + '">' + event.category + '</span>' + (eventTime ? '<p class="mt-2"><i class="bi bi-clock"></i> ' + eventTime + '</p>' : '') + (event.description ? '<p class="mt-2">' + event.description + '</p>' : '') + '</div>';
                                });
                                eventsListContainer.innerHTML = html;
                            } else {
                                eventsListContainer.innerHTML = '<p class="text-muted">No events on this date.</p>';
                            }
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            renderCalendar(currentYear, currentMonth);
                        });
                    </script>
</body>

</html>