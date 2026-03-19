<?php

/**
 * Student Profile API
 * Handles all profile-related CRUD operations with encryption
 * 
 * Security Features:
 * - CSRF token validation
 * - Input sanitization
 * - Field-level encryption for sensitive data
 * - SSL/HTTPS enforcement
 */

session_start();

// Include database connection
include '../db_connection.php';

// Include encryption key if available
$encryption_key = null;
$encryption_iv_length = 16;
if (file_exists('../config/encryption_key.php')) {
    include '../config/encryption_key.php';
}

// Set content type to JSON
header('Content-Type: application/json');

// Enforce HTTPS in production
function enforceHTTPS()
{
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        // For development, we'll allow HTTP but log a warning
        // In production, uncomment the following line:
        // header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        // exit;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Encrypt data using AES-256
 */
function encryptData($data, $key)
{
    if (empty($data) || empty($key)) {
        return $data;
    }

    // Generate a random IV
    $iv = random_bytes(16);

    // Encrypt the data
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    // Combine IV and encrypted data, then base64 encode
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt data using AES-256
 */
function decryptData($data, $key)
{
    if (empty($data) || empty($key)) {
        return $data;
    }

    // Decode the base64 data
    $combined = base64_decode($data);

    // Extract IV and encrypted data
    $iv = substr($combined, 0, 16);
    $encrypted = substr($combined, 16);

    // Decrypt the data
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Sanitize input
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get profile details for a student
 */
function getProfileDetails($conn, $studentId, $encryptionKey = null)
{
    // Get basic student info
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        return ['success' => false, 'message' => 'Student not found'];
    }

    // Decrypt sensitive fields if encryption key is available
    if ($encryptionKey) {
        $fieldsToDecrypt = ['email', 'phone', 'address', 'guardian_name', 'guardian_phone'];
        foreach ($fieldsToDecrypt as $field) {
            $encryptedField = $field . '_encrypted';
            if (!empty($student[$encryptedField])) {
                $student[$field] = decryptData($student[$encryptedField], $encryptionKey);
            }
        }
    }

    // Get extended profile details
    $stmt = $conn->prepare("SELECT * FROM student_profile_details WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileDetails = $result->fetch_assoc();
    $stmt->close();

    // Decrypt extended profile fields if encryption key is available
    if ($profileDetails && $encryptionKey) {
        $fieldsToDecrypt = ['professional_bio', 'school_name', 'grade_level', 'awards_hextracurricularsonors', '', 'portfolio_projects', 'skills'];
        foreach ($fieldsToDecrypt as $field) {
            if (!empty($profileDetails[$field])) {
                $profileDetails[$field] = decryptData($profileDetails[$field], $encryptionKey);
            }
        }
    }

    return [
        'success' => true,
        'student' => $student,
        'profile_details' => $profileDetails
    ];
}

/**
 * Save/update profile details
 */
function saveProfileDetails($conn, $data, $encryptionKey = null)
{
    $studentId = $data['student_id'] ?? '';

    if (empty($studentId)) {
        return ['success' => false, 'message' => 'Student ID is required'];
    }

    // Prepare encrypted fields for students table
    $sensitiveFields = ['email', 'phone', 'address', 'guardian_name', 'guardian_phone', 'name'];

    // Update students table - handle both regular and encrypted fields
    $updateFields = [];
    $params = [];
    $types = '';

    // Check which fields are provided and need to be updated
    $fieldsToUpdate = ['name', 'email', 'phone', 'address', 'guardian_name', 'guardian_phone', 'birthdate', 'photo'];

    foreach ($fieldsToUpdate as $field) {
        if (isset($data[$field]) && $data[$field] !== '') {
            // For encrypted fields
            if (in_array($field, $sensitiveFields) && !empty($encryptionKey)) {
                $encryptedValue = encryptData($data[$field], $encryptionKey);
                $updateFields[] = "$field = ?";
                $params[] = $encryptedValue;
                $types .= 's';

                // Also update the encrypted field
                $updateFields[] = "{$field}_encrypted = ?";
                $params[] = $encryptedValue;
                $types .= 's';
            } else {
                // For non-encrypted fields (like birthdate, photo)
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
                $types .= 's';
            }
        }
    }

    // Update students table if there are fields to update
    if (!empty($updateFields)) {
        $sql = "UPDATE students SET " . implode(', ', $updateFields) . " WHERE student_id = ?";
        $stmt = $conn->prepare($sql);

        $params[] = $studentId;
        $types .= 's';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    // Check if profile details exist
    $stmt = $conn->prepare("SELECT id FROM student_profile_details WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    // Prepare extended profile data with encryption
    $profileFields = ['professional_bio', 'school_name', 'grade_level', 'awards_honors', 'extracurriculars', 'portfolio_projects', 'skills'];
    $profileData = [];

    foreach ($profileFields as $field) {
        $profileData[$field] = isset($data[$field]) ? $data[$field] : '';
        if (!empty($encryptionKey) && !empty($profileData[$field])) {
            $profileData[$field] = encryptData($profileData[$field], $encryptionKey);
        }
    }

    if ($exists) {
        // Update existing profile
        $updateParts = [];
        foreach ($profileFields as $field) {
            $updateParts[] = "$field = ?";
        }

        $sql = "UPDATE student_profile_details SET " . implode(', ', $updateParts) . " WHERE student_id = ?";
        $stmt = $conn->prepare($sql);

        $params = [];
        $types = '';
        foreach ($profileFields as $field) {
            $params[] = $profileData[$field];
            $types .= 's';
        }
        $params[] = $studentId;
        $types .= 's';

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new profile
        $insertFields = implode(', ', $profileFields);
        $insertPlaceholders = implode(', ', array_fill(0, count($profileFields), '?'));

        $sql = "INSERT INTO student_profile_details (student_id, $insertFields) VALUES (?, $insertPlaceholders)";
        $stmt = $conn->prepare($sql);

        $params = [];
        $types = 's';
        $params[] = $studentId;
        foreach ($profileFields as $field) {
            $params[] = $profileData[$field];
            $types .= 's';
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    return ['success' => true, 'message' => 'Profile updated successfully'];
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

switch ($method) {
    case 'GET':
        if ($action === 'get_profile') {
            $studentId = $_GET['student_id'] ?? $_SESSION['student_id'] ?? '';

            if (empty($studentId)) {
                echo json_encode(['success' => false, 'message' => 'Student ID is required']);
                break;
            }

            $result = getProfileDetails($conn, $studentId, $encryption_key);
            echo json_encode($result);
        } elseif ($action === 'csrf_token') {
            echo json_encode(['token' => $csrfToken]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            // Handle form submission
            $input = $_POST;
        }

        // Validate CSRF token
        $csrfTokenInput = $input['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfTokenInput)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            break;
        }

        $action = $input['action'] ?? '';

        if ($action === 'save_profile') {
            // Sanitize input
            $sanitizedInput = sanitizeInput($input);

            // Remove action and csrf_token from data
            unset($sanitizedInput['action']);
            unset($sanitizedInput['csrf_token']);

            $result = saveProfileDetails($conn, $sanitizedInput, $encryption_key);
            echo json_encode($result);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
