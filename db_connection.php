<?php

function check_mysqli_loaded()
{
    if (!function_exists('mysqli_connect')) {
        error_log('FATAL: mysqli extension not loaded.');
        http_response_code(500);
        die('Database Error: MySQLi extension required. Contact administrator.');
    }
    return true;
}

function safe_db_connect($host, $user, $pass, $dbname, $port = null)
{
    check_mysqli_loaded();

    if (empty($host) || empty($user) || empty($dbname)) {
        error_log('DB config missing: HOST=' . ($host ?? 'MISSING') . 
                  ', USER=' . ($user ?? 'MISSING') . 
                  ', DBNAME=' . ($dbname ?? 'MISSING'));
        http_response_code(500);
        die('Database Error: Missing configuration. Check environment variables.');
    }

    $port = $port ?: 3306;
    $conn = @mysqli_connect($host, $user, $pass, $dbname, (int)$port);

    if (!$conn) {
        $error = mysqli_connect_error();
        error_log('DB Connection failed: ' . $error);
        http_response_code(500);
        die('Connection failed: Database unavailable. Please try again later.');
    }

    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    return $conn;
}

// ── MAIN CONNECTION — uses Railway variable names ─────────────────────────────
$host    = getenv('MYSQLHOST')     ?: 'localhost';
$db_user = getenv('MYSQLUSER')     ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: 'bunhs_school';
$db_port = getenv('MYSQLPORT')     ?: null;

$conn = safe_db_connect($host, $db_user, $db_pass, $db_name, $db_port);

if (getenv('APP_DEBUG') === 'true') {
    error_log('DB Connected: ' . $host . ':' . ($db_port ?: 3306) . '/' . $db_name);
}
```

**Option B — Keep your PHP file as-is, and manually map variables in Railway instead** *(easier if you don't want to touch the code)*:

In your Railway **BUNHS-PORTAL service → Variables tab**, add these:

| Name | Value |
|---|---|
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_USER` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |

> ⭐ **Option B is recommended** — no code changes needed, just set it once in Railway's dashboard.

---

## ✅ Fix 3 — Make sure you have a `Procfile`

Check your project root for a `Procfile`. If it doesn't exist, create one:
```
web: exec php82 -S 0.0.0.0:${PORT:-8080} -t .