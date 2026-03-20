<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  login.php — CACHED VERSION
//  Admin-only login page.  Replace the existing login.php with this file.
//  Only the PHP handler block changes; the HTML below it is untouched.
// ═══════════════════════════════════════════════════════════════════════════════

session_start();

// Redirect already-authenticated admins
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_account/admin_dashboard.php');
    exit;
}

// Load caching layer — must come BEFORE any credential lookup
require_once __DIR__ . '/cache_helper.php';

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include __DIR__ . '/db_connection.php';   // provides $conn (mysqli)

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $login_error = 'Please enter both username and password.';
    } else {

        $admin     = null;
        $cache_key = "admin:{$username}";

        // ── 1. Try cache first ────────────────────────────────────────────────
        $cached = cache_get($cache_key);

        if ($cached !== false) {
            // CACHE HIT — credential row already in shared memory
            $admin = $cached;
        } else {
            // CACHE MISS — hit the database and store the result
            $stmt = $conn->prepare(
                "SELECT id, password FROM `admin` WHERE username = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                $row    = $result->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $admin = $row;
                    // Store in APCu so the next login for this admin skips DB
                    cache_set($cache_key, $admin, CACHE_TTL_CREDENTIALS);
                }
            }
        }

        // ── 2. Verify password — ALWAYS, even on cache hit ───────────────────
        // The cache only eliminates the SELECT.  The bcrypt check always runs.
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: admin_account/admin_dashboard.php');
            exit;
        } else {
            $login_error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Login - Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/img/logo.jpg" alt="School Logo">
                <h2>Admin Login</h2>
                <p>Please sign in to access the school management system</p>
            </div>

            <form id="loginForm" method="POST" action="login.php">

                <?php if ($login_error): ?>
                    <div class="alert alert-danger" style="
                        background:#fdf1f1;color:#b94040;border:1px solid #f0d5d5;
                        border-radius:8px;padding:11px 15px;margin-bottom:16px;
                        font-size:13.5px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="error-message" id="usernameError">Please enter a valid username</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="error-message" id="passwordError">Please enter your password</div>
                </div>

                <button type="submit" class="btn btn-login">Sign In</button>
            </form>

            <div class="forgot-password">
                <a href="#">Forgot your password?</a>
            </div>

            <div class="signup-link">
                <p>Don't have an account? <a href="signup.html">Sign up here</a></p>
            </div>
        </div>
    </div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            let isValid = true;

            document.getElementById('usernameError').style.display = 'none';
            document.getElementById('passwordError').style.display = 'none';

            if (!username) {
                document.getElementById('usernameError').style.display = 'block';
                isValid = false;
            }
            if (!password) {
                document.getElementById('passwordError').style.display = 'block';
                isValid = false;
            }

            if (!isValid) e.preventDefault();
        });
    </script>
</body>

</html>