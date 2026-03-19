<!-- Notification Preference Modal -->
<style>
    .notification-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }

    .notification-modal-overlay.active {
        display: flex;
    }

    .notification-modal {
        background: white;
        border-radius: 20px;
        width: 95%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideUp 0.4s ease;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .notification-modal-header {
        background: linear-gradient(135deg, #8a9a5b 0%, #10b981 100%);
        color: white;
        padding: 28px;
        border-radius: 20px 20px 0 0;
        text-align: center;
    }

    .notification-modal-header .icon {
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 32px;
    }

    .notification-modal-header h2 {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .notification-modal-header p {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
    }

    .notification-modal-body {
        padding: 28px;
    }

    .notification-option {
        display: flex;
        align-items: center;
        padding: 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .notification-option:hover {
        border-color: #8a9a5b;
        background: #f9fafb;
    }

    .notification-option.selected {
        border-color: #8a9a5b;
        background: rgba(138, 154, 91, 0.08);
    }

    .notification-option input[type="radio"] {
        display: none;
    }

    .notification-option .option-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        font-size: 22px;
        color: white;
        flex-shrink: 0;
    }

    .notification-option.phone .option-icon {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .notification-option.email .option-icon {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .notification-option.both .option-icon {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .notification-option .option-content {
        flex: 1;
    }

    .notification-option .option-title {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 2px;
    }

    .notification-option .option-desc {
        font-size: 12px;
        color: #6b7280;
    }

    .notification-option .check-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        transition: all 0.2s;
    }

    .notification-option.selected .check-icon {
        background: #8a9a5b;
        border-color: #8a9a5b;
    }

    /* Contact Details Form */
    .contact-details-form {
        margin-top: 20px;
        padding: 20px;
        background: #f9fafb;
        border-radius: 12px;
        display: none;
    }

    .contact-details-form.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 13px;
        color: #374151;
        margin-bottom: 6px;
    }

    .form-group input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }

    .form-group input:focus {
        outline: none;
        border-color: #8a9a5b;
    }

    .form-group input.error {
        border-color: #ef4444;
    }

    .error-message {
        color: #ef4444;
        font-size: 12px;
        margin-top: 4px;
        display: none;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .notification-modal-footer {
        padding: 0 28px 28px;
        display: flex;
        gap: 12px;
    }

    .btn-notify {
        flex: 1;
        padding: 14px 24px;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-notify.primary {
        background: linear-gradient(135deg, #8a9a5b 0%, #10b981 100%);
        color: white;
    }

    .btn-notify.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(138, 154, 91, 0.4);
    }

    .btn-notify.primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-notify.secondary {
        background: #f3f4f6;
        color: #4b5563;
    }

    .btn-notify.secondary:hover {
        background: #e5e7eb;
    }

    /* Success State */
    .notification-success {
        text-align: center;
        padding: 20px;
    }

    .notification-success .success-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 40px;
        color: white;
        animation: successPop 0.5s ease;
    }

    @keyframes successPop {
        0% {
            transform: scale(0);
        }

        50% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    .notification-success h3 {
        color: #1f2937;
        margin-bottom: 8px;
    }

    .notification-success p {
        color: #6b7280;
        font-size: 14px;
    }

    /* Spinner */
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
</style>

<div class="notification-modal-overlay" id="notificationPreferenceModal">
    <div class="notification-modal">
        <div class="notification-modal-header">
            <div class="icon">
                <i class="fas fa-bell"></i>
            </div>
            <h2>Emergency Notifications</h2>
            <p>Would you like to receive emergency alerts from BUNHS?</p>
        </div>

        <div class="notification-modal-body" id="preferenceBody">
            <!-- Step 1: Choose preference -->
            <div id="preferenceStep">
                <div class="notification-option phone" onclick="selectPreference('phone')">
                    <input type="radio" name="preference" value="phone">
                    <div class="option-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="option-content">
                        <div class="option-title">SMS Notification</div>
                        <div class="option-desc">Receive alerts via text message</div>
                    </div>
                    <div class="check-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>

                <div class="notification-option email" onclick="selectPreference('email')">
                    <input type="radio" name="preference" value="email">
                    <div class="option-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="option-content">
                        <div class="option-title">Email Notification</div>
                        <div class="option-desc">Receive alerts via email</div>
                    </div>
                    <div class="check-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>

                <div class="notification-option both" onclick="selectPreference('both')">
                    <input type="radio" name="preference" value="both">
                    <div class="option-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="option-content">
                        <div class="option-title">Both</div>
                        <div class="option-desc">Receive alerts via SMS and Email</div>
                    </div>
                    <div class="check-icon">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            </div>

            <!-- Step 2: Enter contact details -->
            <div class="contact-details-form" id="contactForm">
                <div class="form-row" id="phoneRow">
                    <div class="form-group">
                        <label for="notifyPhone">Phone Number</label>
                        <input type="tel" id="notifyPhone" placeholder="09123456789">
                        <div class="error-message" id="phoneError">Please enter a valid phone number</div>
                    </div>
                </div>
                <div class="form-row" id="emailRow">
                    <div class="form-group">
                        <label for="notifyEmail">Email Address</label>
                        <input type="email" id="notifyEmail" placeholder="email@example.com">
                        <div class="error-message" id="emailError">Please enter a valid email address</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success State -->
        <div class="notification-modal-body" id="successBody" style="display: none;">
            <div class="notification-success">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3>You're All Set!</h3>
                <p>You will now receive emergency notifications from Buyoan National High School.</p>
            </div>
        </div>

        <div class="notification-modal-footer">
            <button class="btn-notify secondary" onclick="skipNotification()" id="skipBtn">Skip for Now</button>
            <button class="btn-notify primary" onclick="saveNotificationPreference()" id="saveBtn" disabled>
                <span id="btnText">Save Preference</span>
            </button>
        </div>
    </div>
</div>

<script>
    let selectedPreference = null;

    function initNotificationModal() {
        // Check if student is logged in (session exists)
        fetch('api/student_notification_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_preference'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && !data.has_preference) {
                    // Show modal if no preference set
                    setTimeout(() => {
                        document.getElementById('notificationPreferenceModal').classList.add('active');
                    }, 1000);
                }
            })
            .catch(error => console.log('Notification modal check skipped'));
    }

    function selectPreference(preference) {
        selectedPreference = preference;

        // Update UI
        document.querySelectorAll('.notification-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        document.querySelector(`.notification-option.${preference}`).classList.add('selected');

        // Show/hide contact form
        const contactForm = document.getElementById('contactForm');
        const phoneRow = document.getElementById('phoneRow');
        const emailRow = document.getElementById('emailRow');
        const saveBtn = document.getElementById('saveBtn');

        if (preference === 'phone') {
            contactForm.classList.add('active');
            phoneRow.style.display = 'block';
            emailRow.style.display = 'none';
            saveBtn.disabled = false;
        } else if (preference === 'email') {
            contactForm.classList.add('active');
            phoneRow.style.display = 'none';
            emailRow.style.display = 'block';
            saveBtn.disabled = false;
        } else if (preference === 'both') {
            contactForm.classList.add('active');
            phoneRow.style.display = 'block';
            emailRow.style.display = 'block';
            saveBtn.disabled = false;
        }
    }

    function saveNotificationPreference() {
        if (!selectedPreference) return;

        const saveBtn = document.getElementById('saveBtn');
        const btnText = document.getElementById('btnText');
        saveBtn.disabled = true;
        btnText.innerHTML = '<div class="spinner"></div> Saving...';

        const phone = document.getElementById('notifyPhone').value.trim();
        const email = document.getElementById('notifyEmail').value.trim();

        // Validate
        let hasError = false;

        if (selectedPreference === 'phone' || selectedPreference === 'both') {
            if (!phone || phone.length < 11) {
                document.getElementById('notifyPhone').classList.add('error');
                document.getElementById('phoneError').style.display = 'block';
                hasError = true;
            } else {
                document.getElementById('notifyPhone').classList.remove('error');
                document.getElementById('phoneError').style.display = 'none';
            }
        }

        if (selectedPreference === 'email' || selectedPreference === 'both') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                document.getElementById('notifyEmail').classList.add('error');
                document.getElementById('emailError').style.display = 'block';
                hasError = true;
            } else {
                document.getElementById('notifyEmail').classList.remove('error');
                document.getElementById('emailError').style.display = 'none';
            }
        }

        if (hasError) {
            saveBtn.disabled = false;
            btnText.textContent = 'Save Preference';
            return;
        }

        // Save preference
        fetch('api/student_notification_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save_preference',
                    preference: selectedPreference,
                    phone: phone,
                    email: email
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess();
                } else {
                    alert(data.message || 'Failed to save preference');
                    saveBtn.disabled = false;
                    btnText.textContent = 'Save Preference';
                }
            })
            .catch(error => {
                console.error('Error saving preference:', error);
                alert('An error occurred. Please try again.');
                saveBtn.disabled = false;
                btnText.textContent = 'Save Preference';
            });
    }

    function skipNotification() {
        fetch('api/student_notification_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'skip'
                })
            })
            .then(response => response.json())
            .then(() => {
                closeModal();
            });
    }

    function showSuccess() {
        document.getElementById('preferenceBody').style.display = 'none';
        document.getElementById('successBody').style.display = 'block';
        document.getElementById('skipBtn').style.display = 'none';
        document.getElementById('saveBtn').style.display = 'none';

        setTimeout(() => {
            closeModal();
        }, 2500);
    }

    function closeModal() {
        document.getElementById('notificationPreferenceModal').classList.remove('active');
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initNotificationModal);
</script>