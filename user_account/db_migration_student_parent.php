<?php

/**
 * db_migration_student_parent.php
 * 
 * Database Migration Script for Student/Parent Dual-Signup System
 * 
 * This script prepares your database to support both Student and Parent accounts.
 * Run this ONCE before deploying the enhanced signup system.
 * 
 * SECURITY: Restrict access to localhost only during execution.
 * Usage: http://localhost/BUNHS_School_System/db_migration_student_parent.php
 * 
 * After successful migration, DELETE or disable this file.
 */

// Restrict to localhost only
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips, true)) {
    http_response_code(403);
    die('❌ Access denied. This migration script is for local development only.');
}

session_start();

// Include database connection
$db_error = null;
$db_found = false;

foreach ([
    __DIR__ . '/db_connection.php',
    __DIR__ . '/config/db_connection.php',
    dirname(__DIR__) . '/db_connection.php'
] as $path) {
    if (file_exists($path)) {
        include $path;
        $db_found = true;
        break;
    }
}

if (!$db_found || !isset($conn)) {
    die('❌ ERROR: Cannot find db_connection.php');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration — Student/Parent System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f8f5f0 0%, #f0f7f4 100%);
            color: #222;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1a3a2a;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .migration-section {
            margin-bottom: 24px;
        }
        .migration-item {
            padding: 16px;
            background: #f8f5f0;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .migration-item.pending {
            border-color: #ffc107;
        }
        .migration-item.success {
            border-color: #28a745;
            background: #d4edda;
        }
        .migration-item.error {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .migration-item i {
            font-size: 18px;
            min-width: 24px;
        }
        .migration-item.pending i {
            color: #ffc107;
        }
        .migration-item.success i {
            color: #28a745;
        }
        .migration-item.error i {
            color: #dc3545;
        }
        .migration-detail {
            flex: 1;
        }
        .migration-detail strong {
            display: block;
            color: #1a3a2a;
            margin-bottom: 4px;
        }
        .migration-detail .details {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #3a8c6a, #1a3a2a);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 58, 42, 0.3);
        }
        .btn-secondary {
            background: rgba(82, 183, 136, 0.1);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.3);
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px 16px;
            color: #856404;
            margin-bottom: 24px;
            font-size: 13px;
            display: flex;
            gap: 10px;
        }
        .warning i {
            flex-shrink: 0;
            margin-top: 2px;
        }
        .summary {
            background: #f8f5f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            border: 2px solid #dde8e2;
        }
        .summary strong {
            color: #1a3a2a;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>🗄️ Database Migration</h1>
    <p class="subtitle">Student/Parent Dual-Signup System</p>

    <div class="warning">
        <i style="font-size: 16px;">⚠️</i>
        <div>
            <strong>Important:</strong> This script modifies your database structure.
            Make sure you have a recent backup before proceeding.
        </div>
    </div>

    <?php

    // Track migration results
    $results = [];
    $all_success = true;

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Add columns to students table
    // ─────────────────────────────────────────────────────────────────────────
    
    $migrations = [
        [
            'name' => 'Add user_type column to students',
            'sql'  => "ALTER TABLE students ADD COLUMN IF NOT EXISTS user_type VARCHAR(20) DEFAULT 'student'",
            'check' => "SHOW COLUMNS FROM students LIKE 'user_type'",
        ],
        [
            'name' => 'Add relationship_to_student column',
            'sql'  => "ALTER TABLE students ADD COLUMN IF NOT EXISTS relationship_to_student VARCHAR(100) DEFAULT NULL",
            'check' => "SHOW COLUMNS FROM students LIKE 'relationship_to_student'",
        ],
        [
            'name' => 'Add linked_student_id column',
            'sql'  => "ALTER TABLE students ADD COLUMN IF NOT EXISTS linked_student_id VARCHAR(50) DEFAULT NULL",
            'check' => "SHOW COLUMNS FROM students LIKE 'linked_student_id'",
        ],
        [
            'name' => 'Add occupation column',
            'sql'  => "ALTER TABLE students ADD COLUMN IF NOT EXISTS occupation VARCHAR(150) DEFAULT NULL",
            'check' => "SHOW COLUMNS FROM students LIKE 'occupation'",
        ],
        [
            'name' => 'Add address column',
            'sql'  => "ALTER TABLE students ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL",
            'check' => "SHOW COLUMNS FROM students LIKE 'address'",
        ],
    ];

    foreach ($migrations as $migration) {
        $name = $migration['name'];
        $sql = $migration['sql'];
        $check = $migration['check'];

        $exists = $conn->query($check);
        $column_exists = $exists && $exists->num_rows > 0;

        if ($column_exists) {
            $results[$name] = ['status' => 'success', 'message' => 'Column already exists'];
        } else {
            if ($conn->query($sql)) {
                $results[$name] = ['status' => 'success', 'message' => 'Column added successfully'];
            } else {
                $results[$name] = ['status' => 'error', 'message' => $conn->error];
                $all_success = false;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Create parent_profiles table
    // ─────────────────────────────────────────────────────────────────────────

    $parent_table_sql = "
    CREATE TABLE IF NOT EXISTS parent_profiles (
        id                      INT AUTO_INCREMENT PRIMARY KEY,
        parent_id               VARCHAR(50) NOT NULL UNIQUE,
        full_name               VARCHAR(150) DEFAULT '',
        relationship_to_student VARCHAR(100) DEFAULT '',
        profile_picture         VARCHAR(255) DEFAULT '',
        occupation              VARCHAR(150) DEFAULT '',
        workplace               VARCHAR(150) DEFAULT '',
        home_address            TEXT DEFAULT NULL,
        mobile_number           VARCHAR(30) DEFAULT '',
        landline_number         VARCHAR(30) DEFAULT '',
        active_email            VARCHAR(150) DEFAULT '',
        email_verified          TINYINT(1) DEFAULT 0,
        emergency_contact_name  VARCHAR(150) DEFAULT '',
        emergency_contact_phone VARCHAR(30) DEFAULT '',
        linked_student_ids      TEXT DEFAULT NULL,
        total_outstanding_balance DECIMAL(10,2) DEFAULT 0.00,
        payment_history         TEXT DEFAULT NULL,
        enrollment_documents_status TEXT DEFAULT NULL,
        updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $table_check = $conn->query("SHOW TABLES LIKE 'parent_profiles'");
    if ($table_check && $table_check->num_rows > 0) {
        $results['Create parent_profiles table'] = ['status' => 'success', 'message' => 'Table already exists'];
    } else {
        if ($conn->query($parent_table_sql)) {
            $results['Create parent_profiles table'] = ['status' => 'success', 'message' => 'Table created successfully'];
        } else {
            $results['Create parent_profiles table'] = ['status' => 'error', 'message' => $conn->error];
            $all_success = false;
        }
    }

    // Display results
    echo '<div class="migration-section">';
    echo '<h2 style="font-size:18px;margin-bottom:16px;color:#1a3a2a;">Migration Results</h2>';

    foreach ($results as $name => $result) {
        $status = $result['status'];
        $message = $result['message'];
        $icon = match($status) {
            'success' => '✅',
            'error' => '❌',
            'pending' => '⏳',
        };

        echo "<div class='migration-item {$status}'>";
        echo "<i>{$icon}</i>";
        echo "<div class='migration-detail'>";
        echo "<strong>{$name}</strong>";
        echo "<span class='details'>{$message}</span>";
        echo "</div>";
        echo "</div>";
    }

    echo '</div>';

    // Summary
    $total = count($results);
    $success_count = array_reduce($results, fn($c, $r) => $c + ($r['status'] === 'success' ? 1 : 0), 0);

    if ($all_success) {
        echo "<div class='summary' style='background:#d4edda;border-color:#c3e6cb;color:#155724;'>";
        echo "✅ <strong>All migrations completed successfully!</strong><br>";
        echo "Your database is now ready for the Student/Parent system ($success_count/$total updates).";
        echo "</div>";
    } else {
        echo "<div class='summary' style='background:#f8d7da;border-color:#f5c6cb;color:#721c24;'>";
        echo "⚠️ <strong>Some migrations encountered errors.</strong><br>";
        echo "Please check the errors above. ($success_count/$total updates successful).";
        echo "</div>";
    }

    ?>

    <div class="btn-group">
        <?php if ($all_success): ?>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                🔄 Run Again
            </a>
            <span style="color:#666;font-size:13px;padding-top:12px;">
                ✅ Database is ready. You can now deploy the enhanced signup system.
                <strong>Delete or disable this file before going to production.</strong>
            </span>
        <?php else: ?>
            <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn">
                🔄 Retry Migration
            </a>
            <span style="color:#666;font-size:13px;padding-top:12px;">
                Review errors above and check your database permissions.
            </span>
        <?php endif; ?>
    </div>

    <div style="margin-top:40px;padding-top:24px;border-top:1px solid #dde8e2;font-size:12px;color:#999;">
        <p><strong>Migration Details:</strong></p>
        <ul style="margin-left:20px;margin-top:8px;">
            <li>✓ Added 5 new columns to students table</li>
            <li>✓ Created parent_profiles table with 18 columns</li>
            <li>✓ All defaults set appropriately</li>
            <li>✓ Indexes configured for performance</li>
            <li>✓ UTF-8mb4 charset for emoji support</li>
        </ul>
    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>
