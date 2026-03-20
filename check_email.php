<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  check_email.php — CACHED VERSION
//  AJAX endpoint called on every keystroke during sub-admin signup.
//  Without caching: one DB query per keypress.
//  With caching: DB queried ONCE per unique email; result served from memory
//  for the next 5 minutes.
//
//  INVALIDATION: When a new sub-admin is successfully created in signup.php,
//  call cache_delete("email_exists:{$email}") so the next check reflects reality.
// ═══════════════════════════════════════════════════════════════════════════════

include 'db_connection.php';
require_once 'cache_helper.php';    // Load caching layer

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    $email = trim($_POST['email']);

    // Basic format validation — no DB needed for this
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'invalid';
        exit();
    }

    // Normalise to lowercase so "Admin@School.com" and "admin@school.com"
    // share the same cache slot
    $email_key = strtolower($email);
    $cache_key = "email_exists:{$email_key}";

    // ── 1. Try cache first ────────────────────────────────────────────────────
    $cached = cache_get($cache_key);

    if ($cached !== false) {
        // CACHE HIT — return the stored 'exists' or 'available' string directly
        echo $cached;
        exit();
    }

    // ── 2. CACHE MISS — run DB query ──────────────────────────────────────────
    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE email = ?");
    $stmt->bind_param('s', $email_key);
    $stmt->execute();
    $result = $stmt->get_result();

    $status = $result->num_rows > 0 ? 'exists' : 'available';

    // Cache the result so subsequent keystrokes (and identical emails) skip DB
    cache_set($cache_key, $status, CACHE_TTL_EMAIL_EXISTS);

    $stmt->close();
    $conn->close();

    echo $status;
} else {
    echo 'invalid';
}
