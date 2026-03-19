<?php

/**
 * settings_api.php — School Settings API
 * Stores/retrieves settings from MySQL (school_settings table).
 * Also keeps JSON file as secondary cache.
 *
 * BUG FIX: loadSettings() logic was inverted — fixed below.
 */

session_start();
require_once '../db_connection.php';

// JSON cache path (optional secondary storage)
$configPath = __DIR__ . '/config/settings.json';

/**
 * FIXED: original had inverted logic (!file_exists → read, file missing → [])
 * Correct: if file EXISTS → read & return; if NOT → return []
 */
function loadSettings(string $path): array
{
    if (file_exists($path)) {
        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function saveSettings(string $path, array $data): bool
{
    @mkdir(dirname($path), 0775, true);
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/** Load settings from DB (school_settings table) */
function loadDbSettings(mysqli $conn): array
{
    $out = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM school_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $out;
}

/** Save a single key→value to DB */
function saveDbSetting(mysqli $conn, string $key, string $value): bool
{
    $stmt = $conn->prepare(
        "INSERT INTO school_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($action) {
    // ── Load: merge DB + JSON cache ────────────────────────
    case 'load':
        $dbSettings   = loadDbSettings($conn);
        $fileSettings = loadSettings($configPath);
        echo json_encode(array_merge($fileSettings, $dbSettings));
        break;

    // ── Save: persist to both DB and JSON ─────────────────
    case 'save':
        $newData = $input['data'] ?? $input ?? [];
        unset($newData['action']); // strip action key if present

        $errors = [];
        foreach ($newData as $k => $v) {
            if (!saveDbSetting($conn, (string)$k, (string)$v)) {
                $errors[] = $k;
            }
        }

        // Also update JSON cache
        $existing = loadSettings($configPath);
        saveSettings($configPath, array_merge($existing, $newData));

        if (empty($errors)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save keys: ' . implode(', ', $errors)]);
        }
        break;

    // ── Reset: reload defaults (re-seed DB from JSON) ──────
    case 'reset':
        if (file_exists($configPath)) {
            $defaults = json_decode(file_get_contents($configPath), true);
            if (is_array($defaults)) {
                foreach ($defaults as $k => $v) {
                    saveDbSetting($conn, (string)$k, (string)$v);
                }
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Invalid JSON in config file']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No config file found to reset from']);
        }
        break;

    // ── Get single key ─────────────────────────────────────
    case 'get':
        $key = trim($_GET['key'] ?? '');
        if ($key === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing key parameter']);
            break;
        }
        $stmt = $conn->prepare("SELECT setting_value FROM school_settings WHERE setting_key=? LIMIT 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        echo json_encode(['key' => $key, 'value' => $row ? $row['setting_value'] : null]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
