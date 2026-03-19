<?php
// ─────────────────────────────────────────────────────────────
//  login_otp.php  –  Login OTP Handler (AJAX endpoint)
// ─────────────────────────────────────────────────────────────

// Suppress warnings/notices so they never corrupt the JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Send JSON header first — before any include that might output something
header('Content-Type: application/json');

// Secure session configuration (must match index.php)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 on HTTPS/production only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// Include DB connection
include 'db_connection.php';

// ── Load OTP delivery helpers from signup.php ────────────────
// We reuse sendEmailOTP() and sendSmsOTP() defined there.
// To avoid re-including the whole file we inline lightweight versions here.

define('SMTP_HOST_L',     'smtp.gmail.com');
define('SMTP_PORT_L',     587);
define('SMTP_USER_L',     'bunhs.deped@gmail.com');       // ← same as signup.php
define('SMTP_PASS_L',     'msqncrybbxlxhmbn');    // ← same as signup.php
define('SMTP_FROM_L',     'bunhs.deped@gmail.com');
define('SMTP_FROM_NAME_L', 'Buyoan National High School');

define('SEMAPHORE_KEY_L',    'YOUR_SEMAPHORE_API_KEY');  // ← same as signup.php
define('SEMAPHORE_SENDER_L', 'BUNHS');

define('LOGIN_OTP_EXPIRY',       300);   // 5 minutes
define('LOGIN_MAX_OTP_ATTEMPTS', 5);
define('LOGIN_MAX_RESEND',       3);
define('MAX_LOGIN_ATTEMPTS',     5);
define('LOCKOUT_TIME',           900);   // 15 min

// ──────────────────────────────────────────────
//  HELPERS
// ──────────────────────────────────────────────

function sanitize($v)
{
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}
function genOTP()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function getIP()
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) return $_SERVER[$k];
    }
    return '0.0.0.0';
}

function isRateLimited($ip)
{
    $attempts = $_SESSION['login_attempts'][$ip] ?? [];
    $recent = array_filter($attempts, fn($t) => $t > time() - LOCKOUT_TIME);
    return count($recent) >= MAX_LOGIN_ATTEMPTS;
}

function recordAttempt($ip)
{
    $_SESSION['login_attempts'][$ip][] = time();
}

function maskEmail($email)
{
    [$user, $domain] = explode('@', $email, 2);
    return substr($user, 0, 2) . str_repeat('*', max(1, strlen($user) - 2)) . '@' . $domain;
}

function maskPhone($phone)
{
    $clean = preg_replace('/\D/', '', $phone);
    return substr($clean, 0, 4) . str_repeat('*', max(0, strlen($clean) - 7)) . substr($clean, -3);
}

function sendLoginEmail($to, $otp)
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return false;

    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST_L;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER_L;
        $mail->Password   = SMTP_PASS_L;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT_L;
        $mail->setFrom(SMTP_FROM_L, SMTP_FROM_NAME_L);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Buyoan National High School Login Code';
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:30px;
                        border:1px solid #e0e0e0;border-radius:12px;">
                <h2 style="color:#1a73e8;text-align:center;">Login Verification Code</h2>
                <p style="color:#555;text-align:center;">Use this code to sign in to your account.</p>
                <div style="background:#f1f3f4;border-radius:8px;padding:20px;text-align:center;
                            letter-spacing:10px;font-size:36px;font-weight:bold;color:#202124;
                            margin:24px 0;">' . $otp . '</div>
                <p style="color:#888;font-size:13px;text-align:center;">
                    Expires in <strong>5 minutes</strong>. Never share this code.
                </p>
            </div>';
        $mail->AltBody = "Your BUNHS login code: $otp (expires in 5 minutes)";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Login mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendLoginSMS($phone, $otp)
{
    $clean = preg_replace('/\D/', '', $phone);
    if (substr($clean, 0, 1) === '0') $clean = '63' . substr($clean, 1);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'apikey'     => SEMAPHORE_KEY_L,
            'number'     => $clean,
            'message'    => "Your BUNHS login code is: $otp\nExpires in 5 minutes.",
            'sendername' => SEMAPHORE_SENDER_L,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is a no-op since PHP 8.0 and deprecated in PHP 8.4 — omitted
    return $code === 200;
}

function sendLoginOTP($user_data, $otp)
{
    if (!empty($user_data['email'])) return sendLoginEmail($user_data['email'], $otp);
    if (!empty($user_data['phone'])) return sendLoginSMS($user_data['phone'], $otp);
    return false;
}

function logAttempt($username, $success)
{
    if (!is_dir('logs')) mkdir('logs', 0755, true);
    file_put_contents(
        'logs/login_attempts.log',
        sprintf(
            "[%s] IP: %s | User: %s | Success: %s\n",
            date('Y-m-d H:i:s'),
            getIP(),
            htmlspecialchars($username),
            $success ? 'Yes' : 'No'
        ),
        FILE_APPEND | LOCK_EX
    );
}

// ──────────────────────────────────────────────
//  MAIN ROUTER
// ──────────────────────────────────────────────

$action = $_POST['action'] ?? '';

if ($action === 'login_verify_credentials') {
    // ── Step 1: Check username + password ────────────────────
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password']           ?? '';
    $ip       = getIP();

    if (isRateLimited($ip)) {
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Try again in 15 minutes.']);
        exit;
    }

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter both username and password.']);
        exit;
    }

    $user_data  = null;
    $user_type  = '';

    // Check admin table
    $stmt = $conn->prepare("SELECT id, password, personal_email AS email, personal_mobile AS phone FROM `admin` WHERE username = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $user_data = $row;
            $user_type = 'admin';
        }
        $stmt->close();
    }

    // Check sub_admin table
    if (!$user_data) {
        $stmt = $conn->prepare(
            "SELECT id, password, email, phone FROM `sub_admin` WHERE username = ? AND status = 'approved' LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $user_data = $row;
                $user_type = 'sub-admin';
            }
            $stmt->close();
        }
    }

    // Check students table — email or student_id, must have a password set
    if (!$user_data) {
        $stmt = $conn->prepare(
            "SELECT id, password, email,
                    COALESCE(phone, phone_number, '') AS phone,
                    first_name, last_name, grade_level
             FROM students
             WHERE (email = ? OR student_id = ?)
               AND password IS NOT NULL
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) { $user_data = $row; $user_type = 'student'; }
            $stmt->close();
        }
    }

    // ── Password check — handles bcrypt AND plain text (legacy accounts) ──
    $password_ok = false;
    if ($user_data) {
        $stored = $user_data['password'] ?? '';
        if (strlen($stored) >= 60 && str_starts_with($stored, '$2')) {
            // Proper bcrypt hash
            $password_ok = password_verify($password, $stored);
        } else {
            // Plain text (stored before hashing was implemented) — compare directly
            // then immediately upgrade to bcrypt so next login uses the hash
            $password_ok = ($password === $stored);
            if ($password_ok && $user_type === 'student') {
                $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $up = $conn->prepare("UPDATE students SET password = ? WHERE id = ? LIMIT 1");
                if ($up) {
                    $up->bind_param('si', $new_hash, $user_data['id']);
                    $up->execute();
                    $up->close();
                }
            }
        }
    }

    if ($password_ok) {
        // Generate OTP
        $otp = genOTP();
        $_SESSION['login_otp_data'] = [
            'otp'          => $otp,
            'otp_expires'  => time() + LOGIN_OTP_EXPIRY,
            'otp_attempts' => 0,
            'resend_count' => 0,
            'user_id'      => $user_data['id'],
            'username'     => $username,
            'user_type'    => $user_type,
            'email'        => $user_data['email'] ?? '',
            'phone'        => $user_data['phone'] ?? '',
            'student_name' => isset($user_data['first_name'])
                                ? trim($user_data['first_name'] . ' ' . $user_data['last_name'])
                                : '',
            'grade_level'  => $user_data['grade_level'] ?? '',
        ];

        $sent = sendLoginOTP($user_data, $otp);
        if (!$sent) {
            // ── DEV MODE: PHPMailer/SMS not configured yet ────────────
            // OTP is returned in the JSON response for local testing.
            // ⚠️ REMOVE the dev_otp line before going to production!
            error_log("=== DEV LOGIN OTP for [{$username}]: {$otp} ===");
            $sent = true;
            // ─────────────────────────────────────────────────────────
            // unset($_SESSION['login_otp_data']);
            // logAttempt($username, false);
            // echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please contact support.']);
            // exit;
        }

        // Build masked contact for UI
        $masked = !empty($user_data['email'])
            ? maskEmail($user_data['email'])
            : maskPhone($user_data['phone']);

        logAttempt($username, true);
        unset($_SESSION['login_attempts'][$ip]);

        // ⚠️ DEV ONLY — remove 'dev_otp' before going to production!
        echo json_encode([
            'success'        => true,
            'masked_contact' => $masked,
            'dev_otp'        => $otp,  // ⚠️ REMOVE IN PRODUCTION
        ]);
    } else {
        recordAttempt($ip);
        logAttempt($username, false);
        sleep(1);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
    exit;
}

if ($action === 'login_verify_otp') {
    // ── Step 2: Verify login OTP ──────────────────────────────
    $otp_input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['login_otp_data'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }

    $d = &$_SESSION['login_otp_data'];

    if ($d['otp_attempts'] >= LOGIN_MAX_OTP_ATTEMPTS) {
        unset($_SESSION['login_otp_data']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please log in again.']);
        exit;
    }

    if (time() > $d['otp_expires']) {
        unset($_SESSION['login_otp_data']);
        echo json_encode(['success' => false, 'message' => 'Code expired. Please log in again.']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $otp_input) || $otp_input !== $d['otp']) {
        $d['otp_attempts']++;
        $rem = LOGIN_MAX_OTP_ATTEMPTS - $d['otp_attempts'];
        echo json_encode(['success' => false, 'message' => "Invalid code. $rem attempt(s) remaining."]);
        exit;
    }

    // ✅ OTP correct — create authenticated session
    session_regenerate_id(true);

    if ($d['user_type'] === 'student') {
        // Student session — matches what Dashboard.php expects
        $_SESSION['student_id']   = $d['user_id'];
        $_SESSION['student_name'] = $d['student_name'];
        $_SESSION['grade_level']  = $d['grade_level'];
        $_SESSION['user_type']    = 'student';
        $_SESSION['login_method'] = !empty($d['email']) ? 'email' : 'phone';
    } else {
        // Admin / sub-admin session
        $_SESSION['user_id']   = $d['user_id'];
        $_SESSION['username']  = $d['username'];
        $_SESSION['user_type'] = $d['user_type'];
    }

    $_SESSION['login_time'] = time();
    unset($_SESSION['login_otp_data']);

    logAttempt($d['username'], true);
    echo json_encode(['success' => true, 'user_type' => $d['user_type']]);
    exit;
}

if ($action === 'login_resend_otp') {
    // ── Resend login OTP ──────────────────────────────────────
    if (empty($_SESSION['login_otp_data'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    $d = &$_SESSION['login_otp_data'];

    if ($d['resend_count'] >= LOGIN_MAX_RESEND) {
        echo json_encode(['success' => false, 'message' => 'Maximum resend limit reached. Please log in again.']);
        exit;
    }

    $otp = genOTP();
    $d['otp']          = $otp;
    $d['otp_expires']  = time() + LOGIN_OTP_EXPIRY;
    $d['otp_attempts'] = 0;
    $d['resend_count']++;

    $sent = sendLoginOTP($d, $otp);
    $rem  = LOGIN_MAX_RESEND - $d['resend_count'];

    if ($sent) {
        echo json_encode(['success' => true, 'message' => "New code sent. ($rem resend(s) remaining.)"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to resend code. Please try again.']);
    }
    exit;
}

// ── DEBUG — remove after testing ────────────────────────────
// Visit: POST login_otp.php action=debug_student&email=YOUR_EMAIL
if ($action === 'debug_student') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) { echo json_encode(['error' => 'No email provided']); exit; }

    // Check if password column exists
    $col_check = $conn->query("SHOW COLUMNS FROM students LIKE 'password'");
    $password_col_exists = ($col_check && $col_check->num_rows > 0);

    // Find the student row
    $stmt = $conn->prepare("SELECT id, email, student_id, first_name, last_name, password FROM students WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'password_column_exists' => $password_col_exists,
        'student_found'          => $row ? true : false,
        'student_id_field'       => $row['id'] ?? null,
        'student_id_col'         => $row['student_id'] ?? null,
        'name'                   => $row ? ($row['first_name'] . ' ' . $row['last_name']) : null,
        'has_password'           => $row ? (!empty($row['password'])) : null,
        'password_length'        => $row ? strlen($row['password'] ?? '') : null,
    ]);
    exit;
}

// Unknown action
echo json_encode(['success' => false, 'message' => 'Invalid request.']);