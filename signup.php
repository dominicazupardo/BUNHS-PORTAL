<?php
// ─────────────────────────────────────────────────────────────
//  signup.php  –  OTP Registration Handler
//  Supports: ANY valid email (Gmail, Yahoo, Outlook, school, etc.)
//            ANY valid phone number (PH local or international)
//  OTP delivery: PHPMailer SMTP (email) | Semaphore SMS (phone)
// ─────────────────────────────────────────────────────────────

// PHPMailer namespace imports — MUST be at file scope (not inside functions)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ── Secure session ────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);   // Set to 1 on HTTPS production
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// ── Database ──────────────────────────────────
include 'db_connection.php';

// ──────────────────────────────────────────────
//  CONFIGURATION — fill in your credentials
// ──────────────────────────────────────────────

// Your sending Gmail account + App Password
// Get App Password: myaccount.google.com → Security → App Passwords
define('SMTP_FROM',      'bunhs.deped@gmail.com');       // ← your Gmail
define('SMTP_PASS',      'msqncrybbxlxhmbn');   // ← Gmail App Password (no spaces)
define('SMTP_FROM_NAME', 'Buyoan National High School');

// Semaphore SMS (for phone OTP) — https://semaphore.co
define('SEMAPHORE_KEY',    'YOUR_SEMAPHORE_API_KEY');   // ← Semaphore API key
define('SEMAPHORE_SENDER', 'BUNHS');                    // ← approved sender name

// OTP settings
define('OTP_EXPIRY_SECONDS',  300);  // 5 minutes
define('MAX_OTP_ATTEMPTS',      5);  // max wrong guesses before lockout
define('MAX_RESEND_ATTEMPTS',   3);  // max resend clicks

// ──────────────────────────────────────────────
//  SMTP ROUTING TABLE
//  Maps email domain → [host, port, encryption]
//  Allows any domain to receive OTP emails via
//  the school's single Gmail sending account.
// ──────────────────────────────────────────────
function getSmtpConfig()
{
    // We always SEND from the school Gmail regardless of recipient domain.
    // The recipient can be any email address — Gmail, Yahoo, Outlook, school, etc.
    return [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'secure'    => 'tls',   // STARTTLS on port 587
        'username'  => SMTP_FROM,
        'password'  => SMTP_PASS,
    ];
}

// ──────────────────────────────────────────────
//  HELPERS
// ──────────────────────────────────────────────

function sanitizeInput($v)
{
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function generateOTP()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function isValidEmail($email)
{
    // Accept ANY valid email format — no domain restriction
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone)
{
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone); // strip spaces/dashes/parens
    // Philippine local:   09xxxxxxxxx  (11 digits starting with 09)
    // Philippine intl:    +63xxxxxxxxx or 63xxxxxxxxx
    // International:      +[country][number], 10–15 digits total
    return (bool) preg_match('/^(\+?63|0)9\d{9}$|^\+?[1-9]\d{9,14}$/', $clean);
}

function normalizePhone($phone)
{
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    // Convert 09xxxxxxxxx → 639xxxxxxxxx for Semaphore
    if (preg_match('/^09\d{9}$/', $clean)) {
        return '63' . substr($clean, 1);
    }
    // Strip leading + for Semaphore
    return ltrim($clean, '+');
}

function logSignupAttempt($username, $contact, $success)
{
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    $entry = sprintf(
        "[%s] Username: %s | Contact: %s | Success: %s\n",
        date('Y-m-d H:i:s'),
        htmlspecialchars($username),
        htmlspecialchars($contact),
        $success ? 'Yes' : 'No'
    );
    file_put_contents('logs/signup_attempts.log', $entry, FILE_APPEND | LOCK_EX);
}

// ──────────────────────────────────────────────
//  EMAIL OTP via PHPMailer
//  Sends from the school Gmail to ANY recipient
// ──────────────────────────────────────────────
function sendEmailOTP($to_email, $otp)
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('[BUNHS] PHPMailer not found. Run: composer require phpmailer/phpmailer');
        return ['ok' => false, 'error' => 'Mail library not installed on server.'];
    }
    require_once $autoload;

    $cfg  = getSmtpConfig();
    $mail = new PHPMailer(true);

    try {
        // ── Transport ──────────────────────────
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];
        $mail->Timeout    = 20;

        // Log SMTP conversation to PHP error log (helpful for debugging)
        $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) {
            error_log('[PHPMailer] ' . trim($str));
        };

        // ── Message ────────────────────────────
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = 'Buyoan National High School — Verification Code';
        $mail->Body    = '
        <div style="font-family:Arial,sans-serif;max-width:500px;margin:auto;
                    padding:32px;border:1px solid #e0e0e0;border-radius:14px;
                    background:#ffffff;">
          <div style="text-align:center;margin-bottom:20px;">
            <img src="https://buyoannhs.edu.ph/assets/img/logo.jpg"
                 alt="BUNHS" style="height:60px;">
          </div>
          <h2 style="color:#1a3a2a;text-align:center;margin:0 0 8px;">
            Verification Code
          </h2>
          <p style="color:#555;text-align:center;margin:0 0 24px;font-size:14px;">
            Use the code below to complete your registration at<br>
            <strong>Buyoan National High School</strong>.
          </p>
          <div style="background:#f0f7f4;border:2px solid #52b788;
                      border-radius:10px;padding:22px;text-align:center;
                      letter-spacing:12px;font-size:38px;font-weight:bold;
                      color:#1a3a2a;margin-bottom:24px;">' . $otp . '</div>
          <p style="color:#888;font-size:13px;text-align:center;margin:0 0 8px;">
            This code expires in <strong>5 minutes</strong>.
          </p>
          <p style="color:#aaa;font-size:12px;text-align:center;margin:0;">
            If you did not request this, please ignore this email.
          </p>
          <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
          <p style="color:#bbb;font-size:11px;text-align:center;margin:0;">
            Buyoan National High School &bull; Buyoan, Legazpi City, Albay
          </p>
        </div>';
        $mail->AltBody = "Your BUNHS verification code is: $otp\n\nExpires in 5 minutes. Do not share this code.";

        $mail->send();
        return ['ok' => true, 'error' => ''];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo;
        error_log('[BUNHS] PHPMailer send failed: ' . $err);
        return ['ok' => false, 'error' => $err];
    }
}

// ──────────────────────────────────────────────
//  SMS OTP via Semaphore
// ──────────────────────────────────────────────
function sendSmsOTP($phone_number, $otp)
{
    $phone   = normalizePhone($phone_number);
    $message = "Your BUNHS verification code is: $otp\nExpires in 5 minutes. Do not share.";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'apikey'     => SEMAPHORE_KEY,
            'number'     => $phone,
            'message'    => $message,
            'sendername' => SEMAPHORE_SENDER,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $decoded = json_decode($response, true);
        if (!empty($decoded) && isset($decoded[0]['message_id'])) {
            return ['ok' => true, 'error' => ''];
        }
        $err = 'Semaphore rejected the request: ' . $response;
        error_log('[BUNHS] ' . $err);
        return ['ok' => false, 'error' => $err];
    }
    $err = "Semaphore HTTP $httpCode: $response";
    error_log('[BUNHS] ' . $err);
    return ['ok' => false, 'error' => $err];
}

// ── Unified sender ────────────────────────────
function sendOTP($session_data, $otp)
{
    if ($session_data['contact_method'] === 'email') {
        return sendEmailOTP($session_data['email'], $otp);
    }
    return sendSmsOTP($session_data['phone'], $otp);
}

// ──────────────────────────────────────────────
//  JSON error helper
// ──────────────────────────────────────────────
function jsonError($msg)
{
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function jsonOk($msg)
{
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $msg]);
    exit;
}

// ──────────────────────────────────────────────
//  MAIN — only handles POST (AJAX)
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Not an AJAX call — just return nothing (page is index.php)
    exit;
}

$action = trim($_POST['action'] ?? 'signup');

// ════════════════════════════════════════════
//  ACTION: signup  — validate → send OTP
// ════════════════════════════════════════════
if ($action === 'signup') {

    $firstName      = sanitizeInput($_POST['firstName']      ?? '');
    $lastName       = sanitizeInput($_POST['lastName']       ?? '');
    $contact_method = in_array($_POST['contact_method'] ?? '', ['email', 'phone'])
        ? $_POST['contact_method'] : 'email';
    $email          = sanitizeInput($_POST['email']          ?? '');
    $phone          = sanitizeInput($_POST['phone']          ?? '');
    $username       = sanitizeInput($_POST['username']       ?? '');
    $password       = $_POST['password']                     ?? '';
    $confirmPassword = $_POST['confirmPassword']              ?? '';
    $terms          = isset($_POST['terms']);

    // ── Validate ──────────────────────────────
    if (empty($firstName) || empty($lastName) || empty($username) || empty($password) || empty($confirmPassword)) {
        jsonError('Please fill in all required fields.');
    }
    if (!preg_match('/^[A-Za-z\s]+$/', $firstName) || !preg_match('/^[A-Za-z\s]+$/', $lastName)) {
        jsonError('Names can only contain letters and spaces.');
    }
    if ($contact_method === 'email') {
        if (empty($email) || !isValidEmail($email)) {
            jsonError('Please enter a valid email address (any provider accepted).');
        }
    } else {
        if (empty($phone) || !isValidPhone($phone)) {
            jsonError('Please enter a valid phone number (e.g. 09xxxxxxxxx or +639xxxxxxxxx).');
        }
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        jsonError('Username must be 3–50 characters.');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        jsonError('Username may only contain letters, numbers, and underscores.');
    }
    if (strlen($password) < 8) {
        jsonError('Password must be at least 8 characters.');
    }
    if ($password !== $confirmPassword) {
        jsonError('Passwords do not match.');
    }
    if (!$terms) {
        jsonError('You must agree to the Terms of Service and Privacy Policy.');
    }

    // ── Check uniqueness ──────────────────────
    $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        jsonError('Username already taken. Please choose another.');
    }
    $stmt->close();

    if ($contact_method === 'email') {
        $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            jsonError('this email is already used');
        }
        $stmt->close();
    } else {
        $normPhone = normalizePhone($phone);
        $stmt = $conn->prepare("SELECT id FROM `sub_admin` WHERE phone = ?");
        $stmt->bind_param("s", $normPhone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            jsonError('This phone number is already registered.');
        }
        $stmt->close();
    }

    // ── Generate OTP & store session ──────────
    $otp = generateOTP();
    $_SESSION['signup_data'] = [
        'firstName'      => $firstName,
        'lastName'       => $lastName,
        'contact_method' => $contact_method,
        'email'          => $email,
        'phone'          => $phone,
        'username'       => $username,
        'password'       => password_hash($password, PASSWORD_DEFAULT),
        'otp'            => $otp,
        'otp_expires'    => time() + OTP_EXPIRY_SECONDS,
        'otp_attempts'   => 0,
        'resend_count'   => 0,
    ];

    // ── Send OTP ──────────────────────────────
    $result = sendOTP($_SESSION['signup_data'], $otp);

    if ($result['ok']) {
        logSignupAttempt($username, $email ?: $phone, true);
        jsonOk('Verification code sent.');
    } else {
        unset($_SESSION['signup_data']);
        logSignupAttempt($username, $email ?: $phone, false);
        // Return the real PHPMailer/Semaphore error so you can debug it
        jsonError('Failed to send verification code. Details: ' . $result['error']);
    }
}

// ════════════════════════════════════════════
//  ACTION: verify_otp  — check code → create account
// ════════════════════════════════════════════
if ($action === 'verify_otp') {

    $otp_input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['signup_data'])) {
        jsonError('Session expired. Please start registration again.');
    }

    $sd = &$_SESSION['signup_data'];

    if ($sd['otp_attempts'] >= MAX_OTP_ATTEMPTS) {
        unset($_SESSION['signup_data']);
        jsonError('Too many failed attempts. Please register again.');
    }
    if (time() > $sd['otp_expires']) {
        unset($_SESSION['signup_data']);
        jsonError('Verification code expired. Please register again.');
    }
    if (empty($otp_input) || !preg_match('/^\d{6}$/', $otp_input)) {
        jsonError('Please enter the 6-digit verification code.');
    }
    if ($otp_input !== $sd['otp']) {
        $sd['otp_attempts']++;
        $remaining = MAX_OTP_ATTEMPTS - $sd['otp_attempts'];
        jsonError("Invalid code. $remaining attempt(s) remaining.");
    }

    // ✅ OTP correct — insert account
    $full_name = $sd['firstName'] . ' ' . $sd['lastName'];
    $status    = 'pending';

    if ($sd['contact_method'] === 'email') {
        $stmt = $conn->prepare(
            "INSERT INTO `sub_admin` (username, password, email, full_name, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $sd['username'], $sd['password'], $sd['email'], $full_name, $status);
    } else {
        $normPhone = normalizePhone($sd['phone']);
        $stmt = $conn->prepare(
            "INSERT INTO `sub_admin` (username, password, phone, full_name, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $sd['username'], $sd['password'], $normPhone, $full_name, $status);
    }

    if ($stmt->execute()) {
        logSignupAttempt($sd['username'], $sd['email'] ?: $sd['phone'], true);
        unset($_SESSION['signup_data']);
        session_regenerate_id(true);
        $stmt->close();
        if (isset($conn)) $conn->close();
        jsonOk('Account created successfully! Please wait for admin approval.');
    } else {
        $err = $stmt->error;
        $stmt->close();
        error_log('[BUNHS] DB insert error: ' . $err);
        jsonError('Failed to create account. Please try again.');
    }
}

// ════════════════════════════════════════════
//  ACTION: resend_otp
// ════════════════════════════════════════════
if ($action === 'resend_otp') {

    if (empty($_SESSION['signup_data'])) {
        jsonError('Session expired. Please start registration again.');
    }

    $sd = &$_SESSION['signup_data'];

    if ($sd['resend_count'] >= MAX_RESEND_ATTEMPTS) {
        jsonError('Maximum resend limit reached. Please start registration again.');
    }

    $otp = generateOTP();
    $sd['otp']          = $otp;
    $sd['otp_expires']  = time() + OTP_EXPIRY_SECONDS;
    $sd['otp_attempts'] = 0;
    $sd['resend_count']++;

    $result    = sendOTP($sd, $otp);
    $remaining = MAX_RESEND_ATTEMPTS - $sd['resend_count'];

    if ($result['ok']) {
        jsonOk("New code sent. ($remaining resend(s) remaining.)");
    } else {
        jsonError('Failed to resend code. Details: ' . $result['error']);
    }
}

// Unknown action
jsonError('Invalid request.');
