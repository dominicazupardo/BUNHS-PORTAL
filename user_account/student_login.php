<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login — BUNHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../assets/img/logo.jpg" type="image/x-icon">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: #f0f4f1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image:
                radial-gradient(circle at 15% 20%, rgba(82, 183, 136, .12) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(45, 106, 79, .10) 0%, transparent 45%);
        }

        /* ── Card — exact match of #svCard in Dashboard.php ── */
        .card {
            position: relative;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 28px 72px rgba(26, 58, 42, .24), 0 6px 20px rgba(26, 58, 42, .14);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }

        /* ── Hero banner ── */
        .banner {
            position: relative;
            background: #1a3a2a;
            padding: 30px 30px 24px;
            overflow: hidden;
        }

        .banner-grid {
            position: absolute;
            inset: 0;
            opacity: .04;
            background-image:
                linear-gradient(rgba(255, 255, 255, 1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 1) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .banner-badge {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(201, 168, 76, .18);
            border: 1px solid rgba(201, 168, 76, .38);
            color: #f0d98a;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            padding: 3px 11px;
            border-radius: 99px;
            margin-bottom: 8px;
        }

        .banner h1 {
            position: relative;
            z-index: 1;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 4px;
        }

        .banner p {
            position: relative;
            z-index: 1;
            font-size: 13px;
            color: rgba(255, 255, 255, .55);
            margin: 0;
        }

        /* ── Body ── */
        .card-body {
            padding: 26px 30px 30px;
        }

        /* ── Steps ── */
        #step1,
        #step2 {
            text-align: center;
        }

        #step2 {
            display: none;
        }

        /* ── Icon circle ── */
        .step-icon {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(82, 183, 136, .14), rgba(45, 106, 79, .08));
            border: 2px solid rgba(82, 183, 136, .28);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            color: #2d6a4f;
            margin: 0 auto 10px;
        }

        .step-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 17px;
            font-weight: 700;
            color: #1a3a2a;
            margin: 0 0 5px;
        }

        .step-sub {
            font-size: 12.5px;
            color: #6b7c72;
            margin: 0 0 18px;
        }

        /* ── Fields ── */
        .field {
            margin-bottom: 14px;
            text-align: left;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1a3a2a;
            margin-bottom: 6px;
            letter-spacing: .02em;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            padding: 11px 42px 11px 14px;
            border: 2px solid #dde8e2;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #1e2d24;
            background: #f8f5f0;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .input-wrap input:focus {
            border-color: #52b788;
            box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .18);
            background: #fff;
        }

        .toggle-pwd {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7c72;
            font-size: 14px;
            padding: 4px;
            transition: color .15s;
        }

        .toggle-pwd:hover {
            color: #1a3a2a;
        }

        /* ── Error boxes ── */
        .err-box {
            display: none;
            align-items: center;
            gap: 8px;
            background: #fdf1f1;
            border: 1px solid #f0d5d5;
            border-left: 3px solid #e53935;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #b94040;
            text-align: left;
        }

        .err-box.show {
            display: flex;
        }

        /* ── Primary button ── */
        .btn-primary {
            width: 100%;
            padding: 13px 20px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #3a8c6a, #1a3a2a);
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(26, 58, 42, .3);
            transition: transform .15s, box-shadow .15s, opacity .15s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(26, 58, 42, .35);
        }

        .btn-primary:active {
            transform: scale(.98);
        }

        .btn-primary:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Timer pill ── */
        .timer-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 13px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            background: #fdf6ec;
            border: 1px solid #f0e4cc;
            color: #8b5e1a;
            margin-bottom: 16px;
            transition: background .3s, border-color .3s, color .3s;
        }

        /* ── OTP boxes ── */
        #otpBoxes {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 16px;
        }

        .bm-otp-box {
            width: 46px;
            height: 54px;
            border-radius: 10px;
            border: 2px solid #dde8e2;
            background: #f8f5f0;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            color: #1e2d24;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            caret-color: transparent;
        }

        /* ── Resend + Back row ── */
        .otp-actions {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .otp-actions button {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 12.5px;
            color: #6b7c72;
            font-weight: 600;
            transition: color .2s;
        }

        .otp-actions button:hover {
            color: #1a3a2a;
        }

        .otp-actions button:disabled {
            cursor: not-allowed;
            opacity: .5;
        }

        .otp-actions .sep {
            color: #dde8e2;
            font-size: 14px;
        }

        /* ── Home link ── */
        .home-link {
            margin-top: 18px;
            font-size: 12.5px;
            color: #6b7c72;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color .15s;
        }

        .home-link:hover {
            color: #1a3a2a;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 22px 20px 26px;
            }

            .banner {
                padding: 24px 20px 20px;
            }

            .bm-otp-box {
                width: 40px;
                height: 48px;
                font-size: 18px;
            }

            #otpBoxes {
                gap: 6px;
            }
        }
    </style>
</head>

<body>

    <div class="card">

        <!-- Banner — pixel-perfect copy of Dashboard.php svCard banner -->
        <div class="banner">
            <div class="banner-grid"></div>
            <div class="banner-badge">
                <i class="fas fa-shield-alt"></i> Student Sign In
            </div>
            <h1>Two-Step Verification</h1>
            <p>Sign in to your BUNHS student account.</p>
        </div>

        <div class="card-body">

            <!-- ══ Step 1: Credentials ══ -->
            <div id="step1">
                <div class="step-icon"><i class="fas fa-user-graduate"></i></div>
                <p class="step-title">Enter your credentials</p>
                <p class="step-sub">We'll send a verification code to your registered contact.</p>

                <div class="err-box" id="s1Err">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="s1ErrTxt"></span>
                </div>

                <div class="field">
                    <label for="usernameInput">Username / Student ID</label>
                    <div class="input-wrap">
                        <input type="text" id="usernameInput" name="username"
                            placeholder="Enter your username"
                            autocomplete="username">
                    </div>
                </div>

                <div class="field" style="margin-bottom:20px;">
                    <label for="passwordInput">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="passwordInput" name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password">
                        <button type="button" class="toggle-pwd" id="togglePwd" tabindex="-1">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button class="btn-primary" id="sendCodeBtn">
                    <i class="fas fa-paper-plane"></i>&ensp;Send Verification Code
                </button>
            </div>

            <!-- ══ Step 2: OTP entry ══ -->
            <div id="step2">
                <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
                <p class="step-title">Enter Verification Code</p>
                <p class="step-sub" id="otpSubtitle">Enter the 6-digit code sent to your registered contact.</p>

                <div class="timer-pill" id="timerPill">
                    <i class="fas fa-clock"></i>
                    <span id="timerVal">05:00</span>
                </div>

                <div id="otpBoxes">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <input type="hidden" id="otpHidden">

                <div class="err-box" id="s2Err">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="s2ErrTxt"></span>
                </div>

                <button class="btn-primary" id="verifyBtn">
                    <i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in
                </button>

                <div class="otp-actions">
                    <button id="resendBtn" disabled>
                        Resend · <span id="resendCountdown">30</span>s
                    </button>
                    <span class="sep">|</span>
                    <button id="backBtn">
                        <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back
                    </button>
                </div>
            </div>

        </div>
    </div>

    <a href="../index.php" class="home-link">
        <i class="fas fa-arrow-left"></i> Back to home
    </a>

    <script>
        (function() {
            'use strict';

            const ENDPOINT = '../login_otp.php';

            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const s1Err = document.getElementById('s1Err');
            const s1ErrTxt = document.getElementById('s1ErrTxt');
            const s2Err = document.getElementById('s2Err');
            const s2ErrTxt = document.getElementById('s2ErrTxt');
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            const verifyBtn = document.getElementById('verifyBtn');
            const resendBtn = document.getElementById('resendBtn');
            const backBtn = document.getElementById('backBtn');
            const timerPill = document.getElementById('timerPill');
            const timerVal = document.getElementById('timerVal');
            const subtitle = document.getElementById('otpSubtitle');
            const usernameInput = document.getElementById('usernameInput');
            const passwordInput = document.getElementById('passwordInput');

            let _timerID = null;
            let _resendID = null;
            let _busy = false;

            // ── Step switcher ─────────────────────────────────────────
            function showStep(n) {
                step1.style.display = n === 1 ? 'block' : 'none';
                step2.style.display = n === 2 ? 'block' : 'none';
            }

            // ── Error helpers ─────────────────────────────────────────
            function showS1Err(msg) {
                s1ErrTxt.textContent = msg;
                s1Err.classList.add('show');
            }

            function hideS1Err() {
                s1Err.classList.remove('show');
            }

            function showS2Err(msg) {
                s2ErrTxt.textContent = msg;
                s2Err.classList.add('show');
            }

            function hideS2Err() {
                s2Err.classList.remove('show');
            }

            function setLoad(btn, on, loadingHtml, idleHtml) {
                btn.disabled = on;
                btn.style.opacity = on ? '.7' : '1';
                btn.innerHTML = on ? loadingHtml : idleHtml;
            }

            // ── OTP box wiring — identical to Dashboard.php wireOtpBoxes ──
            function wireOtpBoxes() {
                const boxes = Array.from(document.querySelectorAll('#otpBoxes .bm-otp-box'));
                const hid = document.getElementById('otpHidden');

                function sync() {
                    hid.value = boxes.map(b => b.value).join('');
                }

                boxes.forEach((box, i) => {
                    box.addEventListener('input', () => {
                        box.value = box.value.replace(/\D/g, '').slice(-1);
                        sync();
                        if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('keydown', e => {
                        if (e.key === 'Backspace' && !box.value && i > 0) {
                            boxes[i - 1].value = '';
                            boxes[i - 1].focus();
                            sync();
                        }
                        if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                        if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('paste', e => {
                        e.preventDefault();
                        const text = (e.clipboardData || window.clipboardData)
                            .getData('text').replace(/\D/g, '').slice(0, 6);
                        text.split('').forEach((ch, j) => {
                            if (boxes[j]) boxes[j].value = ch;
                        });
                        sync();
                        boxes[Math.min(text.length, boxes.length - 1)].focus();
                    });
                    box.addEventListener('keypress', e => {
                        if (!/\d/.test(e.key)) e.preventDefault();
                    });
                    box.addEventListener('focus', () => {
                        box.style.borderColor = '#52b788';
                        box.style.boxShadow = '0 0 0 3.5px rgba(82,183,136,.18)';
                        box.style.background = '#fff';
                    });
                    box.addEventListener('blur', () => {
                        box.style.borderColor = box.value ? '#2d6a4f' : '#dde8e2';
                        box.style.boxShadow = '';
                        box.style.background = box.value ? 'rgba(45,106,79,.06)' : '#f8f5f0';
                    });
                });
            }

            function clearOtpBoxes() {
                document.querySelectorAll('#otpBoxes .bm-otp-box').forEach(b => {
                    b.value = '';
                    b.classList.remove('is-filled', 'is-error');
                    b.style.borderColor = '#dde8e2';
                    b.style.boxShadow = '';
                    b.style.background = '#f8f5f0';
                });
                document.getElementById('otpHidden').value = '';
            }

            function shakeOtpBoxes() {
                document.querySelectorAll('#otpBoxes .bm-otp-box').forEach(b => {
                    b.style.borderColor = '#e53935';
                    b.classList.add('is-error');
                    setTimeout(() => {
                        b.classList.remove('is-error');
                        b.style.borderColor = '#dde8e2';
                    }, 420);
                });
            }

            // ── Timer — identical to Dashboard.php startTimer ─────────
            function startTimer() {
                clearInterval(_timerID);
                let rem = 300;

                function tick() {
                    const m = String(Math.floor(rem / 60)).padStart(2, '0');
                    const s = String(rem % 60).padStart(2, '0');
                    timerVal.textContent = m + ':' + s;
                    const urgent = rem <= 60;
                    timerPill.style.background = urgent ? '#fff1f0' : '#fdf6ec';
                    timerPill.style.borderColor = urgent ? '#ffd0cc' : '#f0e4cc';
                    timerPill.style.color = urgent ? '#c62828' : '#8b5e1a';
                    if (rem-- > 0) _timerID = setTimeout(tick, 1000);
                }
                tick();
            }

            // ── Resend countdown — identical to Dashboard.php ─────────
            function startResendCountdown() {
                resendBtn.disabled = true;
                resendBtn.style.opacity = '.5';
                resendBtn.innerHTML = 'Resend · <span id="resendCountdown">30</span>s';
                let rem = 30;
                _resendID = setInterval(() => {
                    rem--;
                    const el = document.getElementById('resendCountdown');
                    if (el) el.textContent = rem;
                    if (rem <= 0) {
                        clearInterval(_resendID);
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        resendBtn.innerHTML = 'Resend code';
                        resendBtn.classList.add('on');
                    }
                }, 1000);
            }

            // ══ STEP 1 — credentials → send OTP ═══════════════════════
            sendCodeBtn.addEventListener('click', () => {
                hideS1Err();
                const username = (usernameInput.value || '').trim();
                const password = (passwordInput.value || '').trim();

                if (!username || !password) {
                    showS1Err('Please enter your username and password.');
                    return;
                }

                setLoad(sendCodeBtn, true,
                    '<i class="fas fa-spinner fa-spin"></i>&ensp;Sending…',
                    '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');

                const fd = new FormData();
                fd.append('action', 'login_verify_credentials');
                fd.append('username', username);
                fd.append('password', password);

                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        setLoad(sendCodeBtn, false,
                            '', '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');
                        if (d.success) {
                            if (d.masked_contact) subtitle.textContent = 'Code sent to: ' + d.masked_contact;
                            if (d.dev_otp) console.log('[DEV] Login OTP:', d.dev_otp);
                            showStep(2);
                            clearOtpBoxes();
                            startTimer();
                            startResendCountdown();
                            hideS2Err();
                            setTimeout(() => {
                                const first = document.querySelector('#otpBoxes .bm-otp-box');
                                if (first) first.focus();
                            }, 200);
                        } else {
                            showS1Err(d.message || 'Invalid username or password.');
                        }
                    })
                    .catch(() => {
                        setLoad(sendCodeBtn, false,
                            '', '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');
                        showS1Err('Connection error. Please check your internet and try again.');
                    });
            });

            passwordInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') sendCodeBtn.click();
            });

            document.getElementById('togglePwd').addEventListener('click', function() {
                const inp = passwordInput;
                const icon = document.getElementById('eyeIcon');
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });

            // ══ STEP 2 — verify OTP ════════════════════════════════════
            verifyBtn.addEventListener('click', () => {
                if (_busy) return;
                hideS2Err();
                const otp = document.getElementById('otpHidden').value;
                if (otp.length !== 6) {
                    shakeOtpBoxes();
                    showS2Err('Please enter all 6 digits.');
                    return;
                }
                _busy = true;
                setLoad(verifyBtn, true,
                    '<i class="fas fa-spinner fa-spin"></i>&ensp;Verifying…',
                    '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');

                const fd = new FormData();
                fd.append('action', 'login_verify_otp');
                fd.append('otp', otp);

                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        setLoad(verifyBtn, false,
                            '', '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');
                        _busy = false;
                        if (d.success) {
                            clearInterval(_timerID);
                            window.location.href = 'Dashboard.php';
                        } else {
                            shakeOtpBoxes();
                            showS2Err(d.message || 'Invalid code. Please try again.');
                            clearOtpBoxes();
                            const first = document.querySelector('#otpBoxes .bm-otp-box');
                            if (first) first.focus();
                        }
                    })
                    .catch(() => {
                        setLoad(verifyBtn, false,
                            '', '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');
                        _busy = false;
                        showS2Err('Connection error. Please check your internet and try again.');
                    });
            });

            // ── Resend ────────────────────────────────────────────────
            resendBtn.addEventListener('click', () => {
                resendBtn.disabled = true;
                resendBtn.classList.remove('on');

                const fd = new FormData();
                fd.append('action', 'login_resend_otp');

                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            clearOtpBoxes();
                            startTimer();
                            startResendCountdown();
                            hideS2Err();
                            if (d.masked_contact) subtitle.textContent = 'New code sent to ' + d.masked_contact;
                            // Green flash — identical to Dashboard.php resend success
                            s2ErrTxt.textContent = '✓ ' + (d.message || 'New code sent.');
                            s2Err.style.background = 'rgba(82,183,136,.08)';
                            s2Err.style.borderColor = 'rgba(82,183,136,.3)';
                            s2Err.style.borderLeft = '3px solid #2d6a4f';
                            s2Err.style.color = '#2d6a4f';
                            s2Err.style.display = 'flex';
                            if (d.dev_otp) console.log('[DEV] Resend OTP:', d.dev_otp);
                            setTimeout(() => {
                                hideS2Err();
                                s2Err.style.cssText = '';
                            }, 3500);
                            setTimeout(() => {
                                const first = document.querySelector('#otpBoxes .bm-otp-box');
                                if (first) first.focus();
                            }, 150);
                        } else {
                            resendBtn.disabled = false;
                            resendBtn.style.opacity = '1';
                            showS2Err(d.message || 'Failed to resend. Please try again.');
                        }
                    })
                    .catch(() => {
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        showS2Err('Connection error. Could not resend code.');
                    });
            });

            // ── Back ──────────────────────────────────────────────────
            backBtn.addEventListener('click', () => {
                clearInterval(_timerID);
                clearInterval(_resendID);
                clearOtpBoxes();
                hideS2Err();
                showStep(1);
                setTimeout(() => usernameInput.focus(), 150);
            });

            // ── Init ──────────────────────────────────────────────────
            wireOtpBoxes();
            showStep(1);

        })();
    </script>
</body>

</html>