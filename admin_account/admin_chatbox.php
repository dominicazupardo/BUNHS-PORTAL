<?php

/**
 * admin_chatbox.php
 * Enhanced: file request approval, club group chat support.
 */

require_once '../session_config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    header('Location: ../index.php');
    exit;
}

include '../db_connection.php';

// Build base URLs relative to this file's actual location on disk.
// admin_chatbox.php lives in /admin_account/, so:
//   adminBase  = the URL path of /admin_account/  (for API calls)
//   assetsBase = the URL path one level up         (for shared assets)
// Using dirname() on SCRIPT_NAME is reliable regardless of sub-folder depth
// or whether the project is installed at root or in a sub-directory.
$_script     = $_SERVER['SCRIPT_NAME'];            // e.g. /BUNHS/admin_account/admin_chatbox.php
$adminBase   = rtrim(dirname($_script), '/') . '/';          // /BUNHS/admin_account/
$assetsBase  = rtrim(dirname(dirname($_script)), '/') . '/'; // /BUNHS/

$preloadConvId  = (int) ($_GET['conv'] ?? 0);
$adminInitial   = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbox – BUNHS Admin</title>
    <link rel="stylesheet" href="<?= $assetsBase ?>admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ════════════════════════════════════════════
           ROOT
        ════════════════════════════════════════════ */
        :root {
            --moss: #7a8f4e;
            --moss-dark: #5c6b38;
            --moss-light: #adbf72;
            --moss-ultra: rgba(122, 143, 78, .1);
            --moss-glow: rgba(122, 143, 78, .3);
            --bg: #f2f5f0;
            --surface: #ffffff;
            --border: #e4e9de;
            --text: #1a2010;
            --muted: #6b7c55;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-md: 0 6px 24px rgba(0, 0, 0, .09);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, .12);
            --radius: 14px;
            --font: 'DM Sans', sans-serif;
            --font-d: 'Syne', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
        }

        /* ════════════════════════════════════════════
           PAGE WRAPPER
        ════════════════════════════════════════════ */
        .chat-page {
            padding: 22px 24px 0;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px);
        }

        .chat-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .chat-page-header h1 {
            font-family: var(--font-d);
            font-size: 22px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-page-header h1 i {
            color: var(--moss);
        }

        .chat-page-header p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 2px;
        }

        /* Filter pills */
        .filter-pills {
            display: flex;
            gap: 8px;
        }

        .filter-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            transition: all .15s;
        }

        .filter-pill:hover {
            border-color: var(--moss);
            color: var(--moss);
        }

        .filter-pill.active {
            background: var(--moss);
            color: #fff;
            border-color: var(--moss);
        }

        .filter-pill .cnt {
            background: rgba(255, 255, 255, .3);
            color: inherit;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 3px;
        }

        .filter-pill:not(.active) .cnt {
            background: var(--moss-ultra);
            color: var(--moss);
        }

        /* ════════════════════════════════════════════
           CHAT LAYOUT
        ════════════════════════════════════════════ */
        .chat-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 18px;
            flex: 1;
            min-height: 0;
        }

        /* ── Conversation list ── */
        .conv-list {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .conv-list-header {
            padding: 16px 18px 10px;
            border-bottom: 1px solid var(--border);
        }

        .conv-list-header h3 {
            font-family: var(--font-d);
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .conv-search {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 13px;
            outline: none;
            font-family: var(--font);
            background: var(--bg);
            transition: border-color .15s;
        }

        .conv-search:focus {
            border-color: var(--moss);
        }

        .conv-items {
            flex: 1;
            overflow-y: auto;
        }

        .conv-items::-webkit-scrollbar {
            width: 3px;
        }

        .conv-items::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .conv-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 10px 18px 4px;
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f5f8f2;
            transition: background .14s;
            position: relative;
        }

        .conv-item:hover {
            background: var(--moss-ultra);
        }

        .conv-item.active {
            background: var(--moss-ultra);
            border-left: 3px solid var(--moss);
        }

        .conv-avatar {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .conv-avatar.club-av {
            background: linear-gradient(135deg, #2d6a4f, #1b4332);
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .club-tag {
            background: var(--moss-ultra);
            color: var(--moss);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 1px 6px;
            border-radius: 20px;
            border: 1px solid rgba(122, 143, 78, .2);
        }

        .conv-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-time {
            font-size: 10px;
            color: #aaa;
            white-space: nowrap;
        }

        .conv-unread {
            background: var(--moss);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            position: absolute;
            top: 12px;
            right: 12px;
        }

        /* File request indicator */
        .conv-filereq {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f59e0b;
            position: absolute;
            top: 12px;
            right: 12px;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, .2);
        }

        /* ════════════════════════════════════════════
           CHAT WINDOW
        ════════════════════════════════════════════ */
        .chat-win {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            gap: 12px;
        }

        .chat-empty i {
            font-size: 48px;
            opacity: .2;
        }

        .chat-empty p {
            font-size: 14px;
        }

        /* Chat header */
        .chat-win-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 13px;
            background: linear-gradient(90deg, rgba(122, 143, 78, .04), transparent);
        }

        .chat-win-avatar {
            width: 44px;
            height: 44px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 17px;
        }

        .chat-win-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .chat-win-status {
            font-size: 12px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* Pending requests badge in header */
        .pending-req-badge {
            margin-left: auto;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            display: none;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all .15s;
        }

        .pending-req-badge:hover {
            background: #fde68a;
        }

        .pending-req-badge.show {
            display: flex;
        }

        /* Messages */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f6f9f2;
        }

        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        /* Date divider */
        .date-sep {
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            position: relative;
            margin: 6px 0;
        }

        .date-sep span {
            background: #f6f9f2;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }

        .date-sep::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: var(--border);
        }

        /* Bubbles */
        .msg-row {
            display: flex;
            gap: 9px;
            max-width: 74%;
            animation: pop .2s ease;
        }

        @keyframes pop {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-row.mine {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .msg-av {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            flex-shrink: 0;
            align-self: flex-end;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
        }

        .msg-row.mine .msg-av {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .msg-bubble {
            background: var(--surface);
            padding: 11px 15px;
            border-radius: 14px 14px 14px 4px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .msg-row.mine .msg-bubble {
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            color: #fff;
            border: none;
            border-radius: 14px 14px 4px 14px;
        }

        .msg-text {
            font-size: 13.5px;
            line-height: 1.55;
            word-break: break-word;
        }

        .msg-time {
            font-size: 10.5px;
            color: var(--muted);
            margin-top: 4px;
        }

        .msg-row.mine .msg-time {
            color: rgba(255, 255, 255, .6);
            text-align: right;
        }

        /* ── File request bubble ── */
        .msg-bubble.file-req-bubble {
            background: #fffdf5;
            border: 1.5px solid #f0e09a;
            border-radius: 14px 14px 14px 4px;
        }

        .file-req-header {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 7px;
        }

        .file-req-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
        }

        .file-req-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--moss);
        }

        .file-req-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .file-req-reason {
            font-size: 12.5px;
            color: var(--muted);
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .req-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .req-status-pill.pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .req-status-pill.approved {
            background: #dcfce7;
            color: #14532d;
        }

        .req-status-pill.rejected {
            background: #fee2e2;
            color: #7f1d1d;
        }

        /* Approval action buttons */
        .approval-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .approve-btn,
        .reject-btn {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
            font-family: var(--font);
        }

        .approve-btn {
            background: #22c55e;
            color: #fff;
        }

        .approve-btn:hover {
            background: #16a34a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, .3);
        }

        .reject-btn {
            background: #ef4444;
            color: #fff;
        }

        .reject-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, .3);
        }

        /* Input area */
        .chat-input-row {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .chat-input-row input {
            flex: 1;
            padding: 11px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            font-family: var(--font);
            transition: border-color .15s, box-shadow .15s;
        }

        .chat-input-row input:focus {
            border-color: var(--moss);
            box-shadow: 0 0 0 3px rgba(122, 143, 78, .1);
        }

        .send-btn {
            height: 40px;
            padding: 0 18px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all .18s;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px var(--moss-glow);
        }

        .send-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Loading */
        .loading-msgs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 40px;
            color: var(--muted);
            font-size: 13px;
        }

        .spin {
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast-zone {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }

        .toast {
            background: var(--surface);
            border-radius: 11px;
            padding: 13px 18px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            font-size: 13.5px;
            transform: translateX(120%);
            transition: transform .3s;
            border-left: 4px solid transparent;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-color: #22c55e;
        }

        .toast.success i {
            color: #22c55e;
        }

        .toast.error {
            border-color: #ef4444;
        }

        .toast.error i {
            color: #ef4444;
        }

        /* ══════════════════════════════════════════
           RESPONSIVE — FULL MOBILE CHATBOX
        ══════════════════════════════════════════ */

        /* Back button (mobile: return to conv list) */
        .chat-back-btn {
            display: none;
            align-items: center;
            gap: 7px;
            background: none;
            border: none;
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            color: var(--moss);
            cursor: pointer;
            padding: 6px 0;
            -webkit-tap-highlight-color: transparent;
        }

        .chat-back-btn i {
            font-size: 14px;
        }

        /* ── Tablet (≤ 900px) ── */
        @media (max-width: 900px) {
            .chat-page {
                padding: 90px 12px 0 !important;
                height: calc(100vh - 0px) !important;
            }

            .chat-page-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
                margin-bottom: 12px !important;
            }

            .filter-pills {
                flex-wrap: wrap !important;
                gap: 6px !important;
            }

            /* Two-pane → single pane with slide behaviour */
            .chat-layout {
                grid-template-columns: 1fr !important;
                position: relative !important;
            }

            /* Conv list is the "default" pane */
            .conv-list {
                display: flex !important;
                position: absolute !important;
                inset: 0 !important;
                z-index: 2 !important;
                border-radius: var(--radius) !important;
                transform: translateX(0) !important;
                transition: transform .3s ease !important;
            }

            .conv-list.slide-out {
                transform: translateX(-105%) !important;
                pointer-events: none !important;
            }

            /* Chat window hides until a conv is selected */
            .chat-win {
                position: absolute !important;
                inset: 0 !important;
                z-index: 1 !important;
                transform: translateX(100%) !important;
                transition: transform .3s ease !important;
            }

            .chat-win.slide-in {
                transform: translateX(0) !important;
                z-index: 3 !important;
            }

            /* Show back button in mobile chat header */
            .chat-back-btn {
                display: flex !important;
            }

            /* Tighten chat-win-header on tablet */
            .chat-win-header {
                flex-wrap: wrap !important;
                gap: 8px !important;
                padding: 10px 14px !important;
            }

            .pending-req-badge {
                margin-left: 0 !important;
                font-size: 11px !important;
            }
        }

        /* ── Mobile (≤ 600px) ── */
        @media (max-width: 600px) {
            .chat-page {
                padding: 76px 8px 0 !important;
            }

            .chat-page-header h1 {
                font-size: 18px !important;
            }

            .chat-messages {
                padding: 14px 12px !important;
                gap: 10px !important;
            }

            .msg-row {
                max-width: 88% !important;
            }

            .chat-input-row {
                padding: 10px 12px !important;
                gap: 8px !important;
            }

            .chat-input-row input {
                font-size: 14px !important;
                padding: 10px 14px !important;
            }

            .send-btn {
                padding: 10px 16px !important;
                font-size: 13px !important;
            }

            .filter-pill {
                padding: 5px 11px !important;
                font-size: 11.5px !important;
            }

            /* Ensure toast doesn't overflow */
            .toast-zone {
                right: 10px !important;
                left: 10px !important;
                width: auto !important;
            }
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <div class="content-area">
        <div class="chat-page">

            <div class="chat-page-header">
                <div>
                    <h1><i class="fas fa-comments"></i> Chatbox</h1>
                    <p>Student–Admin messaging portal</p>
                </div>
                <div class="filter-pills" id="filterPills">
                    <button class="filter-pill active" data-filter="all" onclick="setFilter('all')">
                        All <span class="cnt" id="cntAll">0</span>
                    </button>
                    <button class="filter-pill" data-filter="unread" onclick="setFilter('unread')">
                        Unread <span class="cnt" id="cntUnread">0</span>
                    </button>
                    <button class="filter-pill" data-filter="pending_file" onclick="setFilter('pending_file')">
                        <i class="fas fa-file-clock"></i> File Requests <span class="cnt" id="cntFile">0</span>
                    </button>
                </div>
            </div>

            <div class="chat-layout">
                <!-- Conversation list -->
                <div class="conv-list" id="convList">
                    <div class="conv-list-header">
                        <h3>Conversations</h3>
                        <input type="text" class="conv-search" id="convSearch"
                            placeholder="Search students…" oninput="filterConvs(this.value)">
                    </div>
                    <div class="conv-items" id="convItems">
                        <div class="loading-msgs"><i class="fas fa-spinner spin"></i> Loading…</div>
                    </div>
                </div>

                <!-- Chat window -->
                <div class="chat-win" id="chatWin">
                    <div class="chat-empty" id="chatEmpty">
                        <i class="fas fa-comments"></i>
                        <p>Select a conversation to start chatting</p>
                    </div>

                    <div class="chat-win-header" id="chatWinHeader" style="display:none;">
                        <button class="chat-back-btn" id="chatBackBtn" aria-label="Back to conversations">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <div class="chat-win-avatar" id="chatWinAvatar">?</div>
                        <div>
                            <div class="chat-win-name" id="chatWinName">–</div>
                            <div class="chat-win-status" id="chatWinStatus">Active</div>
                        </div>
                        <div class="pending-req-badge" id="pendingReqBadge"
                            onclick="scrollToPendingRequest()">
                            <i class="fas fa-exclamation-circle"></i>
                            <span id="pendingReqCount">0</span> pending file request(s)
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages" style="display:none;"></div>

                    <div class="chat-input-row" id="chatInputRow" style="display:none;">
                        <input type="text" id="msgInput"
                            placeholder="Type a message…"
                            onkeydown="if(event.key==='Enter')sendMessage()">
                        <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>

        </div><!-- /chat-page -->
    </div><!-- /content-area -->

    <div class="toast-zone" id="toastZone"></div>

    <script>
        const API = '<?= $adminBase ?>chat_api.php';
        const FILE_REQ_API = '<?= $adminBase ?>file_request_api.php';
        const ADMIN_INIT = '<?= $adminInitial ?>';

        let activeConvId = 0;
        let activeStudent = {};
        let pollTimer = null;
        let allConvs = [];
        let activeFilter = 'all';

        /* ════════════════════════════════════════════
           CONVERSATIONS
        ════════════════════════════════════════════ */
        async function loadConversations() {
            const fd = new FormData();
            fd.append('action', 'fetch_conversations');
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) return;
            allConvs = data.conversations;

            updateFilterCounts();
            renderConvs(applyFilter(allConvs));

            const preload = <?= $preloadConvId ?>;
            if (preload) {
                const found = allConvs.find(c => c.id == preload);
                if (found) openConversation(found);
            }
        }

        function applyFilter(list) {
            if (activeFilter === 'unread') return list.filter(c => c.unread > 0);
            if (activeFilter === 'pending_file') return list.filter(c => c.pending_file_requests > 0);
            return list;
        }

        function updateFilterCounts() {
            document.getElementById('cntAll').textContent = allConvs.length;
            document.getElementById('cntUnread').textContent = allConvs.filter(c => c.unread > 0).length;
            document.getElementById('cntFile').textContent = allConvs.filter(c => c.pending_file_requests > 0).length;
        }

        function setFilter(f) {
            activeFilter = f;
            document.querySelectorAll('.filter-pill').forEach(el => {
                el.classList.toggle('active', el.dataset.filter === f);
            });
            renderConvs(applyFilter(allConvs));
        }

        function renderConvs(list) {
            const el = document.getElementById('convItems');
            if (!list.length) {
                el.innerHTML = '<div style="padding:28px;text-align:center;color:#94a3b8;font-size:13px;">No conversations found.</div>';
                return;
            }
            el.innerHTML = list.map(c => {
                const hasPendingFile = c.pending_file_requests > 0;
                return `
        <div class="conv-item ${c.id == activeConvId ? 'active' : ''}"
             data-id="${c.id}">
            <div class="conv-avatar">${escHtml(c.avatar_letter)}</div>
            <div class="conv-info">
                <div class="conv-name">${escHtml(c.student_name)}</div>
                <div class="conv-preview">${escHtml(c.last_message || '—')}</div>
            </div>
            <div style="text-align:right;">
                <div class="conv-time">${escHtml(c.time_ago)}</div>
            </div>
            ${c.unread > 0 && !hasPendingFile ? `<span class="conv-unread">${c.unread}</span>` : ''}
            ${hasPendingFile ? `<span class="conv-filereq" title="Pending file request"></span>` : ''}
        </div>`;
            }).join('');

            el.querySelectorAll('.conv-item').forEach((div, i) => {
                div.addEventListener('click', () => openConversation(list[i]));
            });
        }

        function filterConvs(q) {
            const filtered = allConvs.filter(c =>
                c.student_name.toLowerCase().includes(q.toLowerCase())
            );
            renderConvs(applyFilter(filtered));
        }

        /* ════════════════════════════════════════════
           OPEN CONVERSATION
        ════════════════════════════════════════════ */
        function openConversation(conv) {
            if (typeof conv === 'string') conv = JSON.parse(conv);
            activeConvId = conv.id;
            activeStudent = conv;

            document.querySelectorAll('.conv-item').forEach(el =>
                el.classList.toggle('active', parseInt(el.dataset.id) === activeConvId)
            );

            document.getElementById('chatEmpty').style.display = 'none';
            document.getElementById('chatWinHeader').style.display = '';
            document.getElementById('chatMessages').style.display = '';
            document.getElementById('chatInputRow').style.display = '';

            document.getElementById('chatWinAvatar').textContent = conv.avatar_letter;
            document.getElementById('chatWinName').textContent = conv.student_name;
            document.getElementById('chatWinStatus').textContent = 'Student · ' + (conv.grade_level || 'Active');

            /* Mobile: slide conv-list out, chat-win in */
            if (window.innerWidth <= 900) {
                document.getElementById('convList').classList.add('slide-out');
                document.getElementById('chatWin').classList.add('slide-in');
            }

            clearInterval(pollTimer);
            loadMessages();
            markRead();
            pollTimer = setInterval(loadMessages, 5000);
        }

        /* Mobile: back button returns to conversation list */
        document.addEventListener('DOMContentLoaded', function() {
            var backBtn = document.getElementById('chatBackBtn');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    document.getElementById('convList').classList.remove('slide-out');
                    document.getElementById('chatWin').classList.remove('slide-in');
                    activeConvId = 0;
                    clearInterval(pollTimer);
                });
            }
        });

        /* ════════════════════════════════════════════
           MESSAGES
        ════════════════════════════════════════════ */
        async function loadMessages() {
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('conversation_id', activeConvId);
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) return;
            renderMessages(data.messages);
            await refreshConvList();
        }

        function renderMessages(msgs) {
            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 40;

            let pendingCount = 0;
            let lastDate = '';

            box.innerHTML = msgs.map(m => {
                const isMine = m.sender_role === 'admin';
                const init = isMine ? ADMIN_INIT : (activeStudent.avatar_letter || '?');
                const msgDate = (m.created_at || '').substring(0, 10);

                let divider = '';
                if (msgDate && msgDate !== lastDate) {
                    lastDate = msgDate;
                    divider = `<div class="date-sep"><span>${formatDate(msgDate)}</span></div>`;
                }

                if (m.message_type === 'file_request') {
                    if (m.request_status === 'pending') pendingCount++;
                    return divider + buildFileReqBubble(m, isMine, init);
                }

                return `${divider}
        <div class="msg-row ${isMine ? 'mine' : ''}">
            <div class="msg-av">${escHtml(init)}</div>
            <div>
                <div class="msg-bubble">
                    <div class="msg-text">${escHtml(m.message)}</div>
                </div>
                <div class="msg-time">${escHtml(m.time_label)}</div>
            </div>
        </div>`;
            }).join('');

            if (atBottom) box.scrollTop = box.scrollHeight;

            // Show pending badge in header
            const badge = document.getElementById('pendingReqBadge');
            document.getElementById('pendingReqCount').textContent = pendingCount;
            badge.classList.toggle('show', pendingCount > 0);
        }

        function buildFileReqBubble(m, isMine, init) {
            const statusMap = {
                pending: {
                    cls: 'pending',
                    icon: 'fa-clock',
                    label: 'Pending Approval'
                },
                approved: {
                    cls: 'approved',
                    icon: 'fa-check-circle',
                    label: 'Approved'
                },
                rejected: {
                    cls: 'rejected',
                    icon: 'fa-times-circle',
                    label: 'Rejected'
                },
            };
            const s = statusMap[m.request_status] || statusMap.pending;

            const actionBtns = (m.request_status === 'pending') ? `
        <div class="approval-actions" id="req_actions_${m.request_id}">
            <button class="approve-btn" onclick="processFileRequest(${m.request_id}, 'approve')">
                <i class="fas fa-check"></i> Approve
            </button>
            <button class="reject-btn"  onclick="processFileRequest(${m.request_id}, 'reject')">
                <i class="fas fa-times"></i> Reject
            </button>
        </div>` : '';

            return `
    <div class="msg-row ${isMine ? 'mine' : ''}" id="msg_req_${m.request_id}">
        <div class="msg-av">${escHtml(init)}</div>
        <div>
            <div class="msg-bubble file-req-bubble">
                <div class="file-req-header">
                    <div class="file-req-icon"><i class="fas fa-file-lock"></i></div>
                    <span class="file-req-label">FILE REQUEST</span>
                </div>
                <div class="file-req-name">${escHtml(m.file_name)}</div>
                <div class="file-req-reason"><strong>Reason:</strong> ${escHtml(m.message)}</div>
                <span class="req-status-pill ${s.cls}">
                    <i class="fas ${s.icon}"></i> ${s.label}
                </span>
                ${actionBtns}
            </div>
            <div class="msg-time">${escHtml(m.time_label)}</div>
        </div>
    </div>`;
        }

        function scrollToPendingRequest() {
            const box = document.getElementById('chatMessages');
            const first = box.querySelector('.req-status-pill.pending');
            if (first) first.closest('.msg-row')?.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        /* ════════════════════════════════════════════
           FILE REQUEST APPROVAL
        ════════════════════════════════════════════ */
        async function processFileRequest(requestId, action) {
            const fd = new FormData();
            fd.append('action', 'process_file_request');
            fd.append('request_id', requestId);
            fd.append('decision', action);

            const res = await fetch(FILE_REQ_API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                toast(action === 'approve' ?
                    'File request approved. Student notified.' :
                    'File request rejected. Student notified.', 'success');
                await loadMessages();
            } else {
                toast(data.message || 'Action failed.', 'error');
            }
        }

        /* ════════════════════════════════════════════
           SEND MESSAGE
        ════════════════════════════════════════════ */
        async function sendMessage() {
            const input = document.getElementById('msgInput');
            const text = input.value.trim();
            if (!text || !activeConvId) return;

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            input.value = '';

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('conversation_id', activeConvId);
            fd.append('message', text);
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            btn.disabled = false;

            if (data.success) {
                await loadMessages();
                await refreshConvList();
            } else {
                input.value = text;
                toast('Failed to send message.', 'error');
            }
        }

        function markRead() {
            const fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('conversation_id', activeConvId);
            fetch(API, {
                method: 'POST',
                body: fd
            });
        }

        async function refreshConvList() {
            const fd = new FormData();
            fd.append('action', 'fetch_conversations');
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) return;
            allConvs = data.conversations;
            updateFilterCounts();
            renderConvs(applyFilter(allConvs));
        }

        /* ════════════════════════════════════════════
           HELPERS
        ════════════════════════════════════════════ */
        function escHtml(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function formatDate(d) {
            const today = new Date().toISOString().substring(0, 10);
            if (d === today) return 'Today';
            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);
            if (d === yest) return 'Yesterday';
            return new Date(d).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function toast(msg, type = 'success') {
            const zone = document.getElementById('toastZone');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${msg}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 320);
            }, 3500);
        }

        /* ════════════════════════════════════════════
           INIT
        ════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', loadConversations);
        setInterval(refreshConvList, 15000);
    </script>
</body>

</html>