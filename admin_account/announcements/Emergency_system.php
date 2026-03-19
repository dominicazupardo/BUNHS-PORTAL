<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency System - School Admin Dashboard</title>
    <link rel="stylesheet" href="../admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Emergency System Styles */
        .emergency-header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .emergency-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .emergency-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .emergency-header p {
            font-size: 14px;
            opacity: 0.9;
            max-width: 600px;
        }

        .emergency-stats {
            display: flex;
            gap: 24px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .emergency-stat {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .emergency-stat-value {
            font-size: 24px;
            font-weight: 700;
        }

        .emergency-stat-label {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Disaster Cards Grid */
        .disaster-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .disaster-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .disaster-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .disaster-card.priority-1 {
            border-color: #dc2626;
        }

        .disaster-card.priority-1:hover {
            box-shadow: 0 12px 30px rgba(220, 38, 38, 0.25);
        }

        .disaster-card.priority-2 {
            border-color: #ea580c;
        }

        .disaster-card.priority-2:hover {
            box-shadow: 0 12px 30px rgba(234, 88, 12, 0.25);
        }

        .disaster-card-image {
            height: 180px;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .disaster-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .disaster-card-image .placeholder-icon {
            font-size: 64px;
            color: #9ca3af;
        }

        .disaster-card-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .disaster-card-badge.high {
            background: #dc2626;
        }

        .disaster-card-badge.medium {
            background: #ea580c;
        }

        .disaster-card-badge.low {
            background: #6b7280;
        }

        .disaster-card-content {
            padding: 20px;
        }

        .disaster-card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .disaster-card-title i {
            font-size: 20px;
        }

        .disaster-card-description {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .disaster-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .disaster-card-action {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
        }

        .disaster-card-action i {
            transition: transform 0.3s ease;
        }

        .disaster-card:hover .disaster-card-action i {
            transform: translateX(4px);
        }

        /* Category Badge */
        .category-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .category-badge.natural {
            background: #10b981;
            color: white;
        }

        .category-badge.non-natural {
            background: #6366f1;
            color: white;
        }

        /* Confirmation Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .emergency-modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 24px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-disaster-info {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 12px;
        }

        .modal-disaster-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
        }

        .modal-disaster-details h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .modal-disaster-details p {
            font-size: 13px;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group textarea,
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: #dc2626;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .char-count {
            text-align: right;
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .modal-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .modal-warning i {
            color: #f59e0b;
            font-size: 20px;
            flex-shrink: 0;
        }

        .modal-warning p {
            font-size: 13px;
            color: #92400e;
            margin: 0;
        }

        .modal-footer {
            padding: 0 24px 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-danger:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: toastSlideIn 0.3s ease;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .toast.success {
            border-left: 4px solid #10b981;
        }

        .toast.success i {
            color: #10b981;
        }

        .toast.error {
            border-left: 4px solid #dc2626;
        }

        .toast.error i {
            color: #dc2626;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
        }

        .toast-message {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Alert History Section */
        .history-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-top: 32px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .history-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .history-table th {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            background: #f9fafb;
        }

        .history-table td {
            font-size: 14px;
            color: #1f2937;
        }

        .history-table tr:hover {
            background: #f9fafb;
        }

        .history-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .history-badge.sms {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .history-badge.email {
            background: #fce7f3;
            color: #db2777;
        }

        .empty-history {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .empty-history i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        /* Section Titles */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            margin: 32px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title.natural {
            color: #10b981;
        }

        .section-title.non-natural {
            color: #6366f1;
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <script>
        // Load navigation
        fetch('../admin_nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
                if (mainDiv && pageContent) mainDiv.appendChild(pageContent);
                fixAllNavLinks();
                initDropdowns();
            })
            .catch(error => console.error('Error loading navigation:', error));

        /**
         * Resolve the admin_account base URL from the current page's URL.
         * Works regardless of how deeply nested the current page is.
         */
        function getAdminBase() {
            const parts = window.location.pathname.split('/');
            const idx = parts.indexOf('admin_account');
            if (idx !== -1) {
                return parts.slice(0, idx + 1).join('/') + '/';
            }
            return window.location.pathname.split('/').slice(0, -1).join('/') + '/';
        }

        function fixAllNavLinks() {
            const adminBase = getAdminBase();

            document.querySelectorAll(
                '.sidebar a[href], .topbar a[href], .user-menu a[href]'
            ).forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') ||
                    href.startsWith('http') || href.startsWith('/')) return;

                if (href.startsWith('admin_account/')) {
                    link.setAttribute('href', adminBase + href.replace('admin_account/', ''));
                } else if (!href.startsWith('../') && !href.startsWith('./')) {
                    link.setAttribute('href', adminBase + href);
                }
            });

            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                const page = item.getAttribute('data-page');
                item.setAttribute('href', adminBase + 'announcements/' + page);
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                const fresh = toggle.cloneNode(true);
                toggle.parentNode.replaceChild(fresh, toggle);
                fresh.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    const isActive = dropdown.classList.contains('active');
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                    if (!isActive) dropdown.classList.add('active');
                });
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                }
            });
        }
    </script>

    <section class="page-content">
        <!-- Emergency Header -->
        <div class="emergency-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Emergency Alert System</h1>
            <p>Send immediate emergency notifications to all registered contacts via SMS and Email. Click on any emergency type below to send an alert.</p>
            <div class="emergency-stats">
                <div class="emergency-stat">
                    <div class="emergency-stat-value" id="totalContacts">-</div>
                    <div class="emergency-stat-label">Total Contacts</div>
                </div>
                <div class="emergency-stat">
                    <div class="emergency-stat-value" id="phoneContacts">-</div>
                    <div class="emergency-stat-label">Phone Numbers</div>
                </div>
                <div class="emergency-stat">
                    <div class="emergency-stat-value" id="emailContacts">-</div>
                    <div class="emergency-stat-label">Email Addresses</div>
                </div>
                <div class="emergency-stat">
                    <div class="emergency-stat-value" id="totalAlerts">-</div>
                    <div class="emergency-stat-label">Alerts Sent</div>
                </div>
            </div>
        </div>

        <!-- Natural Disasters Section -->
        <h2 class="section-title natural">
            <i class="fas fa-globe-asia"></i> Natural Disasters
        </h2>
        <div class="disaster-grid" id="naturalDisastersGrid"></div>

        <!-- Non-Natural Emergencies Section -->
        <h2 class="section-title non-natural">
            <i class="fas fa-building"></i> Non-Natural Emergencies
        </h2>
        <div class="disaster-grid" id="nonNaturalDisastersGrid"></div>

        <!-- Alert History -->
        <div class="history-section">
            <div class="history-header">
                <h3><i class="fas fa-history"></i> Alert History</h3>
                <button class="btn btn-secondary" onclick="loadAlertHistory()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div id="historyContent"></div>
        </div>
    </section>

    <!-- Emergency Modal -->
    <div class="modal-overlay" id="emergencyModal">
        <div class="emergency-modal">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-exclamation-circle"></i> Send Emergency Alert</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-disaster-info">
                    <div class="modal-disaster-icon" id="modalIcon">
                        <i class="fas fa-volcano"></i>
                    </div>
                    <div class="modal-disaster-details">
                        <h3 id="modalDisasterName">Volcanic Eruption</h3>
                        <p id="modalDisasterDesc">Mayon Volcano activity detected</p>
                    </div>
                </div>

                <div class="modal-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>This will send an emergency notification to ALL registered contacts via SMS and Email. This action cannot be undone. Please verify the message before sending.</p>
                </div>

                <div class="form-group">
                    <label for="emergencyMessage">Emergency Message</label>
                    <textarea id="emergencyMessage" placeholder="Enter your emergency message..." oninput="updateCharCount()"></textarea>
                    <div class="char-count"><span id="charCount">0</span> / 300 characters</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-danger" id="sendBtn" onclick="sendEmergencyAlert()">
                    <i class="fas fa-paper-plane"></i> Send Alert
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="../admin_assets/js/admin_script.js"></script>

    <script>
        // All Emergency Types for BUNHS - Natural and Non-Natural
        const disasters = [
            // === NATURAL DISASTERS ===
            {
                id: 'volcanic_eruption',
                name: 'Volcanic Eruption',
                description: 'Mayon Volcano eruption - Active stratovolcano in Albay. School is in danger zone.',
                icon: 'fa-volcano',
                color: '#dc2626',
                priority: 1,
                badge: 'HIGH RISK',
                badgeClass: 'high',
                category: 'natural',
                defaultMessage: 'URGENT: Mayon Volcano eruption alert from BUNHS! Evacuate immediately to designated safe zone. Follow school protocols. Stay tuned to local news.'
            },
            {
                id: 'volcanic_ashfall',
                name: 'Volcanic Ashfall',
                description: 'Ash dispersion from Mayon Volcano activity. Common during eruptions.',
                icon: 'fa-cloud',
                color: '#57534e',
                priority: 1,
                badge: 'HIGH RISK',
                badgeClass: 'high',
                category: 'natural',
                defaultMessage: 'ADVISORY: Volcanic ashfall from Mayon Volcano in Albay. BUNHS: 1) Wear masks 2) Stay indoors 3) Protect electronics 4) Close windows. Monitor for closures.'
            },
            {
                id: 'typhoon',
                name: 'Typhoon',
                description: 'Tropical cyclones frequently hit Albay. Classes may be suspended.',
                icon: 'fa-wind',
                color: '#0891b2',
                priority: 2,
                badge: 'MEDIUM RISK',
                badgeClass: 'medium',
                category: 'natural',
                defaultMessage: 'TYPHOON ALERT from BUNHS: Typhoon approaching Albay. Secure belongings. Classes suspended. Stay indoors. Follow evacuation orders.'
            },
            {
                id: 'flood',
                name: 'Flooding',
                description: 'Monsoon floods in Legazpi City and surrounding areas.',
                icon: 'fa-water',
                color: '#0284c7',
                priority: 2,
                badge: 'MEDIUM RISK',
                badgeClass: 'medium',
                category: 'natural',
                defaultMessage: 'FLOOD WARNING: Heavy rains causing flooding in Albay. BUNHS students: Avoid flooded roads. Stay away from rivers. Monitor for school closures.'
            },
            {
                id: 'landslide',
                name: 'Landslide',
                description: 'Rain-induced landslides in Albay mountainous areas.',
                icon: 'fa-mountain',
                color: '#a16207',
                priority: 2,
                badge: 'MEDIUM RISK',
                badgeClass: 'medium',
                category: 'natural',
                defaultMessage: 'LANDSLIDE ADVISORY: BUNHS - Heavy rains may trigger landslides. Avoid slopes and mountains. Evacuate to higher ground if in danger.'
            },
            {
                id: 'storm_surge',
                name: 'Storm Surge',
                description: 'Coastal flooding from typhoons in Legazpi City.',
                icon: 'fa-water-ladder',
                color: '#0369a1',
                priority: 2,
                badge: 'MEDIUM RISK',
                badgeClass: 'medium',
                category: 'natural',
                defaultMessage: 'STORM SURGE WARNING from BUNHS: Coastal flooding expected. Evacuate immediately to designated centers. Do not return until cleared.'
            },
            {
                id: 'earthquake',
                name: 'Earthquake',
                description: 'Seismic activity - Philippines is in Ring of Fire.',
                icon: 'fa-earth-asia',
                color: '#7c3aed',
                priority: 3,
                badge: 'NATURAL',
                badgeClass: 'low',
                category: 'natural',
                defaultMessage: 'EARTHQUAKE ALERT: BUNHS - Drop, Cover, and Hold On! Check for injuries. Evacuate when shaking stops. Follow safety protocols.'
            },
            {
                id: 'tsunami',
                name: 'Tsunami',
                description: 'Tsunami risk for coastal Albay.',
                icon: 'fa-water',
                color: '#0d9488',
                priority: 3,
                badge: 'CRITICAL',
                badgeClass: 'high',
                category: 'natural',
                defaultMessage: 'TSUNAMI WARNING: Evacuate immediately to higher ground (30m+). Do not return until cleared by authorities. This is a BUNHS emergency alert.'
            },
            // === NON-NATURAL EMERGENCIES ===
            {
                id: 'fire',
                name: 'Fire',
                description: 'Building fire or structural fire at BUNHS campus.',
                icon: 'fa-fire',
                color: '#f97316',
                priority: 1,
                badge: 'EMERGENCY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'FIRE EMERGENCY at BUNHS: Evacuate calmly via nearest exit. Do NOT use elevators. Call 911. Assemble at designated point.'
            },
            {
                id: 'bomb_threat',
                name: 'Bomb Threat',
                description: 'Bomb threat or suspicious package reported at school.',
                icon: 'fa-bomb',
                color: '#1f2937',
                priority: 1,
                badge: 'EMERGENCY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'BOMB THREAT at BUNHS: Evacuate immediately and calmly. Leave all belongings. Go to designated evacuation area. Do not return until cleared.'
            },
            {
                id: 'intruder',
                name: 'Intruder',
                description: 'Unauthorized person or security threat on campus.',
                icon: 'fa-user-shield',
                color: '#dc2626',
                priority: 1,
                badge: 'SECURITY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'SECURITY ALERT: Intruder reported at BUNHS. Students: Lock doors, stay away from windows. Follow lockdown procedures. Wait for all-clear.'
            },
            {
                id: 'lockdown',
                name: 'Lockdown',
                description: 'School-wide lockdown for safety emergency.',
                icon: 'fa-lock',
                color: '#991b1b',
                priority: 1,
                badge: 'LOCKDOWN',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'LOCKDOWN at BUNHS: This is NOT a drill. Secure all doors. Stay away from windows. Remain quiet. Do not open doors until all-clear.'
            },
            {
                id: 'gas_leak',
                name: 'Gas Leak',
                description: 'Gas leak or chemical fumes in school building.',
                icon: 'fa-gas-pump',
                color: '#84cc16',
                priority: 1,
                badge: 'EMERGENCY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'GAS LEAK at BUNHS: Evacuate immediately. Do not turn on switches or use phones. Go to open area. Do not return until safe.'
            },
            {
                id: 'structural_damage',
                name: 'Structural Damage',
                description: 'Building collapse or structural damage at school.',
                icon: 'fa-building-cracked',
                color: '#a16207',
                priority: 1,
                badge: 'EMERGENCY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'STRUCTURAL EMERGENCY at BUNHS: Building damage detected. Evacuate immediately. Do not re-enter. Proceed to assembly point.'
            },
            {
                id: 'food_poisoning',
                name: 'Food Poisoning',
                description: 'Mass food poisoning or contamination incident.',
                icon: 'fa-utensils',
                color: '#f59e0b',
                priority: 2,
                badge: 'HEALTH',
                badgeClass: 'medium',
                category: 'non-natural',
                defaultMessage: 'HEALTH ALERT: Food poisoning reported at BUNHS. Affected students: Seek medical attention. Others: Do not consume school food. Report symptoms.'
            },
            {
                id: 'medical_emergency',
                name: 'Medical Emergency',
                description: 'Mass casualty or serious injury incident at school.',
                icon: 'fa-syringe',
                color: '#ef4444',
                priority: 2,
                badge: 'MEDICAL',
                badgeClass: 'medium',
                category: 'non-natural',
                defaultMessage: 'MEDICAL EMERGENCY at BUNHS: Serious incident reported. First aid in progress. Clear corridors for ambulances. Follow staff instructions.'
            },
            {
                id: 'bus_accident',
                name: 'Bus Accident',
                description: 'School bus accident or transportation emergency.',
                icon: 'fa-bus-school',
                color: '#d97706',
                priority: 2,
                badge: 'TRANSPORT',
                badgeClass: 'medium',
                category: 'non-natural',
                defaultMessage: 'BUS EMERGENCY: School bus incident reported. Parents will be notified. Follow instructions from school administration.'
            },
            {
                id: 'water_emergency',
                name: 'Water Emergency',
                description: 'Water supply contamination or outage.',
                icon: 'fa-faucet-drip',
                color: '#0891b2',
                priority: 3,
                badge: 'UTILITY',
                badgeClass: 'low',
                category: 'non-natural',
                defaultMessage: 'WATER EMERGENCY at BUNHS: Water supply issue. Do not drink tap water until further notice. Use bottled water only.'
            },
            {
                id: 'power_outage',
                name: 'Power Outage',
                description: 'Power interruption affecting school operations.',
                icon: 'fa-bolt',
                color: '#eab308',
                priority: 3,
                badge: 'UTILITY',
                badgeClass: 'low',
                category: 'non-natural',
                defaultMessage: 'POWER OUTAGE NOTICE from BUNHS: Power interruption. School operations affected. Wait for further announcements.'
            },
            {
                id: 'disease_outbreak',
                name: 'Disease Outbreak',
                description: 'Contagious disease outbreak affecting students.',
                icon: 'fa-virus',
                color: '#84cc16',
                priority: 3,
                badge: 'HEALTH',
                badgeClass: 'low',
                category: 'non-natural',
                defaultMessage: 'HEALTH ADVISORY: Disease outbreak at BUNHS. Stay home if symptomatic. Practice hygiene. Monitor for school health announcements.'
            },
            {
                id: 'riot',
                name: 'Riot / Civil Unrest',
                description: 'Student unrest or civil disturbance near school.',
                icon: 'fa-users-slash',
                color: '#991b1b',
                priority: 1,
                badge: 'SECURITY',
                badgeClass: 'high',
                category: 'non-natural',
                defaultMessage: 'SECURITY ALERT: Civil unrest near BUNHS. Stay inside. Lock doors. Follow lockdown procedures. Wait for official update.'
            }
        ];

        let currentDisaster = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            renderDisasterCards();
            loadContactStats();
            loadAlertHistory();
        });

        // Render disaster cards separated by category
        function renderDisasterCards() {
            const naturalGrid = document.getElementById('naturalDisastersGrid');
            const nonNaturalGrid = document.getElementById('nonNaturalDisastersGrid');

            const naturalDisasters = disasters.filter(d => d.category === 'natural');
            const nonNaturalDisasters = disasters.filter(d => d.category === 'non-natural');

            naturalGrid.innerHTML = naturalDisasters.map(disaster => createDisasterCard(disaster)).join('');
            nonNaturalGrid.innerHTML = nonNaturalDisasters.map(disaster => createDisasterCard(disaster)).join('');
        }

        function createDisasterCard(disaster) {
            return `
                <div class="disaster-card priority-${disaster.priority}" onclick="openModal('${disaster.id}')">
                    <div class="disaster-card-image">
                        <span class="disaster-card-badge ${disaster.badgeClass}">${disaster.badge}</span>
                        <span class="category-badge ${disaster.category}">${disaster.category === 'natural' ? 'Natural' : 'Non-Natural'}</span>
                        <i class="fas ${disaster.icon} placeholder-icon" style="color: ${disaster.color}"></i>
                    </div>
                    <div class="disaster-card-content">
                        <h3 class="disaster-card-title">
                            <i class="fas ${disaster.icon}" style="color: ${disaster.color}"></i>
                            ${disaster.name}
                        </h3>
                        <p class="disaster-card-description">${disaster.description}</p>
                        <div class="disaster-card-footer">
                            <span class="disaster-card-action">
                                Send Alert <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Load contact statistics
        async function loadContactStats() {
            try {
                const contactsResponse = await fetch('../api/emergency_api.php?action=list_contacts');
                const emergencyContacts = await contactsResponse.json();
                const studentsResponse = await fetch('../api/emergency_api.php?action=student_contacts');
                const studentContacts = await studentsResponse.json();

                const allContacts = [...emergencyContacts, ...studentContacts];
                const uniqueContacts = [];
                const seen = new Set();

                allContacts.forEach(c => {
                    const key = (c.phone_number || c.parent_phone || '') + '|' + (c.email || c.parent_email || '');
                    if (key !== '|' && !seen.has(key)) {
                        seen.add(key);
                        uniqueContacts.push(c);
                    }
                });

                const phoneCount = uniqueContacts.filter(c => c.phone_number || c.parent_phone).length;
                const emailCount = uniqueContacts.filter(c => c.email || c.parent_email).length;

                document.getElementById('totalContacts').textContent = uniqueContacts.length;
                document.getElementById('phoneContacts').textContent = phoneCount;
                document.getElementById('emailContacts').textContent = emailCount;

                const historyResponse = await fetch('../api/emergency_api.php?action=alert_history&limit=1000');
                const history = await historyResponse.json();
                document.getElementById('totalAlerts').textContent = history.length;

            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load alert history
        async function loadAlertHistory() {
            try {
                const response = await fetch('../api/emergency_api.php?action=alert_history');
                const history = await response.json();
                const container = document.getElementById('historyContent');

                if (history.length === 0) {
                    container.innerHTML = `
                        <div class="empty-history">
                            <i class="fas fa-inbox"></i>
                            <p>No emergency alerts have been sent yet.</p>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Emergency Type</th>
                                <th>Message</th>
                                <th>SMS</th>
                                <th>Email</th>
                                <th>Sent By</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${history.map(alert => `
                                <tr>
                                    <td>${new Date(alert.sent_at).toLocaleString()}</td>
                                    <td><strong>${alert.disaster_type.replace(/_/g, ' ')}</strong></td>
                                    <td>${alert.message.substring(0, 50)}${alert.message.length > 50 ? '...' : ''}</td>
                                    <td><span class="history-badge sms"><i class="fas fa-sms"></i> ${alert.sms_sent}</span></td>
                                    <td><span class="history-badge email"><i class="fas fa-envelope"></i> ${alert.email_sent}</span></td>
                                    <td>${alert.sent_by}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } catch (error) {
                console.error('Error loading history:', error);
            }
        }

        // Open modal
        function openModal(disasterId) {
            currentDisaster = disasters.find(d => d.id === disasterId);
            if (!currentDisaster) return;

            document.getElementById('modalTitle').innerHTML = `<i class="fas fa-exclamation-circle"></i> Send Emergency Alert`;
            document.getElementById('modalDisasterName').textContent = currentDisaster.name;
            document.getElementById('modalDisasterDesc').textContent = currentDisaster.description;

            const iconDiv = document.getElementById('modalIcon');
            iconDiv.style.background = currentDisaster.color;
            iconDiv.innerHTML = `<i class="fas ${currentDisaster.icon}"></i>`;

            document.getElementById('emergencyMessage').value = currentDisaster.defaultMessage;
            updateCharCount();

            document.getElementById('emergencyModal').classList.add('active');
        }

        // Close modal
        function closeModal() {
            document.getElementById('emergencyModal').classList.remove('active');
            currentDisaster = null;
        }

        // Update character count
        function updateCharCount() {
            const message = document.getElementById('emergencyMessage').value;
            document.getElementById('charCount').textContent = message.length;
        }

        // Send emergency alert
        async function sendEmergencyAlert() {
            if (!currentDisaster) return;

            const message = document.getElementById('emergencyMessage').value.trim();
            if (!message) {
                showToast('Error', 'Please enter an emergency message', 'error');
                return;
            }

            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<div class="spinner"></div> Sending...';

            try {
                const response = await fetch('../api/emergency_api.php?action=send_alert', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        disaster_type: currentDisaster.id,
                        message: message,
                        sent_by: 'Admin'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Alert Sent!', `SMS: ${result.sms_sent} | Email: ${result.email_sent} | Total: ${result.total_recipients} recipients`, 'success');
                    closeModal();
                    loadContactStats();
                    loadAlertHistory();
                } else {
                    showToast('Error', result.message || 'Failed to send alert', 'error');
                }
            } catch (error) {
                console.error('Error sending alert:', error);
                showToast('Error', 'An error occurred while sending the alert', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Alert';
            }
        }

        // Show toast notification
        function showToast(title, message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'toastSlideIn 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Close modal on outside click
        document.getElementById('emergencyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>

</html>