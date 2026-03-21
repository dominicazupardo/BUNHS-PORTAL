<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  apcu_setup_check.php — XAMPP APCu Setup Verification & Debug Page
//
//  STEP 1: Visit this page BEFORE adding any cache code to verify APCu works.
//  STEP 2: Delete or restrict access to this file in production.
//
//  URL: http://localhost/BUNHS_School_System/apcu_setup_check.php
// ═══════════════════════════════════════════════════════════════════════════════

// Only allow access from localhost during development
$allowed_ips = ['127.0.0.1', '::1', 'localhost'];
$client_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($client_ip, $allowed_ips, true)) {
    http_response_code(403);
    die('Access denied. This diagnostic page is for local development only.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>APCu Setup Check — BUNHS</title>
    <style>
        body        { font-family: 'Segoe UI', Arial, sans-serif; max-width: 820px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; color: #222; }
        h1          { color: #1a3a2a; border-bottom: 3px solid #52b788; padding-bottom: 10px; }
        h2          { color: #2d6a4f; margin-top: 32px; }
        .card       { background: #fff; border-radius: 10px; padding: 20px 24px; margin: 16px 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .ok         { color: #1a7a3c; font-weight: bold; }
        .fail       { color: #c0392b; font-weight: bold; }
        .warn       { color: #d68910; font-weight: bold; }
        table       { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td      { text-align: left; padding: 8px 12px; border-bottom: 1px solid #e5e5e5; }
        th          { background: #f0f7f4; color: #1a3a2a; }
        code        { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        pre         { background: #1e2d24; color: #b7e4c7; padding: 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.6; }
        .step       { background: #fff; border-left: 4px solid #52b788; padding: 12px 16px; margin: 12px 0; border-radius: 0 8px 8px 0; }
        .step.warn  { border-color: #d68910; }
        .badge      { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge.ok   { background: #d4edda; color: #155724; }
        .badge.fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<h1>🏫 BUNHS — APCu Setup Check</h1>
<p style="color:#666;">Run this page to verify APCu is correctly installed and working on your XAMPP server.</p>

<?php

// ── 1. Extension loaded? ──────────────────────────────────────────────────────
$ext_loaded = extension_loaded('apcu');
$fn_fetch   = function_exists('apcu_fetch');
$fn_store   = function_exists('apcu_store');
$fn_delete  = function_exists('apcu_delete');
$fn_info    = function_exists('apcu_cache_info');

echo '<div class="card">';
echo '<h2>1. Extension Status</h2>';
echo '<table>';
echo '<tr><th>Check</th><th>Result</th></tr>';
echo '<tr><td>extension_loaded("apcu")</td><td class="' . ($ext_loaded ? 'ok' : 'fail') . '">' . ($ext_loaded ? '✅ Loaded' : '❌ NOT loaded') . '</td></tr>';
echo '<tr><td>apcu_fetch() available</td><td class="' . ($fn_fetch ? 'ok' : 'fail') . '">' . ($fn_fetch ? '✅ Yes' : '❌ No') . '</td></tr>';
echo '<tr><td>apcu_store() available</td><td class="' . ($fn_store ? 'ok' : 'fail') . '">' . ($fn_store ? '✅ Yes' : '❌ No') . '</td></tr>';
echo '<tr><td>apcu_delete() available</td><td class="' . ($fn_delete ? 'ok' : 'fail') . '">' . ($fn_delete ? '✅ Yes' : '❌ No') . '</td></tr>';
echo '<tr><td>apcu_cache_info() available</td><td class="' . ($fn_info ? 'ok' : 'fail') . '">' . ($fn_info ? '✅ Yes' : '❌ No') . '</td></tr>';
echo '</table>';
echo '</div>';

if (!$ext_loaded) {
    echo '<div class="card">';
    echo '<h2 class="fail">❌ APCu Not Installed — Follow These Steps</h2>';

    $php_ver = phpversion();
    echo '<div class="step warn"><strong>Your PHP version:</strong> ' . $php_ver . ' &nbsp; <span class="badge fail">Need matching DLL</span></div>';

    echo '<div class="step"><strong>Step 1:</strong> Download the correct APCu DLL from <a href="https://pecl.php.net/package/APCu" target="_blank">pecl.php.net/package/APCu</a><br>';
    echo 'Match: PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ' &bull; ';
    echo (PHP_INT_SIZE === 8 ? 'x64' : 'x86') . ' &bull; ';
    echo (PHP_ZTS ? 'Thread Safe (TS)' : 'Non-Thread Safe (NTS)') . '</div>';

    echo '<div class="step"><strong>Step 2:</strong> Place <code>php_apcu.dll</code> in <code>C:\xampp\php\ext\</code></div>';

    echo '<div class="step"><strong>Step 3:</strong> Open <code>C:\xampp\php\php.ini</code> and add these lines at the bottom:<br>';
    echo '<pre>extension=php_apcu.dll
apc.enabled=1
apc.shm_size=128M
apc.enable_cli=1</pre></div>';

    echo '<div class="step"><strong>Step 4:</strong> Restart Apache in XAMPP Control Panel, then reload this page.</div>';

    echo '</div>';
    echo '</body></html>';
    exit;
}


// ── 2. INI settings ───────────────────────────────────────────────────────────
echo '<div class="card">';
echo '<h2>2. APCu INI Settings</h2>';
$ini_keys = ['apc.enabled', 'apc.shm_size', 'apc.enable_cli', 'apc.ttl', 'apc.user_ttl'];
echo '<table><tr><th>Setting</th><th>Value</th></tr>';
foreach ($ini_keys as $k) {
    $v = ini_get($k);
    echo '<tr><td><code>' . $k . '</code></td><td>' . ($v !== false ? htmlspecialchars($v) : '<em style="color:#999">not set</em>') . '</td></tr>';
}
echo '</table>';
echo '</div>';


// ── 3. Live read/write test ───────────────────────────────────────────────────
echo '<div class="card">';
echo '<h2>3. Live Read / Write Test</h2>';

$test_key   = 'bunhs_apcu_test_' . time();
$test_value = ['ts' => time(), 'msg' => 'BUNHS APCu is working!', 'php' => phpversion()];

$write_ok = apcu_store($test_key, $test_value, 30);
$read_val = apcu_fetch($test_key, $fetch_ok);
$delete_ok = apcu_delete($test_key);

echo '<table><tr><th>Operation</th><th>Result</th></tr>';
echo '<tr><td>apcu_store()</td><td class="' . ($write_ok ? 'ok' : 'fail') . '">' . ($write_ok ? '✅ Stored successfully' : '❌ Store failed') . '</td></tr>';
echo '<tr><td>apcu_fetch()</td><td class="' . ($fetch_ok ? 'ok' : 'fail') . '">' . ($fetch_ok ? '✅ Read back correctly: <code>' . htmlspecialchars($read_val['msg']) . '</code>' : '❌ Fetch failed') . '</td></tr>';
echo '<tr><td>apcu_delete()</td><td class="' . ($delete_ok ? 'ok' : 'fail') . '">' . ($delete_ok ? '✅ Deleted successfully' : '❌ Delete failed') . '</td></tr>';
echo '</table>';
echo '</div>';


// ── 4. Cache stats ────────────────────────────────────────────────────────────
if ($fn_info) {
    $info = apcu_cache_info(false);
    $sma  = apcu_sma_info(true);

    echo '<div class="card">';
    echo '<h2>4. Cache Statistics</h2>';
    echo '<table><tr><th>Metric</th><th>Value</th></tr>';
    echo '<tr><td>Entries in cache</td><td>' . number_format((int)($info['num_entries'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Cache hits</td><td>' . number_format((int)($info['num_hits'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Cache misses</td><td>' . number_format((int)($info['num_misses'] ?? 0)) . '</td></tr>';
    $total = ((int)($info['num_hits'] ?? 0)) + ((int)($info['num_misses'] ?? 0));
    $rate  = $total > 0 ? round(($info['num_hits'] / $total) * 100, 1) . '%' : 'n/a';
    echo '<tr><td>Hit rate</td><td><strong>' . $rate . '</strong></td></tr>';
    echo '<tr><td>Available shared memory</td><td>' . round(($sma['avail_mem'] ?? 0) / 1_048_576, 1) . ' MB</td></tr>';
    echo '<tr><td>APCu version</td><td>' . phpversion('apcu') . '</td></tr>';
    echo '</table>';

    if (!empty($info['cache_list'])) {
        echo '<h3 style="margin-top:20px;">Current cache entries</h3>';
        echo '<table><tr><th>Key</th><th>TTL (s)</th><th>Hits</th><th>Memory</th></tr>';
        foreach ($info['cache_list'] as $entry) {
            $ttl = max(0, (int)($entry['ttl'] ?? 0) - (time() - (int)($entry['creation_time'] ?? time())));
            echo '<tr>';
            echo '<td><code>' . htmlspecialchars($entry['info']) . '</code></td>';
            echo '<td>' . $ttl . '</td>';
            echo '<td>' . (int)($entry['num_hits'] ?? 0) . '</td>';
            echo '<td>' . number_format((int)($entry['mem_size'] ?? 0)) . ' B</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color:#888;margin-top:12px;"><em>No entries cached yet. Visit the homepage and log in first.</em></p>';
    }

    echo '</div>';
}


// ── 5. PHP info summary ───────────────────────────────────────────────────────
echo '<div class="card">';
echo '<h2>5. Environment Summary</h2>';
echo '<table><tr><th>Property</th><th>Value</th></tr>';
echo '<tr><td>PHP Version</td><td>' . phpversion() . '</td></tr>';
echo '<tr><td>Architecture</td><td>' . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit') . '</td></tr>';
echo '<tr><td>Thread Safety</td><td>' . (PHP_ZTS ? 'Thread Safe (TS)' : 'Non-Thread Safe (NTS)') . '</td></tr>';
echo '<tr><td>Server Software</td><td>' . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . '</td></tr>';
echo '<tr><td>memory_limit</td><td>' . ini_get('memory_limit') . '</td></tr>';
echo '</table>';
echo '</div>';


// ── Summary ───────────────────────────────────────────────────────────────────
$all_ok = $ext_loaded && $write_ok && $fetch_ok && $delete_ok;
echo '<div class="card" style="border: 2px solid ' . ($all_ok ? '#52b788' : '#c0392b') . ';">';
echo '<h2>' . ($all_ok ? '✅ All checks passed — APCu is ready!' : '⚠️  Some checks failed — see above') . '</h2>';
if ($all_ok) {
    echo '<p class="ok">APCu is correctly installed, readable, writable, and ready for the BUNHS caching system.</p>';
    echo '<p style="color:#666;">You may now add <code>require_once \'cache_helper.php\';</code> to your PHP files.</p>';
    echo '<p style="color:#c0392b;font-weight:bold;">⚠️ Delete or restrict this file before deploying to production.</p>';
}
echo '</div>';

?>
</body>
</html>
