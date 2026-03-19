<?php

/**
 * file_request_api.php  — STUB
 * ─────────────────────────────────────────────────────────────
 * Placeholder so chatbox.php and admin_chatbox.php load without
 * JS errors while the full file-request feature is pending.
 *
 * Every action returns a safe, well-formed JSON response.
 * Replace this file with the real implementation in Phase 5.
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // Student requests: returns empty list so the dropdown renders
    // "No restricted files available." instead of a broken state.
    case 'get_restricted_files':
        echo json_encode(['success' => true, 'files' => []]);
        break;

    // Student submits a file request
    case 'submit_file_request':
        echo json_encode(['success' => false, 'message' => 'File requests are not yet enabled.']);
        break;

    // Admin approves or rejects a file request
    case 'process_file_request':
        echo json_encode(['success' => false, 'message' => 'File requests are not yet enabled.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
