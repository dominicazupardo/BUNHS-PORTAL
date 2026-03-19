<?php
// ─────────────────────────────────────────────────────────────
//  generate_password_hash.php
//  ONE-TIME USE — run this once in your browser to get the
//  bcrypt hash for your admin password, then DELETE this file.
//
//  Visit: http://localhost/BUNHS_School_System/generate_password_hash.php
// ─────────────────────────────────────────────────────────────

// ✏️  Change this to your desired admin password:
$plain_password = 'your_password_here';

$hash = password_hash($plain_password, PASSWORD_BCRYPT);

echo '<pre style="font-family:monospace;font-size:15px;padding:20px;">';
echo "Plain password : " . htmlspecialchars($plain_password) . "\n";
echo "Bcrypt hash    : " . $hash . "\n\n";
echo "Run this SQL to create or update your admin account:\n\n";
echo "-- INSERT (new account):\n";
echo "INSERT INTO admins (username, password_hash, full_name, title, school_email)\n";
echo "VALUES ('admin', '" . $hash . "', 'Jojo Apuli', 'School Administrator', 'jojo.apuli@buyoan.edu');\n\n";
echo "-- Or UPDATE existing row:\n";
echo "UPDATE admins SET password_hash = '" . $hash . "' WHERE username = 'admin';\n";
echo '</pre>';
echo '<p style="color:red;font-weight:bold;padding:0 20px;">⚠️ DELETE this file immediately after use!</p>';
