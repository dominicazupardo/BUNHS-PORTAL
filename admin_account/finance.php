<?php
// Start session for CSRF token
session_start();
include '../db_connection.php';
assert($conn instanceof mysqli);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pagination and filtering variables
$records_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(fund_title LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $param_types .= "ss";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = &$category_filter;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';

        // Delete finance record
        if ($action == 'delete') {
            $id = intval($_POST['id']);

            // Get the proof image first to delete file
            $stmt = $conn->prepare("SELECT proof_image FROM finance_records WHERE id = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
                exit;
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $record = $result->fetch_assoc();
            $stmt->close();

            if (!$record) {
                echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
                exit;
            }

            // Delete from database
            $stmt = $conn->prepare("DELETE FROM finance_records WHERE id = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
                exit;
            }
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();

            // Delete proof image file if exists
            if ($success && $record && $record['proof_image']) {
                $file_path = "admin_assets/finance_proofs/" . $record['proof_image'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Record deleted successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error deleting record.']);
            }
            exit;
        }

        // Edit finance record
        if ($action == 'edit') {
            $id = intval($_POST['id']);
            $fund_title       = trim($_POST['fund_title']       ?? '');
            $description      = trim($_POST['description']      ?? '');
            $transaction_date = $_POST['transaction_date']       ?? '';
            $category         = trim($_POST['category']         ?? '');
            $dv_check_no      = trim($_POST['dv_check_no']      ?? '');
            $cash_advance     = floatval($_POST['cash_advance']  ?? 0);
            $payments         = floatval($_POST['payments']      ?? 0);
            $tax_withheld     = floatval($_POST['tax_withheld']  ?? 0);
            $balance          = floatval($_POST['balance']       ?? 0);
            $mooe_col         = trim($_POST['mooe_col']          ?? '');
            $electricity      = floatval($_POST['electricity']   ?? 0);
            $semi_expendable  = floatval($_POST['semi_expendable'] ?? 0);
            $other_general    = floatval($_POST['other_general'] ?? 0);
            $training         = floatval($_POST['training']      ?? 0);
            $water            = floatval($_POST['water']         ?? 0);
            $other_supplies   = floatval($_POST['other_supplies'] ?? 0);
            $internet         = floatval($_POST['internet']      ?? 0);
            $due_to_bir           = floatval($_POST['due_to_bir']          ?? 0);
            $amount_other         = floatval($_POST['amount_other']        ?? 0);
            $account_description  = trim($_POST['account_description']     ?? '');
            $uacs_code            = trim($_POST['uacs_code']               ?? '');
            $amount = $payments; // amount stored in DB = payments value

            if (empty($transaction_date)) $transaction_date = date('Y-m-d');

            // Handle image upload (optional - only if new image provided)
            $proof_image = null;
            $update_image = false;

            if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
                $target_dir = "admin_assets/finance_proofs/";

                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES["proof_image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $max_size = 5 * 1024 * 1024;

                if (!in_array($file_extension, $allowed_types)) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.']);
                    exit;
                }

                if ($_FILES['proof_image']['size'] > $max_size) {
                    echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB limit.']);
                    exit;
                }

                if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
                    $proof_image = $new_filename;
                    $update_image = true;

                    // Delete old image
                    $stmt = $conn->prepare("SELECT proof_image FROM finance_records WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $old_record = $result->fetch_assoc();
                        $stmt->close();

                        if ($old_record && $old_record['proof_image']) {
                            $old_file = "admin_assets/finance_proofs/" . $old_record['proof_image'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                }
            }

            // Merge CDR extra fields into description as JSON meta
            $cdr_meta = json_encode([
                'dv_check_no'    => $dv_check_no,
                'cash_advance'   => $cash_advance,
                'payments'       => $payments,
                'tax_withheld'   => $tax_withheld,
                'balance'        => $balance,
                'mooe_col'       => $mooe_col,
                'electricity'    => $electricity,
                'semi_expendable' => $semi_expendable,
                'other_general'  => $other_general,
                'training'       => $training,
                'water'          => $water,
                'other_supplies' => $other_supplies,
                'internet'       => $internet,
                'due_to_bir'     => $due_to_bir,
                'amount_other'   => $amount_other,
                'account_description' => $account_description,
                'uacs_code'      => $uacs_code,
                'note'           => $description,
            ]);

            // Update database
            if ($update_image) {
                $stmt = $conn->prepare("UPDATE finance_records SET fund_title = ?, description = ?, amount = ?, transaction_date = ?, category = ?, proof_image = ?, updated_at = NOW() WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE finance_records SET fund_title = ?, description = ?, amount = ?, transaction_date = ?, category = ?, updated_at = NOW() WHERE id = ?");
            }

            if (!$stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error.']);
                exit;
            }

            if ($update_image) {
                $stmt->bind_param("ssdsssi", $fund_title, $cdr_meta, $amount, $transaction_date, $category, $proof_image, $id);
            } else {
                $stmt->bind_param("ssdssi", $fund_title, $cdr_meta, $amount, $transaction_date, $category, $id);
            }

            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Finance record updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error updating finance record.']);
            }
            exit;
        }

        // Add new finance record
        $fund_title       = trim($_POST['fund_title']        ?? '');
        $description      = trim($_POST['description']       ?? '');
        $transaction_date = $_POST['transaction_date']        ?? '';
        $category         = trim($_POST['category']          ?? '');
        $dv_check_no      = trim($_POST['dv_check_no']       ?? '');
        $cash_advance     = floatval($_POST['cash_advance']   ?? 0);
        $payments         = floatval($_POST['payments']       ?? 0);
        $tax_withheld     = floatval($_POST['tax_withheld']   ?? 0);
        $balance          = floatval($_POST['balance']        ?? 0);
        $mooe_col         = trim($_POST['mooe_col']           ?? '');
        $electricity      = floatval($_POST['electricity']    ?? 0);
        $semi_expendable  = floatval($_POST['semi_expendable'] ?? 0);
        $other_general    = floatval($_POST['other_general']  ?? 0);
        $training         = floatval($_POST['training']       ?? 0);
        $water            = floatval($_POST['water']          ?? 0);
        $other_supplies   = floatval($_POST['other_supplies'] ?? 0);
        $internet         = floatval($_POST['internet']       ?? 0);
        $due_to_bir           = floatval($_POST['due_to_bir']          ?? 0);
        $amount_other         = floatval($_POST['amount_other']        ?? 0);
        $account_description  = trim($_POST['account_description']     ?? '');
        $uacs_code            = trim($_POST['uacs_code']               ?? '');
        $amount = $payments; // amount stored in DB = payments value

        if (empty($transaction_date)) $transaction_date = date('Y-m-d');

        // Handle image upload
        $proof_image = '';
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
            $target_dir = "admin_assets/finance_proofs/";

            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["proof_image"]["name"], PATHINFO_EXTENSION));
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;

            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5 * 1024 * 1024;

            if (!in_array($file_extension, $allowed_types)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.']);
                exit;
            }

            if ($_FILES['proof_image']['size'] > $max_size) {
                echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB limit.']);
                exit;
            }

            if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_file)) {
                $proof_image = $new_filename;
            }
        }

        // Encode CDR extra fields into description
        $cdr_meta = json_encode([
            'dv_check_no'    => $dv_check_no,
            'cash_advance'   => $cash_advance,
            'payments'       => $payments,
            'tax_withheld'   => $tax_withheld,
            'balance'        => $balance,
            'mooe_col'       => $mooe_col,
            'electricity'    => $electricity,
            'semi_expendable' => $semi_expendable,
            'other_general'  => $other_general,
            'training'       => $training,
            'water'          => $water,
            'other_supplies' => $other_supplies,
            'internet'       => $internet,
            'due_to_bir'     => $due_to_bir,
            'amount_other'   => $amount_other,
            'account_description' => $account_description,
            'uacs_code'      => $uacs_code,
            'note'           => $description,
        ]);

        $stmt = $conn->prepare("INSERT INTO finance_records (fund_title, description, amount, transaction_date, category, proof_image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Database error.']);
            exit;
        }
        $stmt->bind_param("ssdsss", $fund_title, $cdr_meta, $amount, $transaction_date, $category, $proof_image);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Finance record added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error adding finance record.']);
        }
    }

    // Handle GET requests for export
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['export']) && $_GET['export'] == 'csv') {
        $export_query = "SELECT * FROM finance_records ORDER BY transaction_date DESC, created_at DESC";
        $export_result = $conn->query($export_query);

        if ($export_result instanceof mysqli_result && $export_result->num_rows > 0) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="finance_records_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            fputcsv($output, ['ID', 'Fund Title', 'Description', 'Amount', 'Category', 'Transaction Date', 'Created At']);

            while ($row = $export_result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['fund_title'],
                    $row['description'],
                    $row['amount'],
                    $row['category'],
                    $row['transaction_date'],
                    $row['created_at']
                ]);
            }

            fclose($output);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No records to export.']);
        }
        exit;
    }
    exit;
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM finance_records $where_clause";
$total_records = 0;

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt) {
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        if ($count_result) {
            $total_records = (int)($count_result->fetch_assoc()['total'] ?? 0);
        }
        $count_stmt->close();
    }
} else {
    $count_result = $conn->query($count_query);
    if ($count_result instanceof mysqli_result) {
        $total_records = (int)($count_result->fetch_assoc()['total'] ?? 0);
    }
}

$total_pages = ceil($total_records / $records_per_page);

// Get finance records with pagination
$finance_records = [];
$query = "SELECT * FROM finance_records $where_clause ORDER BY transaction_date DESC, created_at DESC LIMIT ? OFFSET ?";
$params[] = &$records_per_page;
$params[] = &$offset;
$param_types .= "ii";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $finance_records[] = $row;
        }
    }
    $stmt->close();
}

// Get all records for statistics
$all_records = [];
$all_result = $conn->query("SELECT * FROM finance_records ORDER BY transaction_date DESC, created_at DESC");
if ($all_result instanceof mysqli_result && $all_result->num_rows > 0) {
    while ($row = $all_result->fetch_assoc()) {
        $all_records[] = $row;
    }
}

// Calculate comprehensive statistics
$total_amount = 0;
$current_month_total = 0;
$previous_month_total = 0;
$current_month = date('Y-m');
$previous_month = date('Y-m', strtotime('-1 month'));
$category_totals = [];
$highest_expense = ['amount' => 0, 'title' => ''];

foreach ($all_records as $record) {
    $amount = $record['amount'];
    $total_amount += $amount;

    if (!empty($record['category'])) {
        if (!isset($category_totals[$record['category']])) {
            $category_totals[$record['category']] = 0;
        }
        $category_totals[$record['category']] += $amount;
    }

    if (substr($record['transaction_date'], 0, 7) === $current_month) {
        $current_month_total += $amount;
    }

    if (substr($record['transaction_date'], 0, 7) === $previous_month) {
        $previous_month_total += $amount;
    }

    if ($amount > $highest_expense['amount']) {
        $highest_expense = ['amount' => $amount, 'title' => $record['fund_title']];
    }
}

$average_transaction = count($all_records) > 0 ? $total_amount / count($all_records) : 0;
$month_over_month_change = $previous_month_total > 0 ? (($current_month_total - $previous_month_total) / $previous_month_total) * 100 : 0;

// ── Fetch Principal info from admin table ──────────────────────────
$principal_name  = 'JOJO D. APULI';
$principal_title = 'School Principal I';
$admin_row = $conn->query("SELECT full_name, title FROM admin ORDER BY id ASC LIMIT 1");
if ($admin_row && $admin_row->num_rows > 0) {
    $ar = $admin_row->fetch_assoc();
    if (!empty($ar['full_name'])) $principal_name  = strtoupper($ar['full_name']);
    if (!empty($ar['title']))     $principal_title = $ar['title'];
}

// ── Fetch Senior Bookkeeper from sub_admin table ───────────────────
$bookkeeper_name  = 'SHANE V. BOLAÑOS';
$bookkeeper_title = 'Senior Bookkeeper-Designate';
$bk_row = $conn->query(
    "SELECT CONCAT(first_name,' ',last_name) AS full_name, role FROM sub_admin
     WHERE FIND_IN_SET('senior_bookkeeper', role) > 0 AND status='approved'
     ORDER BY id ASC LIMIT 1"
);
if ($bk_row && $bk_row->num_rows > 0) {
    $bk = $bk_row->fetch_assoc();
    if (!empty($bk['full_name'])) $bookkeeper_name  = strtoupper(trim($bk['full_name']));
    $bookkeeper_title = 'Senior Bookkeeper-Designate';
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

$category_icons = [
    'Supplies' => 'fa-box',
    'Maintenance' => 'fa-wrench',
    'Equipment' => 'fa-laptop',
    'Sports' => 'fa-basketball-ball',
    'Books' => 'fa-book',
    'Transportation' => 'fa-bus',
    'Utilities' => 'fa-lightbulb',
    'Events' => 'fa-party-horn',
    'Salaries' => 'fa-money-bill-wave',
    'Other' => 'fa-clipboard-list'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Management - School Admin Dashboard</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap');

        :root {
            --primary-color: #5c7a3e;
            --primary-light: #7a9e56;
            --primary-dim: rgba(92, 122, 62, 0.08);
            --secondary-color: #1e6b53;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #2563eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --border-light: #f3f4f6;
            --light-color: #f8fafc;
            --bg-page: #f1f5f0;
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 4px rgba(0, 0, 0, 0.07), 0 4px 12px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 1px 4px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.14), 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 24px 64px rgba(0, 0, 0, 0.18);
            --radius-sm: 6px;
            --radius: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            --gradient-success: linear-gradient(135deg, #16a34a, #15803d);
            --gradient-danger: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
        }

        /* ── PAGE HEADER ── */
        .page-title {
            margin-bottom: 0;
            padding: 28px 36px 20px;
            background: linear-gradient(160deg, #ecf2e8 0%, #e8f0f5 100%);
            border-bottom: 1px solid rgba(92, 122, 62, 0.15);
            width: 100%;
        }

        .page-title .heading {
            padding: 0;
            width: 100%;
        }

        .page-title .heading-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.4px;
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .breadcrumbs {
            padding: 12px 36px !important;
            background: rgba(255, 255, 255, 0.7) !important;
            border-bottom: 1px solid var(--border-color);
            width: 100%;
            font-size: 13px;
        }

        /* ── MAIN SECTION ── */
        .finance-section {
            padding: 24px 36px 36px;
            width: 100%;
        }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow);
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius-lg);
            opacity: 0;
            transition: opacity 0.22s ease;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card.green::before {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .stat-card.blue::before {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }

        .stat-card.orange::before {
            background: linear-gradient(90deg, #d97706, #fbbf24);
        }

        .stat-card.purple::before {
            background: linear-gradient(90deg, #7c3aed, #a78bfa);
        }

        .stat-card.red::before {
            background: linear-gradient(90deg, #dc2626, #f87171);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .stat-icon.orange {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-icon i {
            font-size: 18px;
        }

        .stat-content {
            flex: 1;
            min-width: 0;
        }

        .stat-label {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
            letter-spacing: -0.5px;
            font-feature-settings: "tnum";
        }

        .stat-change {
            font-size: 11.5px;
            margin-top: 5px;
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        .stat-change:not(.positive):not(.negative) {
            color: var(--text-muted);
        }

        /* ── CARD ── */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            background: #fff;
            overflow: hidden;
        }

        .card-header {
            border-bottom: 1px solid var(--border-light);
            background: #fff;
            padding: 16px 22px;
        }

        .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.2px;
        }

        .card-body {
            padding: 20px 22px;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .search-box input {
            width: 100%;
            padding: 9px 14px 9px 38px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-primary);
            background: var(--light-color);
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(92, 122, 62, 0.1);
            outline: none;
            background: #fff;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-select {
            padding: 9px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            background: var(--light-color);
            min-width: 170px;
            cursor: pointer;
            color: var(--text-primary);
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(92, 122, 62, 0.1);
            outline: none;
            background-color: #fff;
        }

        .btn-export {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
            color: #fff;
            padding: 9px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 7px;
            letter-spacing: 0.1px;
        }

        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        }

        /* ── TABLE ── */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            background: var(--light-color);
            border-bottom: 1px solid var(--border-color);
            padding: 11px 16px;
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-light);
            font-size: 13.5px;
            color: var(--text-primary);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr {
            transition: background 0.15s ease;
        }

        .table tbody tr:hover td {
            background: #f7faf5;
        }

        .table tfoot th {
            font-weight: 700;
            padding: 12px 16px;
            background: var(--light-color);
            border-top: 1px solid var(--border-color);
            font-size: 13.5px;
        }

        /* ── CATEGORY BADGES ── */
        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.1px;
        }

        .category-badge i {
            font-size: 10px;
        }

        .category-badge.supplies {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .category-badge.maintenance {
            background: #fef3c7;
            color: #b45309;
        }

        .category-badge.equipment {
            background: #ede9fe;
            color: #6d28d9;
        }

        .category-badge.sports {
            background: #dcfce7;
            color: #15803d;
        }

        .category-badge.books {
            background: #fce7f3;
            color: #be185d;
        }

        .category-badge.transportation {
            background: #cffafe;
            color: #0e7490;
        }

        .category-badge.utilities {
            background: #ffedd5;
            color: #c2410c;
        }

        .category-badge.events {
            background: #f3e8ff;
            color: #7e22ce;
        }

        .category-badge.salaries {
            background: #e0e7ff;
            color: #3730a3;
        }

        .category-badge.other {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* ── BUTTONS ── */
        .btn {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            border-radius: var(--radius);
            padding: 8px 16px;
            font-size: 13px;
            transition: all 0.18s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.1px;
            border: none;
        }

        .btn-sm {
            padding: 6px 11px;
            font-size: 12px;
            border-radius: var(--radius-sm);
        }

        .btn-outline-primary {
            border: 1.5px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary-dim);
        }

        .btn-outline-danger {
            border: 1.5px solid #fca5a5;
            color: var(--danger-color);
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: #fee2e2;
            border-color: var(--danger-color);
        }

        .btn-outline-secondary {
            border: 1.5px solid var(--border-color);
            color: var(--text-secondary);
            background: #fff;
        }

        .btn-outline-secondary:hover {
            background: var(--light-color);
            color: var(--text-primary);
        }

        .btn-success {
            background: var(--gradient-success);
            color: #fff;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
        }

        .btn-success:hover {
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(92, 122, 62, 0.25);
        }

        .btn-primary:hover {
            box-shadow: 0 4px 14px rgba(92, 122, 62, 0.35);
            transform: translateY(-1px);
        }

        .btn-info {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-info:hover {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--gradient-danger);
            color: #fff;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.2);
        }

        .btn-danger:hover {
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 12px;
        }

        /* ── AMOUNT ── */
        .amount-positive {
            color: var(--success-color);
            font-weight: 700;
            font-feature-settings: "tnum";
            font-family: 'DM Mono', monospace;
            font-size: 13px;
        }

        /* ── PAGINATION ── */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 22px;
            background: var(--light-color);
            border-top: 1px solid var(--border-light);
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-muted);
        }

        .pagination {
            display: flex;
            gap: 3px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .pagination li a,
        .pagination li span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            background: #fff;
            border: 1.5px solid var(--border-color);
            text-decoration: none;
            transition: all 0.18s ease;
        }

        .pagination li a:hover {
            background: var(--primary-dim);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .pagination li.active span {
            background: var(--gradient-primary);
            border-color: transparent;
            color: #fff;
            font-weight: 700;
        }

        .pagination li.disabled span {
            opacity: 0.38;
            cursor: not-allowed;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 72px 20px;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: var(--light-color);
            border: 2px dashed var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .empty-state-icon i {
            font-size: 32px;
            color: var(--text-muted);
        }

        .empty-state h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 13.5px;
            max-width: 320px;
            margin: 0 auto;
        }

        /* ── POPUP OVERLAY ── */
        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 14, 23, 0.55);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
            padding: 20px;
            overflow-y: auto;
        }

        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .popup-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(15, 14, 23, .22), 0 0 0 1px rgba(15, 14, 23, .06);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            transform: scale(0.94) translateY(16px);
            transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin: auto;
            display: flex;
            flex-direction: column;
        }

        .popup-overlay.active .popup-card {
            transform: scale(1) translateY(0);
        }

        /* ── Hero header (students.php style) ── */
        .popup-card .card-header {
            background: linear-gradient(135deg, #0d2b1e 0%, #1a4733 50%, #2a6347 100%);
            padding: 24px 28px 20px;
            border-bottom: none;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .popup-card .card-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 80% 50%, rgba(180, 215, 100, .18) 0%, transparent 70%);
            pointer-events: none;
        }

        .popup-card .card-header::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 1px solid rgba(139, 124, 248, .15);
            pointer-events: none;
        }

        .popup-header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            position: relative;
            z-index: 5;
        }

        .popup-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(60, 120, 90, .25);
            border: 1px solid rgba(120, 200, 150, .35);
            border-radius: 20px;
            padding: 3px 11px;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #a8e6c0;
            margin-bottom: 8px;
        }

        .popup-card .card-header h5 {
            font-family: 'Sora', 'DM Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 4px;
            letter-spacing: -.03em;
            line-height: 1.1;
            position: relative;
            z-index: 5;
        }

        .popup-header-sub {
            font-size: .78rem;
            color: rgba(255, 255, 255, .5);
            font-weight: 400;
            position: relative;
            z-index: 5;
        }

        .popup-close-btn {
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 10px;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .7);
            cursor: pointer;
            font-size: .9rem;
            transition: background .15s, color .15s;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        .popup-close-btn:hover {
            background: rgba(255, 255, 255, .2);
            color: #fff;
        }

        .popup-card .card-body {
            padding: 24px 28px;
            max-height: calc(85vh - 160px);
            overflow-y: auto;
        }

        .popup-card .card-footer {
            padding: 16px 28px;
            background: #f8f7f4;
            border-top: 1px solid #e8e6e1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ── FORM ELEMENTS (students.php style) ── */
        .form-label {
            font-size: .7rem;
            font-weight: 700;
            color: #8b89a0;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-control,
        .form-select {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            color: #0f0e17;
            background: #f4faf7;
            border: 1.5px solid #d5ebe0;
            border-radius: 12px;
            padding: 11px 14px;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            width: 100%;
        }

        .form-control::placeholder {
            color: #c5c3d6;
            font-weight: 400;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3c785a;
            box-shadow: 0 0 0 4px rgba(60, 120, 90, .12);
            background: #fff;
            outline: none;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%233c785a' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px 8px;
            padding-right: 36px;
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 70px;
            line-height: 1.5;
        }

        .invalid-feedback {
            font-size: 12px;
            color: var(--danger-color);
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }

        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }

        .form-control.is-invalid+.invalid-feedback {
            display: block;
        }

        .input-group-wrapper {
            position: relative;
        }

        .input-group-wrapper .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
            z-index: 10;
            pointer-events: none;
        }

        .input-group-wrapper .form-control {
            padding-left: 38px;
        }

        .input-group-wrapper.trailing-icon .form-control {
            padding-left: 13px;
            padding-right: 38px;
        }

        .input-group-wrapper.trailing-icon .input-icon {
            left: auto;
            right: 13px;
        }

        .char-counter {
            position: absolute;
            right: 8px;
            bottom: -20px;
            font-size: 11px;
            color: var(--text-muted);
        }

        /* ── UPLOAD ZONE ── */
        .upload-zone {
            border: 2px dashed #d4d0ee;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            background: #faf8ff;
            position: relative;
        }

        .upload-zone:hover {
            border-color: #3c785a;
            background: #edf8f3;
        }

        .upload-zone.dragover {
            border-color: #3c785a;
            background: rgba(60, 120, 90, .08);
        }

        .upload-zone.has-file {
            border-style: solid;
            border-color: #3c785a;
        }

        .upload-zone .upload-icon {
            font-size: 2rem;
            color: #c4c2ce;
            margin-bottom: 10px;
            display: block;
        }

        .upload-zone .upload-text {
            font-size: .84rem;
            font-weight: 700;
            color: #3d3b52;
            margin-bottom: 4px;
        }

        .upload-zone .upload-hint {
            font-size: .74rem;
            color: #a8a6bc;
            margin-top: 4px;
        }

        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .image-preview {
            display: none;
            margin-top: 14px;
            position: relative;
        }

        .image-preview.show {
            display: block;
        }

        .image-preview img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }

        .image-preview .remove-image {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(220, 38, 38, 0.9);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .form-section-title {
            font-size: .67rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: #2d5c43;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 0 10px;
            border-bottom: 2px solid #c5e8d5;
            width: 100%;
            margin-bottom: 4px;
        }

        .form-section-title i {
            width: 22px;
            height: 22px;
            background: #d4f0e2;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #2d5c43;
        }

        .form-group-animated {
            animation: fadeInUp 0.25s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── POPUP FOOTER BUTTONS (students.php style) ── */
        .popup-card .card-footer .btn {
            border-radius: 12px;
            padding: 11px 22px;
            font-size: .84rem;
            font-weight: 700;
            letter-spacing: 0;
            transition: opacity .15s, box-shadow .15s, background .15s;
        }

        .popup-card .card-footer .btn-outline-secondary {
            background: #f4f3f0;
            border: none;
            color: #0f0e17;
            box-shadow: none;
        }

        .popup-card .card-footer .btn-outline-secondary:hover {
            background: #e8e6e0;
            transform: none;
        }

        .popup-card .card-footer .btn-success {
            background: linear-gradient(135deg, #059669, #10b981);
            box-shadow: 0 4px 16px rgba(5, 150, 105, .3);
        }

        .popup-card .card-footer .btn-success:hover {
            opacity: .9;
            box-shadow: 0 6px 20px rgba(5, 150, 105, .4);
            transform: none;
        }

        .popup-card .card-footer .btn-info {
            background: linear-gradient(135deg, #2d5c43, #3c785a);
            box-shadow: 0 4px 16px rgba(44, 92, 67, .35);
        }

        .popup-card .card-footer .btn-info:hover {
            opacity: .9;
            box-shadow: 0 6px 20px rgba(44, 92, 67, .45);
            transform: none;
        }

        .popup-card .card-footer .btn-danger {
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            box-shadow: 0 4px 16px rgba(185, 28, 28, .3);
        }

        .popup-card .card-footer .btn-danger:hover {
            opacity: .9;
            box-shadow: 0 6px 20px rgba(185, 28, 28, .4);
            transform: none;
        }

        /* ── DELETE POPUP ── */
        .popup-card.delete-popup .card-header {
            background: linear-gradient(135deg, #2b0d0d 0%, #471a1a 50%, #632a2a 100%);
        }

        .popup-card.delete-popup .card-header::before {
            background: radial-gradient(ellipse 80% 60% at 80% 50%, rgba(215, 100, 100, .18) 0%, transparent 70%);
        }

        .popup-card.delete-popup .popup-header-badge {
            background: rgba(120, 60, 60, .25);
            border-color: rgba(200, 120, 120, .35);
            color: #f5b8b8;
        }

        .popup-card.delete-popup .card-body {
            text-align: center;
            padding: 32px 28px;
        }

        .popup-card.delete-popup .card-footer {
            justify-content: center;
        }

        .delete-icon-circle {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-icon-circle i {
            font-size: 28px;
            color: var(--danger-color);
        }

        .delete-popup-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        .delete-popup-message {
            color: var(--text-secondary);
            font-size: 13.5px;
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto;
        }

        /* ── MODAL ── */
        .modal-header {
            background: linear-gradient(135deg, #f0f5eb, #ebf5f0);
            border-bottom: 1px solid rgba(92, 122, 62, 0.12);
            padding: 18px 24px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.2px;
        }

        .modal-title i {
            color: var(--primary-color);
        }

        .modal-body {
            padding: 22px 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--border-light);
            padding: 14px 24px;
            background: var(--light-color);
        }

        .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
        }

        /* ── SPINNER ── */
        .spinner-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease;
        }

        .spinner-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .spinner-border {
            width: 2.8rem;
            height: 2.8rem;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.85s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spinner-text {
            margin-top: 12px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        /* ── TOAST ── */
        .toast-container {
            z-index: 10000;
        }

        .custom-toast {
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            font-family: 'DM Sans', sans-serif;
            min-width: 280px;
        }

        .toast.success {
            background: var(--gradient-success);
            color: #fff;
            border: none;
        }

        .toast.error {
            background: var(--gradient-danger);
            color: #fff;
            border: none;
        }

        .toast .toast-header {
            background: transparent;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }

        .toast .btn-close {
            filter: invert(1);
        }

        .toast .btn-close {
            filter: invert(1);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .finance-section {
                padding: 16px;
            }

            .page-title {
                padding: 18px 16px 14px;
            }

            .breadcrumbs {
                padding: 12px 16px !important;
            }

            .page-title .heading-title {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-bar {
                flex-direction: column;
            }

            .search-box,
            .filter-select,
            .btn-export {
                width: 100%;
            }

            .btn-export {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .popup-card {
                width: 95%;
            }

            .popup-card .card-header {
                padding: 16px;
            }

            .popup-card .card-body {
                padding: 16px;
            }

            .popup-card .card-footer {
                padding: 12px 16px;
                flex-direction: column;
            }

            .popup-card .card-footer .btn {
                width: 100%;
                justify-content: center;
            }

            .pagination-wrapper {
                flex-direction: column;
                text-align: center;
            }
        }

        /* ── SCROLLBARS ── */
        .popup-card .card-body::-webkit-scrollbar {
            width: 5px;
        }

        .popup-card .card-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .popup-card .card-body::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        .popup-card .card-body::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        /* ── AMOUNT CLICKABLE ── */
        .amount-clickable {
            cursor: pointer;
            transition: all 0.18s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: var(--radius-sm);
        }

        .amount-clickable:hover {
            background: rgba(22, 163, 74, 0.08);
        }

        .amount-clickable .proof-indicator {
            color: var(--primary-color);
            font-size: 11px;
            opacity: 0.7;
        }

        /* ── ROW INDEX ── */
        .row-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: var(--border-light);
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            font-feature-settings: "tnum";
        }

        /* ── RECORD TITLE ── */
        .record-title {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .record-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            line-height: 1.4;
        }

        /* ── CASH DISBURSEMENT REGISTER TABLE ── */
        .cdr-wrapper {
            width: 100%;
            overflow-x: auto;
            background: #fff;
        }

        .cdr-doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 18px 24px 12px;
            border-bottom: 1px solid #ccc;
            font-size: 11.5px;
            color: #000;
            line-height: 1.7;
        }

        .cdr-doc-header .left-info div,
        .cdr-doc-header .right-info div {
            white-space: nowrap;
        }

        .cdr-doc-header .right-info {
            text-align: left;
        }

        .cdr-table {
            width: 100%;
            min-width: 1700px;
            border-collapse: collapse;
            font-size: 11px;
            color: #000;
            font-family: Arial, sans-serif;
        }

        .cdr-table th,
        .cdr-table td {
            border: 1px solid #888;
            padding: 3px 5px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.3;
        }

        .cdr-table thead th {
            background: #f5f5f5;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
        }

        .cdr-table thead tr.cdr-group-row th {
            background: #ececec;
            font-size: 10.5px;
        }

        .cdr-table tbody td {
            text-align: left;
            font-size: 11px;
        }

        .cdr-table tbody td.cdr-num,
        .cdr-table tfoot td.cdr-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .cdr-table tbody td.cdr-center,
        .cdr-table tfoot td.cdr-center {
            text-align: center;
        }

        .cdr-table tfoot tr td {
            font-weight: 700;
            background: #f0f0f0;
            font-size: 11px;
        }

        .cdr-table tbody tr:hover td {
            background: #fafdf7;
        }

        .cdr-footer {
            display: flex;
            justify-content: space-between;
            padding: 28px 40px 20px;
            font-size: 11.5px;
            color: #000;
        }

        .cdr-footer .sig-block {
            text-align: center;
            min-width: 220px;
        }

        .cdr-footer .sig-label {
            font-size: 10px;
            color: #555;
            margin-bottom: 28px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .cdr-footer .sig-name {
            font-weight: 700;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .cdr-footer .sig-title {
            font-size: 10.5px;
            color: #333;
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <div class="spinner-overlay" id="loadingOverlay">
        <div class="spinner-content">
            <div class="spinner-border"></div>
            <div class="spinner-text">Processing...</div>
        </div>
    </div>

    <main class="main page-content" style="margin-left: 0; width: 100%; max-width: 100%;">
        <div class="page-title">
            <div class="heading">
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="width:42px;height:42px;background:var(--gradient-primary);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(92,122,62,0.3);">
                        <i class="fas fa-wallet" style="color:#fff;font-size:17px;"></i>
                    </div>
                    <div>
                        <h1 class="heading-title" style="margin:0;">Finance Management</h1>
                        <p class="mb-0" style="font-size:13px;color:var(--text-secondary);">Track and manage school funds and expenses</p>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <ol style="max-width: 100%; padding: 0; margin: 0; display:flex; gap:6px; align-items:center; list-style:none;">
                    <li><a href="admin_dashboard.php" style="color:var(--text-secondary);text-decoration:none;font-size:13px;">Home</a></li>
                    <li style="color:var(--text-muted);font-size:12px;"><i class="fas fa-chevron-right"></i></li>
                    <li class="current" aria-current="page" style="color:var(--primary-color);font-weight:600;font-size:13px;">Finance</li>
                </ol>
            </nav>
        </div>

        <section class="finance-section" style="width: 100%; max-width: 100%;">
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-wallet"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Expenses</div>
                        <div class="stat-value">₱<?php echo number_format($total_amount, 2); ?></div>
                        <?php if ($total_records > 0): ?>
                            <div class="stat-change"><?php echo $total_records; ?> transactions</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">This Month</div>
                        <div class="stat-value">₱<?php echo number_format($current_month_total, 2); ?></div>
                        <?php if ($month_over_month_change != 0): ?>
                            <div class="stat-change <?php echo $month_over_month_change >= 0 ? 'positive' : 'negative'; ?>">
                                <i class="fas fa<?php echo $month_over_month_change >= 0 ? '-arrow-up' : '-arrow-down'; ?>"></i>
                                <?php echo number_format(abs($month_over_month_change), 1); ?>% vs last month
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon orange"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Average Transaction</div>
                        <div class="stat-value">₱<?php echo number_format($average_transaction, 2); ?></div>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="fas fa-trophy"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Highest Expense</div>
                        <div class="stat-value" style="font-size: 18px;">₱<?php echo number_format($highest_expense['amount'], 2); ?></div>
                        <div class="stat-change" style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($highest_expense['title']); ?></div>
                    </div>
                </div>
                <div class="stat-card red">
                    <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value"><?php echo count($all_records); ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h5 class="mb-0"><i class="fas fa-table-list me-2" style="color:var(--primary-color);"></i>Finance Records</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-success" onclick="openAddModal()" aria-label="Add new finance record">
                            <i class="fas fa-plus"></i>Add New Record
                        </button>
                        <button class="btn btn-outline-secondary" onclick="refreshTable()" aria-label="Refresh table">
                            <i class="fas fa-rotate-right"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="card-body pb-0">
                    <form method="GET" action="" class="filter-bar" id="filterForm">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" id="searchInput" placeholder="Search by fund title or description..." value="<?php echo htmlspecialchars($search); ?>" aria-label="Search finance records">
                        </div>
                        <select name="category" class="filter-select" id="categoryFilter" aria-label="Filter by category">
                            <option value="">All Categories</option>
                            <option value="Supplies" <?php echo $category_filter == 'Supplies' ? 'selected' : ''; ?>>Supplies</option>
                            <option value="Maintenance" <?php echo $category_filter == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="Equipment" <?php echo $category_filter == 'Equipment' ? 'selected' : ''; ?>>Equipment</option>
                            <option value="Sports" <?php echo $category_filter == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="Books" <?php echo $category_filter == 'Books' ? 'selected' : ''; ?>>Books</option>
                            <option value="Transportation" <?php echo $category_filter == 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                            <option value="Utilities" <?php echo $category_filter == 'Utilities' ? 'selected' : ''; ?>>Utilities</option>
                            <option value="Events" <?php echo $category_filter == 'Events' ? 'selected' : ''; ?>>Events</option>
                            <option value="Salaries" <?php echo $category_filter == 'Salaries' ? 'selected' : ''; ?>>Salaries</option>
                            <option value="Other" <?php echo $category_filter == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                        <?php if (!empty($search) || !empty($category_filter)): ?>
                            <a href="finance.php" class="btn btn-outline-secondary"><i class="fas fa-times me-1"></i> Clear</a>
                        <?php endif; ?>
                        <button type="button" class="btn-export" onclick="exportToCSV()" aria-label="Export to CSV">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </form>
                </div>

                <div class="card-body p-0">
                    <?php
                    $col_map = [
                        'Utilities'      => 'electricity',
                        'Equipment'      => 'semi_expendable',
                        'Other'          => 'other_general',
                        'Salaries'       => 'other_general',
                        'Events'         => 'training',
                        'Sports'         => 'training',
                        'Transportation' => 'training',
                        'Maintenance'    => 'water',
                        'Supplies'       => 'other_supplies',
                        'Books'          => 'other_supplies',
                    ];
                    $cash_advance_total  = 0;
                    $page_total          = 0;
                    $tax_withheld_total  = 0;
                    $col_totals = [
                        'electricity'     => 0,
                        'semi_expendable' => 0,
                        'other_general'   => 0,
                        'training'        => 0,
                        'water'           => 0,
                        'other_supplies'  => 0,
                        'internet'        => 0,
                        'due_to_bir'      => 0,
                        'amount_other'    => 0,
                    ];
                    foreach ($finance_records as $r) {
                        $amt_r = floatval($r['amount']);
                        $page_total += $amt_r;
                        $raw_r = $r['description'] ?? '';
                        $cdr_r = [];
                        if ($raw_r && $raw_r[0] === '{') {
                            $dec_r = json_decode($raw_r, true);
                            if (is_array($dec_r)) $cdr_r = $dec_r;
                        }
                        $cash_advance_total += floatval($cdr_r['cash_advance'] ?? 0);
                        $tax_withheld_total += floatval($cdr_r['tax_withheld'] ?? 0);

                        // Sum each MOOE column from stored explicit values first,
                        // then fall back to category mapping for the payments column
                        $r_elec  = floatval($cdr_r['electricity']     ?? 0);
                        $r_semi  = floatval($cdr_r['semi_expendable']  ?? 0);
                        $r_ogen  = floatval($cdr_r['other_general']    ?? 0);
                        $r_train = floatval($cdr_r['training']         ?? 0);
                        $r_water = floatval($cdr_r['water']            ?? 0);
                        $r_osup  = floatval($cdr_r['other_supplies']   ?? 0);
                        $r_inet  = floatval($cdr_r['internet']         ?? 0);
                        $r_bir   = floatval($cdr_r['due_to_bir']       ?? 0);
                        $r_other = floatval($cdr_r['amount_other']     ?? 0);

                        // If none of the explicit MOOE fields are set, fall back to category mapping
                        $any_explicit = ($r_elec + $r_semi + $r_ogen + $r_train + $r_water + $r_osup + $r_inet + $r_bir + $r_other) > 0;
                        if (!$any_explicit) {
                            $mooe_override_r = !empty($cdr_r['mooe_col']) ? $cdr_r['mooe_col'] : null;
                            $col_r = $mooe_override_r ?? ($col_map[$r['category']] ?? 'other_general');
                            if (isset($col_totals[$col_r])) $col_totals[$col_r] += $amt_r;
                        } else {
                            $col_totals['electricity']     += $r_elec;
                            $col_totals['semi_expendable'] += $r_semi;
                            $col_totals['other_general']   += $r_ogen;
                            $col_totals['training']        += $r_train;
                            $col_totals['water']           += $r_water;
                            $col_totals['other_supplies']  += $r_osup;
                            $col_totals['internet']        += $r_inet;
                            $col_totals['due_to_bir']      += $r_bir;
                            $col_totals['amount_other']    += $r_other;
                        }
                    }
                    ?>
                    <div class="cdr-wrapper">
                        <div class="cdr-doc-header">
                            <div class="left-info">
                                <div><strong>Entity Name:</strong> BUYOAN NATIONAL HIGH SCHOOL (JUNIOR HIGH SCHOOL)</div>
                                <div><strong>Sub-Office/District/Division:</strong> DepEd Division of Legazpi City</div>
                                <div><strong>Municipality/City/Province:</strong> Legazpi City</div>
                                <div><strong>Fund Cluster:</strong> 101101</div>
                            </div>
                            <div class="right-info">
                                <div><strong>Name of Accountable Officer:</strong> <?php echo htmlspecialchars($principal_name); ?></div>
                                <div><strong>Official Designation:</strong> <?php echo htmlspecialchars($principal_title); ?></div>
                                <div><strong>Station:</strong> BUYOAN NATIONAL HIGH SCHOOL</div>
                                <div><strong>Register No.:</strong> &nbsp;</div>
                                <div><strong>Sheet No.:</strong> &nbsp;</div>
                            </div>
                        </div>
                        <table class="cdr-table" role="table" aria-label="Cash Disbursement Register">
                            <thead>
                                <tr class="cdr-group-row">
                                    <th rowspan="3" style="width:66px;">Date</th>
                                    <th rowspan="3" style="width:76px;">DV/Payroll<br>Check No.</th>
                                    <th rowspan="3" style="min-width:160px;">Particulars/Supplier</th>
                                    <th colspan="4">Advances for<br>Operating Expenses<br>(19901010)</th>
                                    <th colspan="11">BREAKDOWN OF WITHDRAWALS/PAYMENTS</th>
                                    <th rowspan="3" style="width:76px;">Actions</th>
                                </tr>
                                <tr class="cdr-group-row">
                                    <th colspan="4">Amount</th>
                                    <th colspan="11">MAINTENANCE AND OTHER OPERATING EXPENSES</th>
                                </tr>
                                <tr>
                                    <th style="width:76px;">Cash Advance</th>
                                    <th style="width:76px;">Payments</th>
                                    <th style="width:68px;">Tax Withheld</th>
                                    <th style="width:76px;">Balance</th>
                                    <th style="width:66px;">Electricity Expenses<br><small style="font-weight:400;">(5020401000)</small></th>
                                    <th style="width:72px;">Semi-Expendable Information and Communications Technology Equipment<br><small style="font-weight:400;">(5020321210)</small></th>
                                    <th style="width:68px;">Other General Services<br><small style="font-weight:400;">(5021299000)</small></th>
                                    <th style="width:64px;">Training Expenses<br><small style="font-weight:400;">(5020201000)</small></th>
                                    <th style="width:64px;">Water Expenses<br><small style="font-weight:400;">(5020402000)</small></th>
                                    <th style="width:72px;">Other Supplies &amp; Materials Expenses<br><small style="font-weight:400;">(5020399000)</small></th>
                                    <th style="width:66px;">Internet Subscription Expenses<br><small style="font-weight:400;">(5020503000)</small></th>
                                    <th style="width:62px;">Due to BIR<br><small style="font-weight:400;">(2020101000)</small></th>
                                    <th style="width:100px;">Account Description</th>
                                    <th style="width:72px;">UACS Code</th>
                                    <th style="width:66px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $running_balance = $cash_advance_total;
                                if (count($finance_records) === 0):
                                ?>
                                    <tr>
                                        <td colspan="19" style="text-align:center;color:#999;font-size:11px;padding:12px;">No records found.</td>
                                    </tr>
                                <?php
                                endif;
                                foreach ($finance_records as $record):
                                    $amt = floatval($record['amount']);
                                    // Decode CDR meta from description
                                    $cdr = [];
                                    $raw = $record['description'] ?? '';
                                    if ($raw && $raw[0] === '{') {
                                        $decoded = json_decode($raw, true);
                                        if (is_array($decoded)) $cdr = $decoded;
                                    }
                                    $dv_no           = !empty($cdr['dv_check_no'])     ? $cdr['dv_check_no']             : 'DV-' . str_pad($record['id'], 4, '0', STR_PAD_LEFT);
                                    $rec_advance     = isset($cdr['cash_advance'])      ? floatval($cdr['cash_advance'])   : 0;
                                    $rec_payments    = isset($cdr['payments'])          ? floatval($cdr['payments'])       : $amt;
                                    $rec_tax         = isset($cdr['tax_withheld'])      ? floatval($cdr['tax_withheld'])   : 0;
                                    $rec_balance     = isset($cdr['balance'])           ? floatval($cdr['balance'])        : 0;
                                    $mooe_override   = !empty($cdr['mooe_col'])         ? $cdr['mooe_col']                : null;
                                    // Particulars fix: show only the note/description text, NOT JSON
                                    $note            = !empty($cdr['note'])             ? $cdr['note']                    : '';
                                    // Per-row MOOE overrides from individual columns if set
                                    $row_electricity   = isset($cdr['electricity'])    ? floatval($cdr['electricity'])    : 0;
                                    $row_semi          = isset($cdr['semi_expendable']) ? floatval($cdr['semi_expendable']) : 0;
                                    $row_other_gen     = isset($cdr['other_general'])  ? floatval($cdr['other_general'])  : 0;
                                    $row_training      = isset($cdr['training'])       ? floatval($cdr['training'])       : 0;
                                    $row_water         = isset($cdr['water'])          ? floatval($cdr['water'])          : 0;
                                    $row_other_sup     = isset($cdr['other_supplies']) ? floatval($cdr['other_supplies']) : 0;
                                    $row_internet      = isset($cdr['internet'])       ? floatval($cdr['internet'])       : 0;
                                    $row_bir           = isset($cdr['due_to_bir'])     ? floatval($cdr['due_to_bir'])     : 0;
                                    $row_amount_other  = isset($cdr['amount_other'])   ? floatval($cdr['amount_other'])   : 0;
                                    $row_account_desc  = isset($cdr['account_description']) ? $cdr['account_description'] : '';
                                    $row_uacs_code     = isset($cdr['uacs_code'])      ? $cdr['uacs_code']               : '';
                                    $running_balance -= $amt;
                                    // MOOE column: if individual overrides set use those, else derive from category
                                    $col = $mooe_override ?? ($col_map[$record['category']] ?? 'other_general');
                                    // Use explicit per-row values if any were set, else use category mapping
                                    $display_elec  = $row_electricity  > 0 ? $row_electricity  : ($col === 'electricity'    ? $amt : 0);
                                    $display_semi  = $row_semi         > 0 ? $row_semi         : ($col === 'semi_expendable' ? $amt : 0);
                                    $display_ogen  = $row_other_gen    > 0 ? $row_other_gen    : ($col === 'other_general'  ? $amt : 0);
                                    $display_train = $row_training     > 0 ? $row_training     : ($col === 'training'       ? $amt : 0);
                                    $display_water = $row_water        > 0 ? $row_water        : ($col === 'water'          ? $amt : 0);
                                    $display_osup  = $row_other_sup    > 0 ? $row_other_sup    : ($col === 'other_supplies' ? $amt : 0);
                                    $display_inet  = $row_internet     > 0 ? $row_internet     : ($col === 'internet'       ? $amt : 0);
                                    $display_bir   = $row_bir          > 0 ? $row_bir          : ($col === 'due_to_bir'     ? $amt : 0);
                                    $display_other = $row_amount_other > 0 ? $row_amount_other : 0;
                                ?>
                                    <tr data-id="<?php echo $record['id']; ?>">
                                        <td class="cdr-center"><?php echo date('m/d/Y', strtotime($record['transaction_date'])); ?></td>
                                        <td class="cdr-center" style="font-size:10px;"><?php echo htmlspecialchars($dv_no); ?></td>
                                        <td style="padding-left:6px;">
                                            <!-- FIX: Show fund_title (Particulars/Supplier) only — NOT JSON -->
                                            <strong style="font-size:11px;"><?php echo htmlspecialchars($record['fund_title']); ?></strong>
                                            <?php if ($note): ?>
                                                <div style="font-size:10px;color:#555;"><?php echo htmlspecialchars(substr($note, 0, 45)); ?><?php echo strlen($note) > 45 ? '…' : ''; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cdr-num"><?php echo $rec_advance > 0 ? '₱' . number_format($rec_advance, 2) : '—'; ?></td>
                                        <td class="cdr-num">
                                            <?php if ($record['proof_image']): ?>
                                                <span style="cursor:pointer;color:#1a4f12;" onclick="viewProof('<?php echo htmlspecialchars($record['proof_image']); ?>')" title="View proof">
                                                    ₱<?php echo number_format($rec_payments, 2); ?> <i class="fas fa-eye" style="font-size:9px;"></i>
                                                </span>
                                            <?php else: ?>
                                                ₱<?php echo number_format($rec_payments, 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cdr-num"><?php echo $rec_tax > 0 ? '₱' . number_format($rec_tax, 2) : '—'; ?></td>
                                        <td class="cdr-num">₱<?php echo number_format(max(0, $running_balance), 2); ?></td>
                                        <td class="cdr-num"><?php echo $display_elec  > 0 ? '₱' . number_format($display_elec,  2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_semi  > 0 ? '₱' . number_format($display_semi,  2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_ogen  > 0 ? '₱' . number_format($display_ogen,  2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_train > 0 ? '₱' . number_format($display_train, 2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_water > 0 ? '₱' . number_format($display_water, 2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_osup  > 0 ? '₱' . number_format($display_osup,  2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_inet  > 0 ? '₱' . number_format($display_inet,  2) : ''; ?></td>
                                        <td class="cdr-num"><?php echo $display_bir   > 0 ? '₱' . number_format($display_bir,   2) : ''; ?></td>
                                        <td style="padding-left:5px;font-size:10.5px;"><?php echo htmlspecialchars($row_account_desc); ?></td>
                                        <td class="cdr-center" style="font-size:10.5px;"><?php echo htmlspecialchars($row_uacs_code); ?></td>
                                        <td class="cdr-num"><?php echo $display_other > 0 ? '₱' . number_format($display_other, 2) : ''; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-info edit-btn"
                                                    data-id="<?php echo $record['id']; ?>"
                                                    data-fund_title="<?php echo htmlspecialchars($record['fund_title']); ?>"
                                                    data-note="<?php echo htmlspecialchars($note); ?>"
                                                    data-dv_check_no="<?php echo htmlspecialchars($dv_no); ?>"
                                                    data-cash_advance="<?php echo $rec_advance; ?>"
                                                    data-payments="<?php echo $rec_payments; ?>"
                                                    data-tax_withheld="<?php echo $rec_tax; ?>"
                                                    data-balance="<?php echo $rec_balance; ?>"
                                                    data-mooe_col="<?php echo htmlspecialchars($mooe_override ?? ''); ?>"
                                                    data-electricity="<?php echo $row_electricity; ?>"
                                                    data-semi_expendable="<?php echo $row_semi; ?>"
                                                    data-other_general="<?php echo $row_other_gen; ?>"
                                                    data-training="<?php echo $row_training; ?>"
                                                    data-water="<?php echo $row_water; ?>"
                                                    data-other_supplies="<?php echo $row_other_sup; ?>"
                                                    data-internet="<?php echo $row_internet; ?>"
                                                    data-due_to_bir="<?php echo $row_bir; ?>"
                                                    data-amount_other="<?php echo $display_other; ?>"
                                                    data-account_description="<?php echo htmlspecialchars($row_account_desc); ?>"
                                                    data-uacs_code="<?php echo htmlspecialchars($row_uacs_code); ?>"
                                                    data-amount="<?php echo $record['amount']; ?>"
                                                    data-transaction_date="<?php echo $record['transaction_date']; ?>"
                                                    data-category="<?php echo htmlspecialchars($record['category'] ?? ''); ?>"
                                                    data-proof_image="<?php echo htmlspecialchars($record['proof_image'] ?? ''); ?>"
                                                    onclick="openEditModal(this)" aria-label="Edit record">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $record['id']; ?>" aria-label="Delete record">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align:center;font-weight:700;letter-spacing:.5px;font-size:11px;">TOTALS</td>
                                    <td class="cdr-num">₱<?php echo number_format($cash_advance_total, 2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($page_total, 2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($tax_withheld_total, 2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format(max(0, $cash_advance_total - $page_total), 2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['electricity'],     2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['semi_expendable'], 2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['other_general'],   2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['training'],        2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['water'],           2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['other_supplies'],  2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['internet'],        2); ?></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['due_to_bir'],      2); ?></td>
                                    <td></td>
                                    <td></td>
                                    <td class="cdr-num">₱<?php echo number_format($col_totals['amount_other'],    2); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="cdr-footer">
                            <div class="sig-block">
                                <div class="sig-label">Prepared by:</div>
                                <div class="sig-name"><?php echo htmlspecialchars($bookkeeper_name); ?></div>
                                <div class="sig-title">Signature Over Printed Name</div>
                                <div class="sig-title"><?php echo htmlspecialchars($bookkeeper_title); ?></div>
                            </div>
                            <div class="sig-block">
                                <div class="sig-label">Certified Correct:</div>
                                <div class="sig-name"><?php echo htmlspecialchars($principal_name); ?></div>
                                <div class="sig-title">Signature Over Printed Name</div>
                                <div class="sig-title"><?php echo htmlspecialchars($principal_title); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination-info">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" aria-label="First page"><i class="fas fa-angle-double-left"></i></a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous page"><i class="fas fa-angle-left"></i></a></li>
                                    <?php else: ?>
                                        <li class="disabled"><span><i class="fas fa-angle-double-left"></i></span></li>
                                        <li class="disabled"><span><i class="fas fa-angle-left"></i></span></li>
                                    <?php endif; ?>
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                            <span><?php echo $i; ?></span>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next page"><i class="fas fa-angle-right"></i></a></li>
                                        <li><a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" aria-label="Last page"><i class="fas fa-angle-double-right"></i></a></li>
                                    <?php else: ?>
                                        <li class="disabled"><span><i class="fas fa-angle-right"></i></span></li>
                                        <li class="disabled"><span><i class="fas fa-angle-double-right"></i></span></li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Add New Record Pop-up Card -->
        <div class="popup-overlay" id="addRecordPopup" role="dialog" aria-modal="true">
            <div class="popup-card">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-plus-circle"></i> New Entry</div>
                            <h5>Add Finance Record</h5>
                            <div class="popup-header-sub">Fill in the transaction details below</div>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeAddPopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="financeForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <!-- Document Reference -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-file-alt"></i> Document Reference</div>
                            </div>
                            <div class="col-md-6">
                                <label for="transaction_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="dv_check_no" class="form-label">DV/Payroll Check No.</label>
                                <input type="text" class="form-control" id="dv_check_no" name="dv_check_no" placeholder="e.g., DV-0001" maxlength="50">
                            </div>
                            <div class="col-12">
                                <label for="fund_title" class="form-label">Particulars/Supplier</label>
                                <input type="text" class="form-control" id="fund_title" name="fund_title" placeholder="e.g., ARCHIEMEDES A. AZURIN" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Notes / Purpose</label>
                                <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional notes about this transaction..."></textarea>
                            </div>

                            <!-- Advances for Operating Expenses -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-money-bill-wave"></i> Advances for Operating Expenses (19901010)</div>
                            </div>
                            <div class="col-md-3">
                                <label for="cash_advance" class="form-label">Cash Advance (₱)</label>
                                <input type="number" class="form-control" id="cash_advance" name="cash_advance" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="amount" class="form-label">Payments (₱)</label>
                                <input type="number" class="form-control" id="amount" name="payments" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="tax_withheld" class="form-label">Tax Withheld (₱)</label>
                                <input type="number" class="form-control" id="tax_withheld" name="tax_withheld" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="balance" class="form-label">Balance (₱)</label>
                                <input type="number" class="form-control" id="balance" name="balance" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- MOOE Breakdown -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-table"></i> Breakdown — MOOE Columns</div>
                            </div>
                            <div class="col-md-4">
                                <label for="electricity" class="form-label">Electricity Expenses (₱)</label>
                                <input type="number" class="form-control" id="electricity" name="electricity" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="semi_expendable" class="form-label">Semi-Expendable ICT Equipment (₱)</label>
                                <input type="number" class="form-control" id="semi_expendable" name="semi_expendable" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="other_general" class="form-label">Other General Services (₱)</label>
                                <input type="number" class="form-control" id="other_general" name="other_general" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="training" class="form-label">Training Expenses (₱)</label>
                                <input type="number" class="form-control" id="training" name="training" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="water" class="form-label">Water Expenses (₱)</label>
                                <input type="number" class="form-control" id="water" name="water" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="other_supplies" class="form-label">Other Supplies &amp; Materials (₱)</label>
                                <input type="number" class="form-control" id="other_supplies" name="other_supplies" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="internet" class="form-label">Internet Subscription (₱)</label>
                                <input type="number" class="form-control" id="internet" name="internet" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="due_to_bir" class="form-label">Due to BIR (₱)</label>
                                <input type="number" class="form-control" id="due_to_bir" name="due_to_bir" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="account_description" class="form-label">Account Description</label>
                                <input type="text" class="form-control" id="account_description" name="account_description" placeholder="e.g., Advertising Expenses" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label for="uacs_code" class="form-label">UACS Code</label>
                                <input type="text" class="form-control" id="uacs_code" name="uacs_code" placeholder="e.g., 5029901000" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label for="amount_other" class="form-label">Amount (₱)</label>
                                <input type="number" class="form-control" id="amount_other" name="amount_other" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- Category / Override -->
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Select Category</option>
                                    <option value="Utilities">Utilities → Electricity Expenses</option>
                                    <option value="Equipment">Equipment → Semi-Expendable ICT</option>
                                    <option value="Salaries">Salaries → Other General Services</option>
                                    <option value="Other">Other → Other General Services</option>
                                    <option value="Events">Events → Training Expenses</option>
                                    <option value="Sports">Sports → Training Expenses</option>
                                    <option value="Transportation">Transportation → Training Expenses</option>
                                    <option value="Maintenance">Maintenance → Water Expenses</option>
                                    <option value="Supplies">Supplies → Other Supplies &amp; Materials</option>
                                    <option value="Books">Books → Other Supplies &amp; Materials</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mooe_col" class="form-label">Override MOOE Column (optional)</label>
                                <select class="form-select" id="mooe_col" name="mooe_col">
                                    <option value="">— Use category mapping —</option>
                                    <option value="electricity">Electricity Expenses</option>
                                    <option value="semi_expendable">Semi-Expendable ICT Equipment</option>
                                    <option value="other_general">Other General Services</option>
                                    <option value="training">Training Expenses</option>
                                    <option value="water">Water Expenses</option>
                                    <option value="other_supplies">Other Supplies &amp; Materials Expenses</option>
                                    <option value="internet">Internet Subscription Expenses</option>
                                    <option value="due_to_bir">Due to BIR</option>
                                </select>
                            </div>

                            <!-- Proof of Payment -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-paperclip"></i> Proof of Payment</div>
                            </div>
                            <div class="col-12">
                                <div class="upload-zone" id="proofUploadZone">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="upload-text">Click or drag to upload proof image</div>
                                    <div class="upload-hint">JPG, PNG, GIF, WebP (Max 5MB) — Optional</div>
                                    <input type="file" id="proof_image" name="proof_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>
                                <div class="image-preview" id="imagePreview">
                                    <img id="previewImg" src="" alt="Preview">
                                    <button type="button" class="remove-image" onclick="removeProofImage()"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeAddPopup()">Cancel</button>
                    <button type="submit" form="financeForm" class="btn btn-success" id="submitBtn">Save Record</button>
                </div>
            </div>
        </div>

        <!-- Edit Record Pop-up Card -->
        <div class="popup-overlay" id="editRecordPopup" role="dialog" aria-modal="true">
            <div class="popup-card">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-edit"></i> Edit Entry</div>
                            <h5>Edit Finance Record</h5>
                            <div class="popup-header-sub">Update the transaction details below</div>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeEditPopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="editFinanceForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">
                            <!-- Document Reference -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-file-alt"></i> Document Reference</div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_transaction_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_dv_check_no" class="form-label">DV/Payroll Check No.</label>
                                <input type="text" class="form-control" id="edit_dv_check_no" name="dv_check_no" placeholder="e.g., DV-0001" maxlength="50">
                            </div>
                            <div class="col-12">
                                <label for="edit_fund_title" class="form-label">Particulars/Supplier</label>
                                <input type="text" class="form-control" id="edit_fund_title" name="fund_title" maxlength="255">
                            </div>
                            <div class="col-12">
                                <label for="edit_description" class="form-label">Notes / Purpose</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                            </div>

                            <!-- Advances for Operating Expenses -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-money-bill-wave"></i> Advances for Operating Expenses (19901010)</div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_cash_advance" class="form-label">Cash Advance (₱)</label>
                                <input type="number" class="form-control" id="edit_cash_advance" name="cash_advance" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_amount" class="form-label">Payments (₱)</label>
                                <input type="number" class="form-control" id="edit_amount" name="payments" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_tax_withheld" class="form-label">Tax Withheld (₱)</label>
                                <input type="number" class="form-control" id="edit_tax_withheld" name="tax_withheld" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="edit_balance" class="form-label">Balance (₱)</label>
                                <input type="number" class="form-control" id="edit_balance" name="balance" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- MOOE Breakdown -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-table"></i> Breakdown — MOOE Columns</div>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_electricity" class="form-label">Electricity Expenses (₱)</label>
                                <input type="number" class="form-control" id="edit_electricity" name="electricity" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_semi_expendable" class="form-label">Semi-Expendable ICT Equipment (₱)</label>
                                <input type="number" class="form-control" id="edit_semi_expendable" name="semi_expendable" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_other_general" class="form-label">Other General Services (₱)</label>
                                <input type="number" class="form-control" id="edit_other_general" name="other_general" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_training" class="form-label">Training Expenses (₱)</label>
                                <input type="number" class="form-control" id="edit_training" name="training" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_water" class="form-label">Water Expenses (₱)</label>
                                <input type="number" class="form-control" id="edit_water" name="water" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_other_supplies" class="form-label">Other Supplies &amp; Materials (₱)</label>
                                <input type="number" class="form-control" id="edit_other_supplies" name="other_supplies" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_internet" class="form-label">Internet Subscription (₱)</label>
                                <input type="number" class="form-control" id="edit_internet" name="internet" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_due_to_bir" class="form-label">Due to BIR (₱)</label>
                                <input type="number" class="form-control" id="edit_due_to_bir" name="due_to_bir" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_account_description" class="form-label">Account Description</label>
                                <input type="text" class="form-control" id="edit_account_description" name="account_description" placeholder="e.g., Advertising Expenses" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_uacs_code" class="form-label">UACS Code</label>
                                <input type="text" class="form-control" id="edit_uacs_code" name="uacs_code" placeholder="e.g., 5029901000" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_amount_other" class="form-label">Amount (₱)</label>
                                <input type="number" class="form-control" id="edit_amount_other" name="amount_other" placeholder="0.00" step="0.01" min="0">
                            </div>

                            <!-- Category / Override -->
                            <div class="col-md-6">
                                <label for="edit_category" class="form-label">Category</label>
                                <select class="form-select" id="edit_category" name="category">
                                    <option value="">Select Category</option>
                                    <option value="Utilities">Utilities → Electricity Expenses</option>
                                    <option value="Equipment">Equipment → Semi-Expendable ICT</option>
                                    <option value="Salaries">Salaries → Other General Services</option>
                                    <option value="Other">Other → Other General Services</option>
                                    <option value="Events">Events → Training Expenses</option>
                                    <option value="Sports">Sports → Training Expenses</option>
                                    <option value="Transportation">Transportation → Training Expenses</option>
                                    <option value="Maintenance">Maintenance → Water Expenses</option>
                                    <option value="Supplies">Supplies → Other Supplies &amp; Materials</option>
                                    <option value="Books">Books → Other Supplies &amp; Materials</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_mooe_col" class="form-label">Override MOOE Column (optional)</label>
                                <select class="form-select" id="edit_mooe_col" name="mooe_col">
                                    <option value="">— Use category mapping —</option>
                                    <option value="electricity">Electricity Expenses</option>
                                    <option value="semi_expendable">Semi-Expendable ICT Equipment</option>
                                    <option value="other_general">Other General Services</option>
                                    <option value="training">Training Expenses</option>
                                    <option value="water">Water Expenses</option>
                                    <option value="other_supplies">Other Supplies &amp; Materials Expenses</option>
                                    <option value="internet">Internet Subscription Expenses</option>
                                    <option value="due_to_bir">Due to BIR</option>
                                </select>
                            </div>

                            <!-- Proof of Payment -->
                            <div class="col-12">
                                <div class="form-section-title"><i class="fas fa-paperclip"></i> Proof of Payment</div>
                            </div>
                            <div class="col-12">
                                <div class="upload-zone" id="editProofUploadZone">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="upload-text">Click or drag to replace proof image</div>
                                    <div class="upload-hint">JPG, PNG, GIF, WebP (Max 5MB) — Leave empty to keep existing</div>
                                    <input type="file" id="edit_proof_image" name="proof_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>
                                <div class="image-preview" id="editImagePreview">
                                    <img id="editPreviewImg" src="" alt="Preview">
                                    <button type="button" class="remove-image" onclick="removeEditProofImage()"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeEditPopup()">Cancel</button>
                    <button type="submit" form="editFinanceForm" class="btn btn-info" id="editSubmitBtn">Update Record</button>
                </div>
            </div>
        </div>

        <!-- Proof Image Modal -->
        <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true" style="display: none;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-file-image"></i> Proof</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="proofImage" src="" alt="Proof" class="img-fluid" style="max-height: 70vh; border-radius: 8px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Pop-up Card -->
        <div class="popup-overlay" id="deletePopup" role="dialog" aria-modal="true">
            <div class="popup-card delete-popup">
                <div class="card-header">
                    <div class="popup-header-top">
                        <div style="position:relative;z-index:5;">
                            <div class="popup-header-badge"><i class="fas fa-exclamation-triangle"></i> Danger Zone</div>
                            <h5>Confirm Delete</h5>
                            <div class="popup-header-sub">This action is permanent and cannot be undone</div>
                        </div>
                        <button type="button" class="popup-close-btn" onclick="closeDeletePopup()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="delete-icon-circle"><i class="fas fa-trash-alt"></i></div>
                    <h4 class="delete-popup-title">Delete Finance Record?</h4>
                    <p class="delete-popup-message">Are you sure you want to delete this finance record? This action cannot be undone.</p>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeDeletePopup()">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Record</button>
                </div>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="toast" class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000" style="display:none;">
                <div class="toast-header">
                    <strong class="me-auto" id="toastTitle">Success</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body" id="toastMessage"></div>
            </div>
        </div>
    </main>

    <script src="admin_assets/js/admin_script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let deleteRecordId = null;
        let addPopup = null;
        let deletePopup = null;
        let proofModal = null;

        document.addEventListener('DOMContentLoaded', function() {
            addPopup = document.getElementById('addRecordPopup');
            deletePopup = document.getElementById('deletePopup');
            proofModal = new bootstrap.Modal(document.getElementById('proofModal'));

            fetch('./admin_nav.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('navigation-container').innerHTML = data;
                })
                .catch(error => console.error('Error loading navigation:', error));

            document.getElementById('financeForm').addEventListener('submit', handleFormSubmit);
            initializeDeleteButtons();

            addPopup.addEventListener('click', function(e) {
                if (e.target === addPopup) closeAddPopup();
            });
            deletePopup.addEventListener('click', function(e) {
                if (e.target === deletePopup) closeDeletePopup();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (addPopup.classList.contains('active')) closeAddPopup();
                    if (deletePopup.classList.contains('active')) closeDeletePopup();
                }
            });
        });

        function removeProofImage() {
            document.getElementById('proof_image').value = '';
            document.getElementById('imagePreview').classList.remove('show');
            document.getElementById('proofUploadZone').classList.remove('has-file');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('proof_image');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            document.getElementById('previewImg').src = e.target.result;
                            document.getElementById('imagePreview').classList.add('show');
                            document.getElementById('proofUploadZone').classList.add('has-file');
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });

        function openEditModal(btn) {
            const popup = document.getElementById('editRecordPopup');
            document.getElementById('edit_id').value = btn.dataset.id || '';
            document.getElementById('edit_fund_title').value = btn.dataset.fund_title || '';
            document.getElementById('edit_description').value = btn.dataset.note || '';
            document.getElementById('edit_dv_check_no').value = btn.dataset.dv_check_no || '';
            document.getElementById('edit_cash_advance').value = btn.dataset.cash_advance || '';
            document.getElementById('edit_amount').value = btn.dataset.payments || btn.dataset.amount || '';
            document.getElementById('edit_tax_withheld').value = btn.dataset.tax_withheld || '';
            document.getElementById('edit_balance').value = btn.dataset.balance || '';
            document.getElementById('edit_transaction_date').value = btn.dataset.transaction_date || '';
            document.getElementById('edit_electricity').value = btn.dataset.electricity || '';
            document.getElementById('edit_semi_expendable').value = btn.dataset.semi_expendable || '';
            document.getElementById('edit_other_general').value = btn.dataset.other_general || '';
            document.getElementById('edit_training').value = btn.dataset.training || '';
            document.getElementById('edit_water').value = btn.dataset.water || '';
            document.getElementById('edit_other_supplies').value = btn.dataset.other_supplies || '';
            document.getElementById('edit_internet').value = btn.dataset.internet || '';
            document.getElementById('edit_due_to_bir').value = btn.dataset.due_to_bir || '';
            document.getElementById('edit_amount_other').value = btn.dataset.amount_other || '';
            document.getElementById('edit_account_description').value = btn.dataset.account_description || '';
            document.getElementById('edit_uacs_code').value = btn.dataset.uacs_code || '';
            // Set category select
            const catSel = document.getElementById('edit_category');
            for (let opt of catSel.options) {
                opt.selected = opt.value === btn.dataset.category;
            }
            // Set mooe_col select
            const mooeSel = document.getElementById('edit_mooe_col');
            for (let opt of mooeSel.options) {
                opt.selected = opt.value === (btn.dataset.mooe_col || '');
            }
            popup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditPopup() {
            document.getElementById('editRecordPopup').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('editFinanceForm').reset();
            const preview = document.getElementById('editImagePreview');
            preview.classList.remove('show');
        }

        function removeEditProofImage() {
            document.getElementById('edit_proof_image').value = '';
            document.getElementById('editImagePreview').classList.remove('show');
            document.getElementById('editProofUploadZone').classList.remove('has-file');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Edit proof image preview
            const editFileInput = document.getElementById('edit_proof_image');
            if (editFileInput) {
                editFileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            document.getElementById('editPreviewImg').src = e.target.result;
                            document.getElementById('editImagePreview').classList.add('show');
                            document.getElementById('editProofUploadZone').classList.add('has-file');
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }

            // Edit form submit
            const editForm = document.getElementById('editFinanceForm');
            if (editForm) {
                editForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('editSubmitBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

                    try {
                        const formData = new FormData(editForm);
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (data.status === 'success') {
                            showToast('success', data.message);
                            closeEditPopup();
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', data.message);
                        }
                    } catch (err) {
                        showToast('error', 'An error occurred. Please try again.');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = 'Update Record';
                    }
                });
            }

            // Close edit popup on overlay click
            document.getElementById('editRecordPopup').addEventListener('click', function(e) {
                if (e.target === this) closeEditPopup();
            });
        });

        function openAddModal() {
            addPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddPopup() {
            addPopup.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('financeForm').reset();
        }

        async function handleFormSubmit(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

            try {
                const formData = new FormData(document.getElementById('financeForm'));
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showToast('success', data.message);
                    closeAddPopup();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Record';
            }
        }

        function initializeDeleteButtons() {
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteRecordId = this.getAttribute('data-id');
                    openDeletePopup();
                });
            });

            document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
                if (!deleteRecordId) return;
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', deleteRecordId);
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        showToast('success', data.message);
                        closeDeletePopup();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = 'Delete Record';
                    deleteRecordId = null;
                }
            });
        }

        function openDeletePopup() {
            deletePopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDeletePopup() {
            deletePopup.classList.remove('active');
            document.body.style.overflow = '';
            deleteRecordId = null;
        }

        function viewProof(filename) {
            document.getElementById('proofImage').src = 'admin_assets/finance_proofs/' + filename;
            proofModal.show();
        }

        function exportToCSV() {
            window.location.href = '?export=csv';
        }

        function refreshTable() {
            location.reload();
        }

        function showToast(type, message) {
            const toast = document.getElementById('toast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');

            toast.style.display = '';
            toast.classList.remove('success', 'error');
            if (type === 'success') {
                toast.classList.add('success');
                toastTitle.textContent = 'Success';
            } else {
                toast.classList.add('error');
                toastTitle.textContent = 'Error';
            }

            toastMessage.textContent = message;
            bootstrap.Toast.getOrCreateInstance(toast).show();
        }
    </script>
</body>

</html>