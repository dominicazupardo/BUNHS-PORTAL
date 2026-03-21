<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  cache_invalidation_guide.php
//  NOT a runnable file — reference snippets to paste into your admin modules.
//
//  Rule: whenever data CHANGES in the database, delete its cache key immediately.
//  If you forget, users will see stale data until the TTL expires.
// ═══════════════════════════════════════════════════════════════════════════════

require_once 'cache_helper.php';    // required in every admin module that writes data


// ══════════════════════════════════════════════════════════════════════════════
//  1. ADMIN PASSWORD CHANGED
//     File: admin_account/change_password.php (or wherever admin PW change lives)
// ══════════════════════════════════════════════════════════════════════════════
//
//  After UPDATE admin SET password = ? WHERE username = ? succeeds:
//
$username = 'alice';   // the admin whose password was changed
cache_delete("admin:{$username}");
// Next login will re-fetch from DB and re-warm the cache with the new hash.


// ══════════════════════════════════════════════════════════════════════════════
//  2. SUB-ADMIN STATUS CHANGED  ← MOST CRITICAL
//     File: admin_account/manage_sub_admins.php (approve / suspend / revoke)
//
//  ⚠️  If you skip this, a suspended sub-admin can still log in using their
//     cached credentials for up to 10 minutes (CACHE_TTL_CREDENTIALS).
// ══════════════════════════════════════════════════════════════════════════════
//
//  After UPDATE sub_admin SET status = ? WHERE username = ? succeeds:
//
$username = 'bob_subadmin';
cache_delete("subadmin:{$username}");
// The status field is included in the cached row, so the next login attempt
// reads the updated (suspended/revoked) status directly from the DB.


// ══════════════════════════════════════════════════════════════════════════════
//  3. SUB-ADMIN PASSWORD CHANGED
//     Same file as status change, or a dedicated reset-password handler.
// ══════════════════════════════════════════════════════════════════════════════
//
$username = 'bob_subadmin';
cache_delete("subadmin:{$username}");


// ══════════════════════════════════════════════════════════════════════════════
//  4. NEW STUDENT / TEACHER ADDED  (affects homepage stats)
//     File: admin_account/add_student.php, add_teacher.php, etc.
// ══════════════════════════════════════════════════════════════════════════════
//
//  After INSERT succeeds:
//
cache_delete('stats:homepage');
// The next homepage load will re-run all stat queries and refresh the cache.


// ══════════════════════════════════════════════════════════════════════════════
//  5. NEWS / EVENTS UPDATED
//     File: admin_account/manage_news.php, manage_events.php
// ══════════════════════════════════════════════════════════════════════════════
//
cache_delete('stats:homepage');    // news and events are packed inside this key


// ══════════════════════════════════════════════════════════════════════════════
//  6. SCHOOL SETTINGS UPDATED (founding year, about photo, CTA photo)
//     File: admin_account/school_settings.php
// ══════════════════════════════════════════════════════════════════════════════
//
cache_delete('settings:homepage');


// ══════════════════════════════════════════════════════════════════════════════
//  7. HOMEPAGE CARD EDITED (leadership, cultural, innovation, cert cards)
//     File: admin_account/homepage_cards.php
// ══════════════════════════════════════════════════════════════════════════════
//
$card_key = 'leadership';   // whichever card was edited
cache_delete("card:{$card_key}");

// Or nuke all cards at once if a bulk-edit was made:
foreach (['leadership', 'cultural', 'innovation', 'cert_card1', 'cert_card2', 'cert_card3'] as $ck) {
    cache_delete("card:{$ck}");
}


// ══════════════════════════════════════════════════════════════════════════════
//  8. STUDENT PROFILE UPDATED (name, photo, email, phone)
//     File: user_account/update_profile.php or a Dashboard AJAX endpoint
// ══════════════════════════════════════════════════════════════════════════════
//
$student_id = 'S-2024-001';
cache_delete("student:profile:{$student_id}");
cache_delete("notif:{$student_id}");


// ══════════════════════════════════════════════════════════════════════════════
//  9. NUCLEAR FLUSH — clear everything (use only on Apache restart or deploy)
//     Do NOT call this on normal requests; it defeats the cache entirely.
// ══════════════════════════════════════════════════════════════════════════════
//
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
}

// Or flush just one namespace (safer):
cache_delete_prefix('stats:');      // all stats
cache_delete_prefix('admin:');      // all admin credential entries
cache_delete_prefix('subadmin:');   // all sub-admin credential entries
cache_delete_prefix('notif:');      // all student notification prefs
cache_delete_prefix('card:');       // all homepage cards


// ══════════════════════════════════════════════════════════════════════════════
//  10. NOTIFICATION_HELPER.PHP — add cache invalidation after every log_admin_notification()
//      if the logged module touches student or stats data.
//      File: notification_helper.php
// ══════════════════════════════════════════════════════════════════════════════
//
//  After log_admin_notification() call in any module that edits students:
//
//    log_admin_notification($conn, 'students', 'Edited student ID 42.');
//    cache_delete('stats:homepage');   // student count may have changed
