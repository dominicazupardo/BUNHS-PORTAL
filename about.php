<?php

/**
 * about.php — Fully Dynamic About Page
 * Fetches all data from database: principal, teachers, students, settings, clubs
 */
require_once 'db_connection.php';

// ── Helper ──────────────────────────────────────────────────────────────────
function h($v)
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// ── Fetch Principal (first admin) ────────────────────────────────────────────
$principal = [];
$pRes = $conn->query("SELECT full_name, title, principal_title, biography, responsibilities, profile_image, mission, vision, core_values FROM admin LIMIT 1");
if ($pRes && $pRes->num_rows) $principal = $pRes->fetch_assoc();

$principalName    = $principal['full_name']       ?? 'School Principal';
$principalTitle   = $principal['principal_title'] ?? ($principal['title'] ?? 'Principal I');
$principalBio     = $principal['biography']       ?? '';
$principalResp    = $principal['responsibilities'] ?? '';
$principalImg     = $principal['profile_image']   ?? '';
$mission          = $principal['mission']         ?? '';
$vision           = $principal['vision']          ?? '';
$coreValues       = $principal['core_values']     ?? '';

// Principal photo URL
if ($principalImg) {
    $principalPhotoUrl = 'admin/uploads/admin_profiles/' . $principalImg;
} else {
    $principalPhotoUrl = 'assets/img/person/school head.jpg';
}

// ── Fetch school settings ────────────────────────────────────────────────────
$impactSubtitle  = 'At Buyoan National High School, measurable excellence means more than just numbers.';
$schoolFoundedYr = 2017;
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM school_settings");
if ($settingsRes) {
    while ($sRow = $settingsRes->fetch_assoc()) {
        if ($sRow['setting_key'] === 'impact_subtitle') $impactSubtitle  = $sRow['setting_value'];
        if ($sRow['setting_key'] === 'school_founded_year') $schoolFoundedYr = (int)$sRow['setting_value'];
    }
}
$yearsExcellence = max(0, (int)date('Y') - $schoolFoundedYr);

// ── Count Completers ─────────────────────────────────────────────────────────
$completersCount = 0;
$cRes = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE status='Completers'");
if ($cRes) {
    $completersCount = (int)$cRes->fetch_assoc()['cnt'];
}
// Fallback: count 'Graduated' for backwards compat if no Completers yet
if ($completersCount === 0) {
    $cRes2 = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE status='Graduated'");
    if ($cRes2) $completersCount = (int)$cRes2->fetch_assoc()['cnt'];
}

// ── Count all teachers ────────────────────────────────────────────────────────
$teacherCount = 0;
$tRes = $conn->query("SELECT COUNT(*) AS cnt FROM teachers");
if ($tRes) $teacherCount = (int)$tRes->fetch_assoc()['cnt'];

// ── Fetch top 3 Entry-Level teachers (Teacher III > II > I) ─────────────────
$facultyTeachers = [];
$fRes = $conn->query("SELECT teacher_name, career_level, teacher_image
    FROM teachers
    WHERE career_level IN ('Teacher I','Teacher II','Teacher III')
    ORDER BY FIELD(career_level,'Teacher III','Teacher II','Teacher I')
    LIMIT 3");
if ($fRes) {
    while ($r = $fRes->fetch_assoc()) $facultyTeachers[] = $r;
}

// ── Fetch clubs (dynamic) ─────────────────────────────────────────────────────
$clubs = [];
$clRes = $conn->query("SELECT id, name AS club_name, description, logo_path AS image, category FROM clubs WHERE status='Active' AND is_active=1 ORDER BY id ASC LIMIT 12");
if ($clRes) {
    while ($r = $clRes->fetch_assoc()) $clubs[] = $r;
}

// ── Core values as array ─────────────────────────────────────────────────────
$coreValuesArr = array_filter(array_map('trim', explode("\n", $coreValues)));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

</head>

<body class="about-page">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

            <a href="index.html" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>

            <div id="nav-placeholder"></div>

        </div>
    </header>

    <main class="main">

        <!-- Page Title -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">About</h1>
                            <p class="mb-0">
                                Buyoan National High School is a thriving public secondary school in Legazpi City dedicated to nurturing resilience,
                                excellence, and character among learners through quality education, community partnership, and innovation in teaching
                                and communication.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="index.html">Home</a></li>
                        <li class="current">About</li>
                    </ol>
                </div>
            </nav>
        </div><!-- End Page Title -->

        <!-- History Section -->
        <section id="history" class="history section">

            <div class="container">

                <div class="hero-content text-center mb-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <span class="section-badge">Excellence in Education</span>
                            <h1 class="hero-title">Shaping Tomorrow's Innovators Through Progressive Learning</h1>
                            <p class="hero-description">Buyoan National High School empowers learners to think critically, embrace innovation, and use modern learning tools to
                                create solutions that shape a brighter future. </p>
                        </div>
                    </div>
                </div>

                <!-- ══ VISIONARY LEADERSHIP ══════════════════════════════════ -->
                <div class="leadership-showcase mb-5">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="leadership-content">
                                <h2 class="section-heading">Visionary Leadership</h2>
                                <p class="section-text">Under the guidance of dedicated school heads and stakeholders, Buyoan National High School upholds a shared vision of
                                    educational excellence, integrity, and progressive leadership in service to the community.</p>

                                <!-- Leader Profile: dynamic from DB -->
                                <div class="leader-profile">
                                    <div class="profile-image">
                                        <img src="<?php echo h($principalPhotoUrl); ?>" alt="Principal" class="img-fluid" onerror="this.src='assets/img/person/school head.jpg'">
                                    </div>
                                    <div class="profile-info">
                                        <h4><?php echo h($principalName); ?></h4>
                                        <span class="title"><?php echo h($principalTitle); ?></span>
                                        <p class="bio"><?php echo $principalBio ? h($principalBio) : 'Dedicated to educational excellence and progressive leadership.'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <!-- Faculty Grid: only Teacher I/II/III, top 3 by level -->
                            <div class="faculty-grid">
                                <?php if (!empty($facultyTeachers)): ?>
                                    <?php foreach ($facultyTeachers as $ft): ?>
                                        <div class="faculty-member">
                                            <?php
                                            $fImg = $ft['teacher_image'] ?: 'assets/img/person/unknown.jpg';
                                            // Strip leading ../ if stored as relative
                                            $fImg = ltrim($fImg, '/');
                                            if (strpos($fImg, '../') === 0) $fImg = substr($fImg, 3);
                                            ?>
                                            <img src="<?php echo h($fImg); ?>" alt="Faculty" class="img-fluid"
                                                onerror="this.src='assets/img/person/unknown.jpg'">
                                            <div class="member-info">
                                                <h5><?php echo h($ft['teacher_name']); ?></h5>
                                                <span><?php echo h($ft['career_level']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="color:#888;font-size:14px;padding:20px;">No Teacher I–III records found yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ OUR FOUNDATION: Mission / Vision / Core Values ════════ -->
                <div class="values-section mb-5">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="values-header text-center mb-4">
                                <span class="section-badge">Our Foundation</span>
                                <h2 class="section-heading">Mission &amp; Vision &amp; Core Values</h2>
                            </div>
                        </div>
                    </div>

                    <div class="row g-0">
                        <div class="col-lg-4">
                            <div class="value-block">
                                <div class="value-number">01</div>
                                <h3>Mission</h3>
                                <?php if ($mission): ?>
                                    <p><?php echo nl2br(h($mission)); ?></p>
                                <?php else: ?>
                                    <p>To protect and promote the right of every Filipino
                                        to quality, equitable, culture-based,
                                        and complete basic education.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="value-block">
                                <div class="value-number">02</div>
                                <h3>Vision</h3>
                                <?php if ($vision): ?>
                                    <p><?php echo nl2br(h($vision)); ?></p>
                                <?php else: ?>
                                    <p>We dream of Filipinos who passionately love their country
                                        and whose competencies and values enable them to realize
                                        their full potential and contribute meaningfully to
                                        building the nation.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="value-block">
                                <div class="value-number">03</div>
                                <h3>Core Values</h3>
                                <?php if (!empty($coreValuesArr)): ?>
                                    <p><?php echo implode('<br><br>', array_map('htmlspecialchars', $coreValuesArr)); ?></p>
                                <?php else: ?>
                                    <p>Maka-Diyos<br><br>Maka-tao<br><br>Makakalikasan<br><br>Makabansa</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ OUR IMPACT ════════════════════════════════════════════ -->
                <div class="accomplishments-section">
                    <div class="row align-items-center">
                        <div class="col-lg-5">
                            <div class="campus-visual">
                                <img src="assets/img/front pic/BNHS school.jpg" alt="Campus Facilities" class="main-image img-fluid">
                                <div class="floating-stats">
                                    <div class="stat-card">
                                        <span class="stat-number"><?php echo $yearsExcellence; ?>+</span>
                                        <span class="stat-label">Years Excellence</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="accomplishments-content">
                                <span class="section-badge">Our Impact</span>
                                <h2 class="section-heading">Measurable Excellence in Education</h2>
                                <p class="section-text"><?php echo h($impactSubtitle); ?></p>

                                <div class="achievements-grid">
                                    <div class="achievement-item">
                                        <span class="achievement-number"><?php echo $completersCount > 0 ? number_format($completersCount) . '+' : '1,600+'; ?></span>
                                        <span class="achievement-desc">Successful Completers</span>
                                    </div>
                                    <div class="achievement-item">
                                        <span class="achievement-number"><?php echo ($teacherCount > 0 ? $teacherCount : '25') . '+'; ?></span>
                                        <span class="achievement-desc">Expert Faculty</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </section><!-- /History Section -->

        <!-- Leadership Section -->
        <section id="leadership" class="leadership section">

            <div class="container">

                <div class="intro-section">
                    <div class="content-wrapper">
                        <span class="intro-label">Leadership Excellence</span>
                        <h2 class="intro-title">Visionary Leaders Shaping Tomorrow's Education</h2>
                        <p class="intro-description">The leadership of Buyoan National High School embodies integrity, foresight, and dedication to educational excellence.
                            Through collaborative governance and a steadfast commitment to the Department of Education's mission, they cultivate an
                            environment that empowers learners, supports teachers, and advances the school's vision of shaping a progressive and
                            resilient community.</p>
                    </div>
                </div>

                <!-- ══ FEATURED LEADER (Principal) ════════════════════════ -->
                <div class="leadership-grid">
                    <div class="featured-leader">
                        <div class="leader-image-large">
                            <img src="<?php echo h($principalPhotoUrl); ?>" alt="Principal" class="img-fluid"
                                onerror="this.src='assets/img/person/school head.jpg'">
                        </div>
                        <div class="leader-details">
                            <h3><?php echo h($principalName); ?></h3>
                            <span class="leader-title"><?php echo h($principalTitle); ?></span>
                            <p class="leader-bio"><?php echo $principalResp
                                                        ? h($principalResp)
                                                        : 'The Principal of Buyoan National High School oversees the overall operations of the school, ensuring smooth administration, quality instruction, and effective implementation of school programs.';
                                                    ?></p>
                            <div class="leader-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $yearsExcellence; ?>+</span>
                                    <span class="stat-label">Years of Service</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $completersCount > 0 ? number_format($completersCount) . '+' : '1,600+'; ?></span>
                                    <span class="stat-label">Completers</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ LEADERSHIP TEAM GRID — Clubs ═══════════════════════ -->
                <?php if (!empty($clubs)): ?>
                    <div class="leadership-team" style="margin-top:48px;">
                        <div class="team-section-header text-center mb-4">
                            <span class="section-badge">School Organizations</span>
                            <h2 class="section-heading">Leadership Team</h2>
                        </div>

                        <div class="leadership-team-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:24px;">
                            <?php foreach ($clubs as $club): ?>
                                <div class="team-member" style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 28px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,.07)'">
                                    <div class="member-photo" style="position:relative;height:160px;overflow:hidden;background:#f3f6f0;">
                                        <?php if (!empty($club['image'])): ?>
                                            <img src="<?php echo h($club['image']); ?>" alt="<?php echo h($club['club_name'] ?? ''); ?>" class="img-fluid" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;color:#b0c9a8;">
                                                <i class="bi bi-people"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="member-overlay" style="position:absolute;inset:0;background:rgba(45,106,79,.7);opacity:0;transition:opacity .2s;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                                            <div class="member-social" style="display:flex;gap:10px;">
                                                <a href="#" style="color:#fff;font-size:18px;"><i class="bi bi-linkedin"></i></a>
                                                <a href="#" style="color:#fff;font-size:18px;"><i class="bi bi-envelope"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="member-info" style="padding:18px;">
                                        <h4 style="font-size:16px;font-weight:700;margin-bottom:4px;"><?php echo h($club['club_name']); ?></h4>
                                        <span class="member-role" style="font-size:12.5px;font-weight:600;color:#2d6a4f;text-transform:uppercase;letter-spacing:.5px;">
                                            <?php echo h($club['category'] ?? ''); ?>
                                        </span>
                                        <p class="member-description" style="font-size:13px;color:#5a6578;margin-top:10px;line-height:1.6;">
                                            <?php echo h($club['description'] ?? ''); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="leadership-philosophy">
                    <div class="philosophy-content">
                        <h3>Our Leadership Philosophy</h3>
                        <p>The leadership philosophies of Buyoan National High School serve as guiding principles that shape the school's vision,
                            governance, and educational practices. These philosophies reflect the institution's unwavering commitment to fostering a
                            learning environment grounded in service, collaboration, and transformation — ensuring that every learner is guided
                            toward holistic growth, academic excellence, and moral integrity.</p>
                        <div class="philosophy-points">
                            <div class="point">
                                <i class="bi bi-lightbulb"></i>
                                <span>Transformational Leadership practices</span>
                            </div>
                            <div class="point">
                                <i class="bi bi-people"></i>
                                <span>Collaborative Leadership practices</span>
                            </div>
                            <div class="point">
                                <i class="bi bi-graph-up"></i>
                                <span>Continuous improvement mindset</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </section><!-- /Leadership Section -->

    </main>

    <!-- Footer Placeholder -->
    <div id="footer-placeholder"></div>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));
    </script>

    <!-- Include Footer -->
    <script>
        fetch('footer.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading footer:', error));
    </script>

    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(response => response.text())
            .then(data => {
                document.body.insertAdjacentHTML('beforeend', data);
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');
                    if (loginBtn) loginBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                        loginModal.show();
                    });
                    if (signupBtn) signupBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
                        signupModal.show();
                    });
                });
            })
            .catch(error => console.error('Error loading modals:', error));
    </script>

</body>

</html>