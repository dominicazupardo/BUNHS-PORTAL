<?php

/**
 * club_chat_api.php  — STUB
 * ─────────────────────────────────────────────────────────────
 * Placeholder so chatbox.php loads without JS errors while the
 * full club-chat feature is pending (Phase 5).
 *
 * get_student_clubs returns an empty array so the sidebar
 * renders cleanly with just the "Direct" admin conversation.
 * Replace this file with the real implementation in Phase 5.
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // Returns empty clubs list — sidebar shows no "Club Chats" section.
    case 'get_student_clubs':
        echo json_encode(['success' => true, 'clubs' => []]);
        break;

    // Fetching messages for a club the student isn't in yet
    case 'fetch_club_messages':
        echo json_encode(['success' => true, 'messages' => []]);
        break;

    // Sending a club message before feature is live
    case 'send_club_message':
        echo json_encode(['success' => false, 'message' => 'Club chat is not yet enabled.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
