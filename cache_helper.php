<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  cache_helper.php — BUNHS APCu Caching Layer
//  Place this file at the project root (same level as db_connection.php).
//
//  USAGE (in any PHP file):
//    require_once 'cache_helper.php';
//
//  APCu is a PHP in-process shared memory store — zero infrastructure,
//  works on XAMPP out of the box once the extension is enabled.
//
//  CACHE KEY CONVENTION:
//    admin:{username}          — admin credential row
//    subadmin:{username}       — sub-admin credential row
//    email_exists:{email}      — email availability (signup check_email)
//    notif:{student_id}        — student notification preference row
//    stats:homepage            — all homepage aggregate counts
//    settings:homepage         — school_settings table values
//    card:{card_key}           — individual homepage card row
//    news:homepage             — recent news array
//    events:homepage           — upcoming events array
// ═══════════════════════════════════════════════════════════════════════════════


// ── AVAILABILITY CHECK ────────────────────────────────────────────────────────
// Detect once and store in a constant so every function avoids repeated calls
// to function_exists().
define('APCU_AVAILABLE', function_exists('apcu_fetch'));


// ── TTL CONSTANTS (seconds) ───────────────────────────────────────────────────
// Centralised here so you never hunt through multiple files to adjust them.
define('CACHE_TTL_CREDENTIALS',   600);  // 10 min  — admin / sub-admin login rows
define('CACHE_TTL_EMAIL_EXISTS',  300);  //  5 min  — signup email-availability checks
define('CACHE_TTL_NOTIF_PREF',    120);  //  2 min  — student notification preferences
define('CACHE_TTL_STATS',         120);  //  2 min  — homepage aggregate stats
define('CACHE_TTL_SETTINGS',     1800);  // 30 min  — school settings (rarely change)
define('CACHE_TTL_CARD',         1800);  // 30 min  — homepage cards
define('CACHE_TTL_NEWS',          300);  //  5 min  — recent news list
define('CACHE_TTL_EVENTS',        300);  //  5 min  — upcoming events list


// ══════════════════════════════════════════════════════════════════════════════
//  CORE FUNCTIONS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Retrieve a value from the APCu cache.
 *
 * Returns the cached value on hit, or boolean false on miss / APCu absent.
 * NOTE: if you store an actual boolean false, use cache_exists() first.
 *
 * @param  string $key
 * @return mixed|false
 */
function cache_get(string $key): mixed
{
    if (!APCU_AVAILABLE) {
        return false;
    }

    $value = apcu_fetch($key, $success);
    return $success ? $value : false;
}


/**
 * Store a value in the APCu cache.
 *
 * @param  string $key
 * @param  mixed  $data  Any serialisable PHP value (array, string, int …)
 * @param  int    $ttl   Seconds until expiry. 0 = never expires (until restart).
 * @return void
 */
function cache_set(string $key, mixed $data, int $ttl = CACHE_TTL_STATS): void
{
    if (!APCU_AVAILABLE) {
        return;
    }
    apcu_store($key, $data, $ttl);
}


/**
 * Delete a single cache entry by exact key.
 * Call this immediately whenever the underlying data changes.
 *
 * @param  string $key
 * @return void
 */
function cache_delete(string $key): void
{
    if (!APCU_AVAILABLE) {
        return;
    }
    apcu_delete($key);
}


/**
 * Delete every APCu entry whose key begins with $prefix.
 * Useful for bulk-invalidating a whole category (e.g. all 'stats:*').
 *
 * @param  string $prefix  e.g. 'stats:', 'admin:', 'subadmin:'
 * @return void
 */
function cache_delete_prefix(string $prefix): void
{
    if (!APCU_AVAILABLE || !function_exists('apcu_cache_info')) {
        return;
    }

    $info = apcu_cache_info(false);          // false = return all entries
    if (empty($info['cache_list'])) {
        return;
    }

    foreach ($info['cache_list'] as $entry) {
        if (str_starts_with($entry['info'], $prefix)) {
            apcu_delete($entry['info']);
        }
    }
}


/**
 * Check whether a key currently exists in cache (without fetching the value).
 *
 * @param  string $key
 * @return bool
 */
function cache_exists(string $key): bool
{
    if (!APCU_AVAILABLE) {
        return false;
    }
    return apcu_exists($key);
}


// ══════════════════════════════════════════════════════════════════════════════
//  DIAGNOSTIC HELPER (DEVELOPMENT ONLY — remove before production)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Return a summary of the current APCu state.
 * Usage: echo json_encode(cache_stats()); on any debug page.
 *
 * @return array
 */
function cache_stats(): array
{
    if (!APCU_AVAILABLE || !function_exists('apcu_cache_info')) {
        return ['available' => false, 'note' => 'APCu not installed or disabled.'];
    }

    $info  = apcu_cache_info(false);
    $mem   = apcu_sma_info(true);
    $hits  = (int)($info['num_hits']   ?? 0);
    $miss  = (int)($info['num_misses'] ?? 0);
    $total = $hits + $miss;

    return [
        'available'   => true,
        'num_entries' => (int)($info['num_entries'] ?? 0),
        'mem_used_mb' => round(($mem['avail_mem'] ?? 0) / 1_048_576, 2),
        'hits'        => $hits,
        'misses'      => $miss,
        'hit_rate'    => $total > 0 ? round($hits / $total * 100, 1) . '%' : 'n/a',
        'keys'        => array_column($info['cache_list'] ?? [], 'info'),
    ];
}
