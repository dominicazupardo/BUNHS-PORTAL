<?php

/**
 * Student API - Handles all student-related CRUD operations
 * Uses prepared statements for SQL security
 */

session_start();
include '../db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
 * Sanitize input
 */
function sanitizeInput($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and upload image file
 */
function uploadImage($file, $targetDir = "../assets/img/person/")
{
    $response = ['success' => false, 'path' => '', 'error' => ''];

    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $response['error'] = 'No file uploaded or upload error';
        return $response;
    }

    // Validate file size (max 2MB)
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxFileSize) {
        $response['error'] = 'File size exceeds 2MB limit';
        return $response;
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        $response['error'] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed';
        return $response;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid('student_') . '.' . strtolower($extension);
    $targetPath = $targetDir . $newFilename;

    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Move file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $response['success'] = true;
        $response['path'] = $targetPath;
    } else {
        $response['error'] = 'Failed to move uploaded file';
    }

    return $response;
}

/**
 * Get all students with optional filtering and pagination
 */
function getStudents($conn, $search = '', $grade = '', $gender = '', $page = 1, $perPage = 20)
{
    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }

    if (!empty($grade)) {
        $conditions[] = "grade_level = ?";
        $params[] = $grade;
        $types .= 's';
    }

    if (!empty($gender)) {
        $conditions[] = "gender = ?";
        $params[] = $gender;
        $types .= 's';
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM students {$whereClause}";
    $countStmt = $conn->prepare($countSql);

    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Calculate pagination
    $offset = ($page - 1) * $perPage;
    $totalPages = ceil($totalCount / $perPage);

    // Get students with LIMIT
    $sql = "SELECT * FROM students {$whereClause} ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    return [
        'students' => $students,
        'total' => $totalCount,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages
    ];
}

/**
 * Get single student by ID
 */
function getStudentById($conn, $studentId)
{
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    return $student;
}

/**
 * Add new student
 */
function addStudent($conn, $data)
{
    $sql = "INSERT INTO students (student_id, first_name, last_name, grade_level, gender, age, birth_date, profile_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Parse full name into first and last name
    $nameParts = explode(' ', trim($data['student_name']), 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

    // Set default birth date if not provided (calculate from age)
    $birthDate = date('Y-m-d', strtotime('-' . $data['age'] . ' years'));

    $stmt->bind_param(
        "sssssiss",
        $data['student_id'],
        $firstName,
        $lastName,
        $data['grade_section'],
        $data['gender'],
        $data['age'],
        $birthDate,
        $data['profile_image']
    );

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Student added successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to add student: ' . $error];
    }
}

/**
 * Update student
 */
function updateStudent($conn, $data)
{
    // Parse full name into first and last name
    $nameParts = explode(' ', trim($data['student_name']), 2);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

    if (!empty($data['profile_image'])) {
        $sql = "UPDATE students SET first_name = ?, last_name = ?, grade_level = ?, gender = ?, age = ?, profile_image = ? 
                WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiss", $firstName, $lastName, $data['grade_section'], $data['gender'], $data['age'], $data['profile_image'], $data['student_id']);
    } else {
        $sql = "UPDATE students SET first_name = ?, last_name = ?, grade_level = ?, gender = ?, age = ? 
                WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssis", $firstName, $lastName, $data['grade_section'], $data['gender'], $data['age'], $data['student_id']);
    }

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Student updated successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to update student: ' . $error];
    }
}

/**
 * Delete student
 */
function deleteStudent($conn, $studentId)
{
    // First get the student to check for profile image
    $student = getStudentById($conn, $studentId);

    if ($student && !empty($student['profile_image']) && file_exists($student['profile_image'])) {
        unlink($student['profile_image']);
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);

    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Student deleted successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to delete student: ' . $error];
    }
}

/**
 * Get dashboard statistics
 */
function getStatistics($conn)
{
    $stats = [
        'totalStudents' => 0,
        'totalMale' => 0,
        'totalFemale' => 0,
        'byGrade' => [],
        'byGender' => []
    ];

    // Total students
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $stats['totalStudents'] = $result->fetch_assoc()['count'];

    // By gender
    $result = $conn->query("SELECT gender, COUNT(*) as count FROM students GROUP BY gender");
    while ($row = $result->fetch_assoc()) {
        $stats['byGender'][] = $row;
        if ($row['gender'] === 'Male') {
            $stats['totalMale'] = $row['count'];
        } else {
            $stats['totalFemale'] = $row['count'];
        }
    }

    // By grade
    $result = $conn->query("SELECT grade_level, COUNT(*) as count FROM students WHERE grade_level != '' GROUP BY grade_level");
    while ($row = $result->fetch_assoc()) {
        $stats['byGrade'][] = $row;
    }

    return $stats;
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            $search = $_GET['search'] ?? '';
            $grade = $_GET['grade'] ?? '';
            $gender = $_GET['gender'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $result = getStudents($conn, $search, $grade, $gender, $page, $perPage);
            echo json_encode($result);
        } elseif ($action === 'get') {
            $studentId = $_GET['id'] ?? '';
            $student = getStudentById($conn, $studentId);
            echo json_encode($student);
        } elseif ($action === 'stats') {
            $stats = getStatistics($conn);
            echo json_encode($stats);
        } elseif ($action === 'csrf_token') {
            echo json_encode(['token' => generateCSRFToken()]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            // Handle form submission
            $input = $_POST;
        }

        // Validate CSRF token
        $csrfToken = $input['csrf_token'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            break;
        }

        $action = $input['action'] ?? '';

        if ($action === 'add') {
            // Handle image upload
            $profileImage = '';
            if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadImage($_FILES['student_image']);
                if ($uploadResult['success']) {
                    $profileImage = $uploadResult['path'];
                }
            }

            $input['profile_image'] = $profileImage;
            $result = addStudent($conn, $input);
            echo json_encode($result);
        } elseif ($action === 'edit') {
            // Handle image upload
            $profileImage = '';
            if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadImage($_FILES['student_image']);
                if ($uploadResult['success']) {
                    $profileImage = $uploadResult['path'];
                }
            }

            $input['profile_image'] = $profileImage;
            $result = updateStudent($conn, $input);
            echo json_encode($result);
        } elseif ($action === 'delete') {
            $studentId = $input['student_id'] ?? '';
            $result = deleteStudent($conn, $studentId);
            echo json_encode($result);
        } elseif ($action === 'csrf_token') {
            echo json_encode(['token' => generateCSRFToken()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
