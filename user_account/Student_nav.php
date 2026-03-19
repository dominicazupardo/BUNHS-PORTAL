<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="student-sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<!-- ── STUDENT SIDEBAR (dark, matches admin_nav) ── -->
<aside class="sidebar" id="student-sidebar" role="navigation" aria-label="Student navigation">

    <!-- Logo -->
    <div class="logo">
        <img src="../assets/img/logo.jpg" alt="School Logo">
        <h2>Buyoan National High School</h2>
    </div>

    <!-- Profile chip — rendered dynamically from body data attributes -->
    <a data-nav-href="profile.php" class="profile" id="navProfileChip" role="link" tabindex="0" aria-label="View profile">
        <!-- Profile visual: either a photo <img> or a user icon circle -->
        <div class="nav-avatar" id="navAvatarWrap">
            <!-- JS will inject either <img> or <i class="fa-solid fa-user"> here -->
        </div>
        <div class="info">
            <h4 id="navStudentName">Student</h4>
            <p id="navGradeLevel">Grade 7</p>
        </div>
        <i class="fas fa-chevron-right profile-arrow"></i>
    </a>

    <div class="menu-divider" role="separator"></div>

    <!-- Navigation menu -->
    <nav class="menu" role="menu" aria-label="Main menu">

        <div class="menu-section">
            <span class="menu-label">MAIN MENU</span>

            <a data-nav-href="Dashboard.php" class="menu-item active" role="menuitem" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
                <span class="menu-badge">New</span>
            </a>

            <a data-nav-href="event-details.php" class="menu-item" role="menuitem" title="Events">
                <i class="fas fa-calendar-alt"></i>
                <span>Events</span>
                <span class="menu-count">3</span>
            </a>

            <a data-nav-href="chatbox.php" class="menu-item" role="menuitem" title="Chatbox">
                <i class="fas fa-comments"></i>
                <span>Chatbox</span>
                <span class="menu-count">3</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">QUICK ACCESS</span>
            <!--
            <a data-nav-href="student_report.php" class="menu-item" role="menuitem" title="report">
                <i class="fa-solid fa-person-harassing"></i>
                <span>Report</span>
            </a> -->

            <a data-nav-href="documents.php" class="menu-item" role="menuitem" title="Documents">
                <i class="fas fa-file-alt"></i>
                <span>Documents</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">SYSTEM</span>

            <a data-nav-href="settings.php" class="menu-item" role="menuitem" title="Settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>

            <a data-nav-href="help.php" class="menu-item" role="menuitem" title="Help &amp; Support">
                <i class="fas fa-question-circle"></i>
                <span>Help &amp; Support</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar footer -->
    <div class="sidebar-footer">
        <a data-nav-href="../index.php" class="logout" role="menuitem" title="Logout"
            onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-power-off"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>

<!-- ══════════════════════════════════════════════
     STYLES — dark sidebar matching admin_nav.php
══════════════════════════════════════════════ -->
<style>
    /* ─── SIDEBAR SHELL ──────────────────────────── */
    .sidebar {
        width: 280px;
        min-height: 100vh;
        background: #1e2433;
        position: fixed;
        top: 0;
        left: 0;
        display: flex;
        flex-direction: column;
        z-index: 200;
        transition: transform 0.3s ease;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .12);
        border-radius: 4px;
    }

    /* ─── LOGO ───────────────────────────────────── */
    .sidebar .logo {
        padding: 20px 20px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, .07);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .sidebar .logo img {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid rgba(255, 255, 255, .15);
    }

    .sidebar .logo h2 {
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        line-height: 1.35;
        margin: 0;
    }

    /* ─── PROFILE CHIP ───────────────────────────── */
    .sidebar .profile {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        margin: 12px 10px 0;
        border-radius: 10px;
        background: rgba(255, 255, 255, .05);
        cursor: pointer;
        text-decoration: none;
        transition: background .2s;
        flex-shrink: 0;
    }

    .sidebar .profile:hover {
        background: rgba(255, 255, 255, .09);
    }

    .sidebar .profile img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(138, 154, 91, .7);
        flex-shrink: 0;
    }

    /* ─── AVATAR WRAPPER (shared by photo & icon modes) ─── */
    .sidebar .nav-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    /* When showing a real profile photo */
    .sidebar .nav-avatar img {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(138, 154, 91, .7);
    }

    /* When showing the generic user icon (phone / skip / none) */
    .sidebar .nav-avatar.icon-mode {
        border: 2px solid rgba(138, 154, 91, .55);
        background: rgba(138, 154, 91, .12);
        color: rgba(255, 255, 255, .65);
        font-size: 16px;
    }

    .sidebar .profile .info {
        flex: 1;
        min-width: 0;
    }

    .sidebar .profile .info h4 {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar .profile .info p {
        font-size: 11px;
        color: rgba(255, 255, 255, .4);
        margin-top: 2px;
    }

    .sidebar .profile-arrow {
        font-size: 11px;
        color: rgba(255, 255, 255, .25);
        flex-shrink: 0;
    }

    /* ─── DIVIDER ────────────────────────────────── */
    .sidebar .menu-divider {
        height: 1px;
        background: rgba(255, 255, 255, .07);
        margin: 12px 0 4px;
        flex-shrink: 0;
    }

    /* ─── MENU ───────────────────────────────────── */
    .sidebar .menu {
        padding: 4px 10px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .sidebar .menu-section {
        margin-bottom: 4px;
    }

    .sidebar .menu-label {
        display: block;
        font-size: 10px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .25);
        padding: 10px 10px 6px;
        font-weight: 600;
    }

    /* ─── MENU ITEMS ─────────────────────────────── */
    .sidebar .menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        color: rgba(255, 255, 255, .55);
        font-size: 13.5px;
        font-weight: 500;
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        margin-bottom: 2px;
        position: relative;
    }

    .sidebar .menu-item i {
        font-size: 14px;
        width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar .menu-item>span:first-of-type {
        flex: 1;
    }

    .sidebar .menu-item:hover {
        background: rgba(255, 255, 255, .07);
        color: #fff;
    }

    .sidebar .menu-item.active {
        background: #8a9a5b;
        color: #fff;
    }

    .sidebar .menu-item.active:hover {
        background: #7a8a4c;
    }

    /* ─── BADGES & COUNTS ────────────────────────── */
    .sidebar .menu-badge {
        margin-left: auto;
        background: #10b981;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        border-radius: 20px;
        padding: 2px 7px;
        letter-spacing: .3px;
        text-transform: uppercase;
    }

    .sidebar .menu-count {
        margin-left: auto;
        background: rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .65);
        font-size: 11px;
        font-weight: 600;
        border-radius: 20px;
        padding: 2px 8px;
        min-width: 22px;
        text-align: center;
    }

    /* ─── FOOTER / LOGOUT ────────────────────────── */
    .sidebar .sidebar-footer {
        padding: 12px 10px;
        border-top: 1px solid rgba(255, 255, 255, .07);
        flex-shrink: 0;
        margin-top: auto;
    }

    .sidebar .logout {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 8px;
        color: rgba(255, 100, 100, .75);
        font-size: 13.5px;
        font-weight: 500;
        text-decoration: none;
        transition: all .2s;
        cursor: pointer;
    }

    .sidebar .logout:hover {
        background: rgba(239, 68, 68, .12);
        color: #ef4444;
    }

    .sidebar .logout i {
        font-size: 14px;
        width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    /* ─── MOBILE TOGGLE BUTTON ───────────────────── */
    .sidebar-toggle {
        display: none;
        position: fixed;
        top: 16px;
        left: 16px;
        z-index: 1001;
        width: 42px;
        height: 42px;
        background: #1e2433;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 8px;
        color: rgba(255, 255, 255, .75);
        font-size: 16px;
        cursor: pointer;
        transition: all .2s;
        align-items: center;
        justify-content: center;
    }

    .sidebar-toggle:hover {
        background: #8a9a5b;
        color: #fff;
        border-color: #8a9a5b;
    }

    /* ─── MOBILE OVERLAY ─────────────────────────── */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 199;
        opacity: 0;
        transition: opacity .3s ease;
        pointer-events: none;
    }

    .sidebar-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }

    /* ─── RESPONSIVE ─────────────────────────────── */
    @media (max-width: 768px) {
        .sidebar-toggle {
            display: flex;
        }

        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
            box-shadow: 6px 0 24px rgba(0, 0, 0, .35);
        }

        .sidebar-overlay {
            display: block;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .sidebar,
        .sidebar-toggle,
        .sidebar-overlay {
            transition: none;
        }
    }
</style>

<!-- ══ SCRIPT ══ -->
<script>
    (function() {
        var StudentNav = (function() {
            var sidebar, toggleBtn, overlay, initialized = false;

            function init() {
                if (initialized) return;
                sidebar = document.getElementById('student-sidebar');
                toggleBtn = document.getElementById('sidebarToggle');
                overlay = document.getElementById('sidebarOverlay');
                if (!sidebar || !toggleBtn) return;
                bindEvents();
                highlightCurrentPage();
                initialized = true;
            }

            function bindEvents() {
                toggleBtn.addEventListener('click', toggle);
                if (overlay) overlay.addEventListener('click', close);
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') close();
                });
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) close();
                });
            }

            function open() {
                sidebar.classList.add('active');
                if (overlay) {
                    overlay.classList.add('active');
                    overlay.setAttribute('aria-hidden', 'false');
                }
                toggleBtn.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function close() {
                sidebar.classList.remove('active');
                if (overlay) {
                    overlay.classList.remove('active');
                    overlay.setAttribute('aria-hidden', 'true');
                }
                toggleBtn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            function toggle() {
                sidebar.classList.contains('active') ? close() : open();
            }

            function highlightCurrentPage() {
                var currentFile = window.location.pathname.split('/').pop() || 'Dashboard.php';
                document.querySelectorAll('.sidebar .menu-item').forEach(function(item) {
                    var href = (item.getAttribute('href') || '').split('/').pop();
                    if (href === currentFile) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
            }

            function updateStudentInfo(name, grade) {
                var nameEl = document.getElementById('navStudentName');
                var gradeEl = document.getElementById('navGradeLevel');
                if (nameEl) {
                    nameEl.textContent = name;
                    nameEl.title = name;
                }
                if (gradeEl) gradeEl.textContent = grade;
            }

            function updateProfileImage(src) {
                // Legacy call — delegate to renderProfileAvatar
                if (src) renderProfileAvatar('img', src, '');
            }

            /**
             * renderProfileAvatar(mode, imgSrc, label)
             * mode : 'img'  — show a circular photo
             *        'icon' — show fa-user icon in a circle border
             *        'none' — show icon (no label)
             */
            function renderProfileAvatar(mode, imgSrc, label) {
                var wrap = document.getElementById('navAvatarWrap');
                var labelEl = document.getElementById('navProfileLabel');

                if (!wrap) return;

                if (mode === 'img' && imgSrc) {
                    wrap.className = 'nav-avatar';
                    var img = document.createElement('img');
                    img.src = imgSrc;
                    img.alt = 'Profile picture';
                    img.onerror = function() {
                        // Photo failed to load — fall back to icon
                        renderProfileAvatar('icon', '', label);
                    };
                    wrap.innerHTML = '';
                    wrap.appendChild(img);
                } else {
                    // Phone / skip / none — icon circle
                    wrap.className = 'nav-avatar icon-mode';
                    wrap.innerHTML = '<i class="fa-solid fa-user"></i>';
                }

                // Update sub-label (email / phone / blank)
                if (labelEl) {
                    labelEl.textContent = label || '';
                    labelEl.style.display = label ? '' : 'none';
                }
            }

            /**
             * Boot: read data attributes from <body> and render the
             * correct profile chip immediately after the nav is injected.
             */
            function bootProfileFromBody() {
                var body = document.body;
                var mode = body.getAttribute('data-profile-mode') || 'none';
                var imgSrc = body.getAttribute('data-profile-img') || '';
                var label = body.getAttribute('data-profile-label') || '';
                var name = body.getAttribute('data-student-name') || 'Student';
                var grade = body.getAttribute('data-grade-level') || '';
                var verified = body.getAttribute('data-user-verified') === '1';

                // Update name & grade
                updateStudentInfo(name, grade);

                // Render avatar
                if (mode === 'email' && imgSrc) {
                    renderProfileAvatar('img', imgSrc, label);
                } else if (mode === 'email' || mode === 'phone' || mode === 'skip' || mode === 'none') {
                    renderProfileAvatar('icon', '', label);
                } else {
                    renderProfileAvatar('icon', '', '');
                }

                // Inject sub-label span if not already there
                var chip = document.getElementById('navProfileChip');
                var infoDiv = chip ? chip.querySelector('.info') : null;
                if (infoDiv && !document.getElementById('navProfileLabel')) {
                    var sp = document.createElement('span');
                    sp.id = 'navProfileLabel';
                    sp.style.cssText = 'font-size:10.5px;color:rgba(255,255,255,.38);display:block;' +
                        'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;margin-top:1px;';
                    sp.textContent = label || '';
                    if (!label) sp.style.display = 'none';
                    infoDiv.appendChild(sp);
                } else if (document.getElementById('navProfileLabel')) {
                    document.getElementById('navProfileLabel').textContent = label || '';
                }
            }

            return {
                init: init,
                toggle: toggle,
                open: open,
                close: close,
                updateStudentInfo: updateStudentInfo,
                updateProfileImage: updateProfileImage,
                renderProfileAvatar: renderProfileAvatar,
                bootProfileFromBody: bootProfileFromBody
            };
        })();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', StudentNav.init);
        } else {
            StudentNav.init();
        }

        window.StudentNav = StudentNav;
        window.toggleSidebar = StudentNav.toggle;
        window.updateNavStudentInfo = StudentNav.updateStudentInfo;
    })();
</script>