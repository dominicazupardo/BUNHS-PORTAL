<?php

/**
 * BUNHS — Notification Preference Modal
 * ─────────────────────────────────────────────────────────────────────
 * Fully self-contained — ZERO Bootstrap dependency.
 * Uses a custom overlay + CSS animations; no external JS frameworks.
 *
 * Bugs fixed vs. previous version:
 *   1. Bootstrap.Modal removed — replaced with native overlay logic
 *   2. JS syntax error in setStepActive() (unescaped apostrophe) — fixed
 *   3. OTP boxes changed from 6 → 5 to match otp_api.php
 *   4. initOtpRow() now clones nodes to prevent duplicate event listeners
 *   5. Skip now uses localStorage (persists across refreshes) + DB write
 *   6. "Join Now" (?show_notif=1) clears localStorage before PHP check
 *   7. Overlay click-through / z-index issues eliminated
 *
 * PHP variables expected (set in Dashboard.php before include):
 *   $show_notif_modal  bool   — true when DB has no preference yet
 *                               AND session has not dismissed
 * ─────────────────────────────────────────────────────────────────────
 */
?>

<!-- ═══════════════════════════════════════════════════════
     GOOGLE FONTS  (only if not already loaded by parent)
     ═══════════════════════════════════════════════════════ -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
    /* ═══════════════════════════════════════════════════════════
   DESIGN TOKENS
   ═══════════════════════════════════════════════════════════ */
    :root {
        --nm-forest: #1a3a2a;
        --nm-green: #2d6a4f;
        --nm-mint: #52b788;
        --nm-sage: #b7e4c7;
        --nm-cream: #f8f5f0;
        --nm-warm: #fdf6ec;
        --nm-gold: #c9a84c;
        --nm-gold-lt: #f0d98a;
        --nm-ink: #1e2d24;
        --nm-muted: #6b7c72;
        --nm-border: #dde8e2;
        --nm-danger: #e53935;
        --nm-shadow: 0 28px 72px rgba(26, 58, 42, .24), 0 6px 20px rgba(26, 58, 42, .14);
        --nm-r: 20px;
        --nm-r-sm: 12px;
        --nm-font: 'DM Sans', 'Segoe UI', system-ui, sans-serif;
        --nm-display: 'Playfair Display', Georgia, serif;
    }

    /* ═══════════════════════════════════════════════════════════
   OVERLAY  — full-screen dimmed backdrop
   ═══════════════════════════════════════════════════════════ */
    #nmOverlay {
        /* Hidden by default */
        position: fixed;
        inset: 0;
        z-index: 99999;

        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;

        background: rgba(8, 20, 14, 0.65);
        backdrop-filter: blur(7px);
        -webkit-backdrop-filter: blur(7px);

        /* Animate in/out */
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease, visibility .3s ease;
    }

    #nmOverlay.nm-open {
        opacity: 1;
        visibility: visible;
    }

    /* Blur & disable the page content behind the modal */
    body.nm-modal-open .main,
    body.nm-modal-open #nav-placeholder,
    body.nm-modal-open aside,
    body.nm-modal-open nav {
        filter: blur(5px);
        pointer-events: none;
        user-select: none;
        transition: filter .3s ease;
    }

    body:not(.nm-modal-open) .main,
    body:not(.nm-modal-open) #nav-placeholder,
    body:not(.nm-modal-open) aside,
    body:not(.nm-modal-open) nav {
        filter: none;
        pointer-events: auto;
        user-select: auto;
        transition: filter .3s ease;
    }

    /* ═══════════════════════════════════════════════════════════
   MODAL CARD
   ═══════════════════════════════════════════════════════════ */
    #nmCard {
        position: relative;
        background: #ffffff;
        border-radius: var(--nm-r);
        box-shadow: var(--nm-shadow);
        width: 100%;
        max-width: 468px;
        max-height: 92dvh;
        overflow-y: auto;
        overflow-x: hidden;
        font-family: var(--nm-font);

        /* Entry animation */
        transform: translateY(28px) scale(.95);
        transition: transform .38s cubic-bezier(.34, 1.28, .64, 1);

        scrollbar-width: thin;
        scrollbar-color: var(--nm-sage) transparent;
    }

    #nmCard::-webkit-scrollbar {
        width: 4px;
    }

    #nmCard::-webkit-scrollbar-thumb {
        background: var(--nm-sage);
        border-radius: 99px;
    }

    #nmOverlay.nm-open #nmCard {
        transform: translateY(0) scale(1);
    }

    /* ═══════════════════════════════════════════════════════════
   HERO HEADER
   ═══════════════════════════════════════════════════════════ */
    .nm-hero {
        position: relative;
        background: var(--nm-forest);
        padding: 30px 30px 24px;
        overflow: hidden;
    }

    .nm-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 70% 60% at 85% 15%, rgba(82, 183, 136, .28) 0%, transparent 60%),
            radial-gradient(ellipse 50% 40% at 5% 90%, rgba(201, 168, 76, .20) 0%, transparent 55%);
        pointer-events: none;
    }

    .nm-hero-grid {
        position: absolute;
        inset: 0;
        opacity: .04;
        background-image:
            linear-gradient(rgba(255, 255, 255, 1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 1) 1px, transparent 1px);
        background-size: 28px 28px;
        pointer-events: none;
    }

    .nm-hero-badge {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(201, 168, 76, .18);
        border: 1px solid rgba(201, 168, 76, .38);
        color: var(--nm-gold-lt);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .1em;

        padding: 3px 10px;
        border-radius: 99px;
        margin-bottom: 10px;
    }

    .nm-hero h2 {
        position: relative;
        z-index: 1;
        font-family: var(--nm-display);
        font-size: 21px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 5px;
        transition: color .25s;
    }

    .nm-hero p {
        position: relative;
        z-index: 1;
        font-size: 13px;
        color: rgba(255, 255, 255, .54);
        margin: 0;
        transition: color .25s;
    }

    /* ── Step indicator ──────────────────────────────────────── */
    .nm-steps {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 20px;
    }

    .nm-step {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .06em;

        color: rgba(255, 255, 255, .3);
        transition: color .3s;
    }

    .nm-step.active {
        color: rgba(255, 255, 255, .88);
    }

    .nm-step.done {
        color: rgba(82, 183, 136, .75);
    }

    .nm-step-dot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 800;
        background: rgba(255, 255, 255, .1);
        color: rgba(255, 255, 255, .3);
        border: 1.5px solid rgba(255, 255, 255, .16);
        transition: all .3s;
        flex-shrink: 0;
    }

    .nm-step.active .nm-step-dot {
        background: var(--nm-mint);
        color: var(--nm-forest);
        border-color: var(--nm-mint);
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .28);
    }

    .nm-step.done .nm-step-dot {
        background: rgba(82, 183, 136, .22);
        color: var(--nm-mint);
        border-color: rgba(82, 183, 136, .45);
    }

    .nm-step-line {
        width: 28px;
        height: 1.5px;
        background: rgba(255, 255, 255, .13);
        margin: 0 6px;
    }

    /* ═══════════════════════════════════════════════════════════
   BODY
   ═══════════════════════════════════════════════════════════ */
    .nm-body {
        padding: 24px 28px 28px;
        background: #fff;
        position: relative;
    }

    /* ═══════════════════════════════════════════════════════════
   STEP PANELS  — only one visible at a time
   ═══════════════════════════════════════════════════════════ */
    .nm-panel {
        display: none;
    }

    .nm-panel.nm-active {
        display: block;
        animation: nmSlideIn .28s cubic-bezier(.25, .46, .45, .94) both;
    }

    .nm-panel.nm-back {
        display: block;
        animation: nmSlideBack .28s cubic-bezier(.25, .46, .45, .94) both;
    }

    @keyframes nmSlideIn {
        from {
            opacity: 0;
            transform: translateX(20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes nmSlideBack {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* ═══════════════════════════════════════════════════════════
   METHOD SELECTION CARDS (Step 1)
   ═══════════════════════════════════════════════════════════ */
    .nm-method-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
        margin-bottom: 8px;
    }

    .nm-method-opt {
        position: relative;
    }

    .nm-method-opt input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .nm-method-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px 12px 14px;
        border: 2px solid var(--nm-border);
        border-radius: var(--nm-r-sm);
        cursor: pointer;
        color: var(--nm-muted);
        background: var(--nm-cream);
        transition: border-color .2s, background .2s, box-shadow .2s, color .2s;
        user-select: none;
        -webkit-user-select: none;
        text-align: center;
        -webkit-tap-highlight-color: transparent;
    }

    .nm-method-card:hover {
        border-color: var(--nm-mint);
        background: rgba(82, 183, 136, .05);
        color: var(--nm-green);
    }

    /* Selected state driven purely by CSS :has() where supported,
   and by JS .nm-sel class as fallback */
    .nm-method-opt input:checked~.nm-method-card,
    .nm-method-card.nm-sel {
        border-color: var(--nm-mint);
        color: var(--nm-green);
        background: rgba(82, 183, 136, .08);
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .14);
    }

    .nm-method-card .nm-mc-icon {
        font-size: 22px;
        transition: transform .2s, color .2s;
    }

    .nm-method-opt input:checked~.nm-method-card .nm-mc-icon,
    .nm-method-card.nm-sel .nm-mc-icon {
        transform: scale(1.16);
        color: var(--nm-green);
    }

    .nm-method-card .nm-mc-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;

    }

    .nm-method-card .nm-mc-sub {
        font-size: 11px;
        color: var(--nm-muted);
        font-weight: 400;
        text-transform: none;
        letter-spacing: 0;
        line-height: 1.3;
    }

    /* ── Both card ───────────────────────────────────────────── */
    .nm-both-opt {
        position: relative;
        margin-bottom: 14px;
    }

    .nm-both-opt input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .nm-both-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--nm-border);
        border-radius: var(--nm-r-sm);
        cursor: pointer;
        color: var(--nm-muted);
        background: var(--nm-cream);
        font-size: 13px;
        font-weight: 600;
        transition: border-color .2s, background .2s, box-shadow .2s, color .2s;
        user-select: none;
        -webkit-user-select: none;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    .nm-both-card:hover {
        border-color: var(--nm-mint);
        background: rgba(82, 183, 136, .05);
        color: var(--nm-green);
    }

    .nm-both-opt input:checked~.nm-both-card,
    .nm-both-card.nm-sel {
        border-color: var(--nm-mint);
        color: var(--nm-green);
        background: rgba(82, 183, 136, .08);
        box-shadow: 0 0 0 3px rgba(82, 183, 136, .14);
    }

    /* ═══════════════════════════════════════════════════════════
   DIVIDER
   ═══════════════════════════════════════════════════════════ */
    .nm-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 6px 0 10px;
        font-size: 11.5px;
        color: var(--nm-muted);
    }

    .nm-divider::before,
    .nm-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--nm-border);
    }

    /* ═══════════════════════════════════════════════════════════
   FORM FIELDS
   ═══════════════════════════════════════════════════════════ */
    .nm-field {
        margin-bottom: 14px;
    }

    .nm-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .07em;
        color: var(--nm-ink);

        margin-bottom: 6px;
    }

    .nm-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .nm-field-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--nm-muted);
        font-size: 13px;
        pointer-events: none;
        z-index: 2;
        transition: color .2s;
    }

    .nm-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 13px 12px 38px;
        font-family: var(--nm-font);
        font-size: 13.5px;
        color: var(--nm-ink);
        background: var(--nm-cream);
        border: 1.5px solid var(--nm-border);
        border-radius: var(--nm-r-sm);
        outline: none;
        -webkit-appearance: none;
        appearance: none;
        transition: border-color .2s, background .2s, box-shadow .2s;
    }

    .nm-input::placeholder {
        color: #b5c0bb;
        font-size: 13px;
    }

    .nm-input:focus {
        border-color: var(--nm-mint);
        background: #fff;
        box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .15);
    }

    .nm-input-wrap:focus-within .nm-field-icon {
        color: var(--nm-green);
    }

    .nm-input.nm-field-err {
        border-color: var(--nm-danger);
        background: #fff8f8;
    }

    /* ═══════════════════════════════════════════════════════════
   INFO NOTE
   ═══════════════════════════════════════════════════════════ */
    .nm-note {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 9px 13px;
        margin-bottom: 14px;
        background: rgba(82, 183, 136, .07);
        border: 1px solid rgba(82, 183, 136, .22);
        border-radius: 8px;
        font-size: 12px;
        color: var(--nm-green);
        line-height: 1.5;
    }

    .nm-note i {
        flex-shrink: 0;
        margin-top: 1px;
    }

    /* ═══════════════════════════════════════════════════════════
   ERROR STRIP
   ═══════════════════════════════════════════════════════════ */
    .nm-err {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 9px 13px;
        margin-bottom: 12px;
        background: #fff1f0;
        border: 1px solid #ffd0cc;
        border-left: 3px solid var(--nm-danger);
        border-radius: 8px;
        color: #c62828;
        font-size: 12.5px;
        font-weight: 500;
        line-height: 1.4;
    }

    .nm-err.show {
        display: flex;
    }

    /* ═══════════════════════════════════════════════════════════
   PRIMARY BUTTON
   ═══════════════════════════════════════════════════════════ */
    .nm-btn {
        width: 100%;
        padding: 13px 20px;
        margin-top: 6px;
        font-family: var(--nm-font);
        font-size: 13.5px;
        font-weight: 700;
        letter-spacing: .03em;
        color: #fff;
        background: linear-gradient(135deg, #3a8c6a 0%, var(--nm-forest) 100%);
        border: none;
        border-radius: var(--nm-r-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 18px rgba(26, 58, 42, .3);
        position: relative;
        overflow: hidden;
        transition: transform .15s, box-shadow .15s, opacity .2s;
        -webkit-tap-highlight-color: transparent;
    }

    .nm-btn::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, .13) 0%, transparent 60%);
        pointer-events: none;
    }

    .nm-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(26, 58, 42, .38);
    }

    .nm-btn:active:not(:disabled) {
        transform: translateY(0);
    }

    .nm-btn:disabled {
        opacity: .52;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Loading state */
    .nm-btn .nm-btn-label {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nm-btn .nm-spin {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        border: 2.5px solid rgba(255, 255, 255, .3);
        border-top-color: #fff;
        animation: nmSpin .65s linear infinite;
        display: none;
    }

    .nm-btn.nm-loading .nm-btn-label {
        display: none;
    }

    .nm-btn.nm-loading .nm-spin {
        display: block;
    }

    @keyframes nmSpin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ═══════════════════════════════════════════════════════════
   SKIP / SECONDARY BUTTON
   ═══════════════════════════════════════════════════════════ */
    .nm-skip {
        width: 100%;
        padding: 10px 20px;
        margin-top: 8px;
        font-family: var(--nm-font);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--nm-muted);
        background: none;
        border: 1.5px solid var(--nm-border);
        border-radius: var(--nm-r-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: border-color .2s, color .2s, background .2s;
        -webkit-tap-highlight-color: transparent;
    }

    .nm-skip:hover {
        border-color: var(--nm-mint);
        color: var(--nm-green);
        background: rgba(82, 183, 136, .04);
    }

    /* ═══════════════════════════════════════════════════════════
   OTP BOXES  — 5 chars (alphanumeric, matches otp_api.php)
   ═══════════════════════════════════════════════════════════ */
    #nmCard .bm-otp-row {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin: 6px 0 20px;
    }

    #nmCard .bm-otp-box {
        width: 52px;
        height: 58px;
        font-family: var(--nm-display);
        font-size: 24px;
        font-weight: 700;
        text-align: center;
        color: var(--nm-forest);
        background: var(--nm-cream);
        border: 2px solid var(--nm-border);
        border-radius: var(--nm-r-sm);
        outline: none;
        caret-color: var(--nm-mint);
        -webkit-appearance: none;
        appearance: none;

        transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;
    }

    #nmCard .bm-otp-box:focus {
        border-color: var(--nm-mint);
        background: #fff;
        box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .18);
        transform: scale(1.07);
    }

    #nmCard .bm-otp-box.is-filled {
        border-color: var(--nm-green);
        background: rgba(45, 106, 79, .06);
    }

    #nmCard .bm-otp-box.is-error {
        border-color: var(--nm-danger) !important;
        animation: bm-shake .38s ease;
    }

    @keyframes bm-shake {

        0%,
        100% {
            transform: translateX(0);
        }

        20% {
            transform: translateX(-5px);
        }

        60% {
            transform: translateX(5px);
        }
    }

    /* ═══════════════════════════════════════════════════════════
   TIMER PILL
   ═══════════════════════════════════════════════════════════ */
    .nm-timer {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 13px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 600;
        background: var(--nm-warm);
        border: 1px solid #f0e4cc;
        color: #8b5e1a;
        transition: background .3s, color .3s, border-color .3s;
    }

    .nm-timer.urgent {
        background: #fff1f0;
        border-color: #ffd0cc;
        color: #c62828;
    }

    /* ═══════════════════════════════════════════════════════════
   OTP ICON WRAP
   ═══════════════════════════════════════════════════════════ */
    .nm-icon-wrap {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(82, 183, 136, .14), rgba(45, 106, 79, .08));
        border: 2px solid rgba(82, 183, 136, .28);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 23px;
        color: var(--nm-green);
        margin: 0 auto 10px;
    }

    /* ═══════════════════════════════════════════════════════════
   GHOST BUTTONS (Resend / Back)
   ═══════════════════════════════════════════════════════════ */
    .nm-ghost {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        font-family: var(--nm-font);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--nm-muted);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: color .2s;
        -webkit-tap-highlight-color: transparent;
    }

    .nm-ghost:hover:not(:disabled) {
        color: var(--nm-green);
    }

    .nm-ghost:disabled {
        opacity: .45;
        cursor: default;
    }

    .nm-ghost.on {
        color: var(--nm-green);
    }

    /* ═══════════════════════════════════════════════════════════
   SUCCESS STATE
   ═══════════════════════════════════════════════════════════ */
    .nm-success-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(82, 183, 136, .2), rgba(45, 106, 79, .1));
        border: 2px solid var(--nm-mint);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        color: var(--nm-green);
        margin: 0 auto 14px;
        animation: nmPop .42s cubic-bezier(.34, 1.56, .64, 1) both;
    }

    @keyframes nmPop {
        from {
            transform: scale(.4);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* ═══════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════ */
    @media (max-width: 500px) {
        .nm-hero {
            padding: 24px 20px 20px;
        }

        .nm-body {
            padding: 20px 18px 24px;
        }

        .nm-method-grid {
            grid-template-columns: 1fr;
        }

        #nmCard .bm-otp-box {
            width: 44px;
            height: 50px;
            font-size: 20px;
        }
    }
</style>


<!-- ═══════════════════════════════════════════════════════════
     MODAL HTML
     ═══════════════════════════════════════════════════════════ -->
<div id="nmOverlay" role="dialog" aria-modal="true" aria-labelledby="nmHeroTitle">
    <div id="nmCard">

        <!-- ── HERO ──────────────────────────────────────────── -->
        <div class="nm-hero">
            <div class="nm-hero-grid"></div>
            <div class="nm-hero-badge">
                <i class="fas fa-bell" style="font-size:9px;"></i>&nbsp; Notification Setup
            </div>
            <h2 id="nmHeroTitle">Stay in the Loop</h2>
            <p id="nmHeroSub">Choose how you'd like to receive school updates and alerts.</p>

            <!-- Step pills -->
            <div class="nm-steps">
                <div class="nm-step active" id="nmSI1">
                    <div class="nm-step-dot">1</div>
                    <span>Method</span>
                </div>
                <div class="nm-step-line"></div>
                <div class="nm-step" id="nmSI2">
                    <div class="nm-step-dot">2</div>
                    <span>Contact</span>
                </div>
                <div class="nm-step-line"></div>
                <div class="nm-step" id="nmSI3">
                    <div class="nm-step-dot">3</div>
                    <span>Verify</span>
                </div>
            </div>
        </div><!-- /nm-hero -->

        <!-- ── BODY ──────────────────────────────────────────── -->
        <div class="nm-body">

            <!-- ╔═══ STEP 1 — Choose Method ═══╗ -->
            <div id="nmP1" class="nm-panel nm-active">

                <div class="nm-err" id="nmE1">
                    <i class="fas fa-circle-exclamation fa-sm"></i>
                    <span id="nmE1Msg">Please select a notification method.</span>
                </div>

                <p style="font-size:13px;color:var(--nm-muted);margin:0 0 18px;line-height:1.65;">
                    Select how you'd like to receive grade updates, event reminders, and school announcements.
                </p>

                <div class="nm-method-grid">
                    <div class="nm-method-opt">
                        <input type="radio" name="nm_method" id="nmRPhone" value="phone">
                        <label class="nm-method-card" for="nmRPhone">
                            <i class="fas fa-mobile-screen-button nm-mc-icon"></i>
                            <span class="nm-mc-title">Phone</span>
                            <small class="nm-mc-sub">SMS notifications</small>
                        </label>
                    </div>
                    <div class="nm-method-opt">
                        <input type="radio" name="nm_method" id="nmREmail" value="email">
                        <label class="nm-method-card" for="nmREmail">
                            <i class="fas fa-envelope nm-mc-icon"></i>
                            <span class="nm-mc-title">Email</span>
                            <small class="nm-mc-sub">Email notifications</small>
                        </label>
                    </div>
                </div>

                <div class="nm-divider">or</div>

                <div class="nm-both-opt">
                    <input type="radio" name="nm_method" id="nmRBoth" value="both">
                    <label class="nm-both-card" for="nmRBoth">
                        <i class="fas fa-layer-group"></i>
                        Both Phone &amp; Email
                    </label>
                </div>

                <button class="nm-btn" id="nmBtnNext1" type="button">
                    <span class="nm-btn-label"><i class="fas fa-arrow-right"></i>&nbsp; Continue</span>
                    <span class="nm-spin"></span>
                </button>

                <button class="nm-skip" id="nmBtnSkip" type="button">
                    <i class="fas fa-times fa-xs"></i> Skip for now
                </button>

            </div><!-- /STEP 1 -->

            <!-- ╔═══ STEP 2 — Enter Contact ═══╗ -->
            <div id="nmP2" class="nm-panel">

                <div class="nm-err" id="nmE2">
                    <i class="fas fa-circle-exclamation fa-sm"></i>
                    <span id="nmE2Msg">Please enter a valid contact.</span>
                </div>

                <div class="nm-note">
                    <i class="fas fa-shield-halved"></i>
                    <span>Your contact is only used for school notifications and OTP verification. It is never shared with third parties.</span>
                </div>

                <div class="nm-field" id="nmFPhone" style="display:none;">
                    <label class="nm-label" for="nmPhone">Phone Number</label>
                    <div class="nm-input-wrap">
                        <i class="fas fa-phone nm-field-icon"></i>
                        <input type="tel" class="nm-input" id="nmPhone"
                            placeholder="09xxxxxxxxx or +639xxxxxxxxx"
                            maxlength="15" autocomplete="tel">
                    </div>
                </div>

                <div class="nm-field" id="nmFEmail" style="display:none;">
                    <label class="nm-label" for="nmEmail">Email Address</label>
                    <div class="nm-input-wrap">
                        <i class="fas fa-envelope nm-field-icon"></i>
                        <input type="email" class="nm-input" id="nmEmail"
                            placeholder="you@gmail.com"
                            autocomplete="email">
                    </div>
                </div>

                <button class="nm-btn" id="nmBtnNext2" type="button">
                    <span class="nm-btn-label"><i class="fas fa-paper-plane"></i>&nbsp; Send Verification Code</span>
                    <span class="nm-spin"></span>
                </button>

                <button class="nm-skip" id="nmBtnBack1" type="button">
                    <i class="fas fa-arrow-left fa-xs"></i> Back
                </button>

            </div><!-- /STEP 2 -->

            <!-- ╔═══ STEP 3 — Verify OTP ═══╗ -->
            <div id="nmP3" class="nm-panel">

                <div class="nm-err" id="nmE3">
                    <i class="fas fa-circle-exclamation fa-sm"></i>
                    <span id="nmE3Msg">Invalid code. Please try again.</span>
                </div>

                <div style="text-align:center;margin-bottom:20px;">
                    <div class="nm-icon-wrap" id="nmOtpIcon">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <p style="font-size:13px;color:var(--nm-muted);line-height:1.6;margin:0 0 10px;">
                        Enter the 5-character code sent to<br>
                        <strong id="nmMasked" style="color:var(--nm-ink);font-size:14px;"></strong>
                    </p>
                    <span class="nm-timer" id="nmTimerPill">
                        <i class="fas fa-clock"></i>
                        <span id="nmTimerVal">5:00</span>
                    </span>
                </div>

                <!-- 5 OTP boxes (otp_api.php generates 5-char ALPHANUMERIC codes) -->
                <div class="bm-otp-row" id="nmOtpRow">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocomplete="one-time-code" autocorrect="off" autocapitalize="characters" spellcheck="false">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                </div>

                <!-- Second row only for "both" method -->
                <div id="nmOtpRow2Wrap" style="display:none;">
                    <p style="font-size:12px;color:var(--nm-muted);text-align:center;margin:4px 0 6px;">
                        Also verify your <strong id="nmSecondLabel">email</strong>:
                        <strong id="nmMasked2" style="color:var(--nm-ink);"></strong>
                    </p>
                    <div class="bm-otp-row" id="nmOtpRow2">
                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="text" autocorrect="off" autocapitalize="characters" spellcheck="false">
                    </div>
                </div>

                <button class="nm-btn" id="nmBtnVerify" type="button">
                    <span class="nm-btn-label"><i class="fas fa-check"></i>&nbsp; Verify &amp; Save</span>
                    <span class="nm-spin"></span>
                </button>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;">
                    <button class="nm-ghost" id="nmBtnResend" type="button" disabled>
                        <i class="fas fa-rotate-right fa-xs"></i> Resend Code
                    </button>
                    <button class="nm-ghost" id="nmBtnBack2" type="button">
                        <i class="fas fa-arrow-left fa-xs"></i> Change Contact
                    </button>
                </div>

            </div><!-- /STEP 3 -->

            <!-- ╔═══ STEP 4 — Success ═══╗ -->
            <div id="nmP4" class="nm-panel" style="text-align:center;padding:10px 0 6px;">

                <div class="nm-success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 style="font-family:var(--nm-display);color:var(--nm-forest);font-size:20px;margin:0 0 8px;">
                    All Set!
                </h3>
                <p style="font-size:13px;color:var(--nm-muted);line-height:1.65;margin:0 0 22px;">
                    Your notification preferences have been saved.<br>
                    You'll now receive school updates via your verified contact.
                </p>
                <button class="nm-btn" id="nmBtnDone" type="button">
                    <span class="nm-btn-label"><i class="fas fa-house"></i>&nbsp; Back to Dashboard</span>
                    <span class="nm-spin"></span>
                </button>

            </div><!-- /STEP 4 -->

        </div><!-- /nm-body -->
    </div><!-- /nmCard -->
</div><!-- /nmOverlay -->


<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT  — self-contained, no Bootstrap, no jQuery
     ═══════════════════════════════════════════════════════════ -->
<script>
    (function() {
        'use strict';

        /* ─────────────────────────────────────────────────────────
           CONFIG
        ───────────────────────────────────────────────────────── */
        var PHP_ELIGIBLE = <?php echo json_encode(!empty($show_notif_modal) ? (bool)$show_notif_modal : false); ?>;
        var PHP_FORCE = <?php echo json_encode(isset($_GET['show_notif']) && $_GET['show_notif'] === '1'); ?>;
        var LS_SKIP_KEY = 'bunhs_notif_skipped_v2';
        var MAX_RESEND = 3;
        var OTP_LENGTH = 5;

        /* ─────────────────────────────────────────────────────────
           STATE
        ───────────────────────────────────────────────────────── */
        var nmMethod = '';
        var nmPhone = '';
        var nmEmail = '';
        var nmResends = 0;
        var nmTimerID = null;
        var nmBusy = false;

        /* ─────────────────────────────────────────────────────────
           HELPERS — identical to index.php login flow
        ───────────────────────────────────────────────────────── */

        // Wire a .bm-otp-box row — supports alphanumeric codes (matches otp_api.php charset)
        function initOtp(rowId) {
            var row = document.getElementById(rowId);
            if (!row) return;
            var boxes = row.querySelectorAll('.bm-otp-box');
            boxes = Array.from(boxes);
            boxes.forEach(function(b, i) {
                b.addEventListener('input', function() {
                    // Keep only valid OTP chars (A-Z, 2-9) — uppercase automatically
                    b.value = b.value.toUpperCase().replace(/[^A-Z2-9]/g, '').slice(-1);
                    b.classList.toggle('is-filled', b.value !== '');
                    if (b.value && i < boxes.length - 1) boxes[i + 1].focus();
                });
                b.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !b.value && i > 0) {
                        boxes[i - 1].value = '';
                        boxes[i - 1].classList.remove('is-filled');
                        boxes[i - 1].focus();
                    }
                    if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                    if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
                });
                b.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var d = (e.clipboardData || window.clipboardData).getData('text')
                        .toUpperCase().replace(/[^A-Z2-9]/g, '').slice(0, boxes.length);
                    d.split('').forEach(function(c, j) {
                        if (boxes[j]) {
                            boxes[j].value = c;
                            boxes[j].classList.toggle('is-filled', c !== '');
                        }
                    });
                    boxes[Math.min(d.length, boxes.length - 1)].focus();
                });
                // Do NOT block keypresses — let the input handler sanitise instead
            });
        }

        // Read value from a .bm-otp-box row (uppercased to match server)
        function getOtp(rowId) {
            return Array.from(document.querySelectorAll('#' + rowId + ' .bm-otp-box'))
                .map(function(b) {
                    return b.value.toUpperCase();
                }).join('');
        }

        // Clear all boxes — exact copy of clearOtp() from index.php
        function clearOtp(rowId) {
            document.querySelectorAll('#' + rowId + ' .bm-otp-box').forEach(function(b) {
                b.value = '';
                b.classList.remove('is-filled', 'is-error');
            });
        }

        // Shake + red border — exact copy of shakeOtp() from index.php
        function shakeOtp(rowId) {
            document.querySelectorAll('#' + rowId + ' .bm-otp-box').forEach(function(b) {
                b.classList.add('is-error');
                setTimeout(function() {
                    b.classList.remove('is-error');
                }, 420);
            });
        }

        // MM:SS countdown — exact copy of mmss() from index.php
        function mmss(spanId, timerId, secs) {
            var span = document.getElementById(spanId);
            var timer = document.getElementById(timerId);
            if (!span) return;
            var rem = secs;

            function tick() {
                var m = String(Math.floor(rem / 60)).padStart(2, '0');
                var s = String(rem % 60).padStart(2, '0');
                span.textContent = m + ':' + s;
                if (timer) timer.classList.toggle('urgent', rem <= 60);
                if (rem-- > 0) nmTimerID = setTimeout(tick, 1000);
            }
            clearTimeout(nmTimerID);
            tick();
        }

        // Resend cooldown — exact copy of cdwn() from index.php
        function cdwn(spanId, secs, done) {
            var span = document.getElementById(spanId);
            if (!span) return;
            var rem = secs;
            span.textContent = rem;
            var t = setInterval(function() {
                rem--;
                var el = document.getElementById(spanId);
                if (el) el.textContent = rem;
                if (rem <= 0) {
                    clearInterval(t);
                    if (done) done();
                }
            }, 1000);
            return t;
        }

        // Show error strip — exact copy of showErr() from index.php
        function showErr(stripId, msgId, msg) {
            var strip = document.getElementById(stripId);
            var span = document.getElementById(msgId);
            if (strip) {
                strip.style.cssText = '';
                strip.classList.add('show');
            }
            if (span) span.textContent = msg;
        }

        // Hide error strip — exact copy of hideErr() from index.php
        function hideErr(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('show');
        }

        // Button loading state
        function setLoad(btn, on) {
            btn.disabled = on;
            btn.classList.toggle('nm-loading', on);
        }

        // POST FormData — same pattern as all fetch() calls in index.php
        function postForm(url, fields) {
            var fd = new FormData();
            Object.keys(fields).forEach(function(k) {
                if (fields[k] !== null && fields[k] !== undefined) fd.append(k, fields[k]);
            });
            return fetch(url, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                });
        }

        /* ─────────────────────────────────────────────────────────
           DOM refs (set after DOMContentLoaded)
        ───────────────────────────────────────────────────────── */
        var overlay, card, heroTitle, heroSub, panels, stepInds;
        var btnNext1, btnNext2, btnBack1, btnVerify, btnBack2, btnResend, btnSkip, btnDone;
        var timerPill, timerVal;

        /* ─────────────────────────────────────────────────────────
           OPEN / CLOSE
        ───────────────────────────────────────────────────────── */
        function openModal() {
            overlay.classList.add('nm-open');
            document.body.classList.add('nm-modal-open');
            setTimeout(function() {
                var first = card.querySelector('input, button, [tabindex]');
                if (first) first.focus();
            }, 380);
        }

        function closeModal() {
            overlay.classList.remove('nm-open');
            document.body.classList.remove('nm-modal-open');
            clearTimeout(nmTimerID);
            // Remove ?show_notif=1 from the URL so a page refresh
            // does not re-open the modal (covers the "Join us" flow).
            try {
                var url = new URL(window.location.href);
                if (url.searchParams.has('show_notif')) {
                    url.searchParams.delete('show_notif');
                    var clean = url.pathname + (url.search ? url.search : '') + url.hash;
                    history.replaceState(null, '', clean);
                }
            } catch (e) {
                /* older browsers — URL stays as-is */
            }
        }

        /* ─────────────────────────────────────────────────────────
           STEP NAVIGATION
        ───────────────────────────────────────────────────────── */
        var heroContent = {
            1: ['Stay in the Loop', "Choose how you'd like to receive school updates."],
            2: ['Enter Your Contact', "We'll send a code to verify your contact details."],
            3: ['Enter Verification Code', 'Type the ' + OTP_LENGTH + '-digit code we just sent you.'],
            4: ['Verified!', 'Your notification preference is now active.']
        };

        function goTo(n, back) {
            Object.keys(panels).forEach(function(k) {
                panels[k].classList.remove('nm-active', 'nm-back');
            });
            var target = panels['p' + n];
            void target.offsetWidth;
            target.classList.add(back ? 'nm-back' : 'nm-active');
            [1, 2, 3].forEach(function(i) {
                var si = stepInds['s' + i];
                si.classList.remove('active', 'done');
                if (i < n) si.classList.add('done');
                if (i === n) si.classList.add('active');
            });
            if (heroContent[n]) {
                heroTitle.textContent = heroContent[n][0];
                heroSub.textContent = heroContent[n][1];
            }
            card.scrollTop = 0;
        }

        /* ─────────────────────────────────────────────────────────
           MASK HELPERS
        ───────────────────────────────────────────────────────── */
        function maskPhone(p) {
            var d = (p || '').replace(/\D/g, '');
            if (d.length < 7) return p;
            return d.slice(0, 4) + '****' + d.slice(-3);
        }

        function maskEmail(e) {
            if (!e || e.indexOf('@') < 0) return e;
            var parts = e.split('@');
            return parts[0].slice(0, 2) + '***@' + parts[1];
        }

        /* ─────────────────────────────────────────────────────────
           METHOD CARD SELECTION
        ───────────────────────────────────────────────────────── */
        function syncCardSelection() {
            document.querySelectorAll('.nm-method-card, .nm-both-card')
                .forEach(function(c) {
                    c.classList.remove('nm-sel');
                });
            var checked = document.querySelector('input[name="nm_method"]:checked');
            if (!checked) return;
            var lbl = document.querySelector('label[for="' + checked.id + '"]');
            if (lbl) lbl.classList.add('nm-sel');
            nmMethod = checked.value;
            hideErr('nmE1');
        }

        /* ─────────────────────────────────────────────────────────
           STEP 1 — Choose Method
        ───────────────────────────────────────────────────────── */
        function initStep1() {
            document.querySelectorAll('input[name="nm_method"]').forEach(function(r) {
                r.addEventListener('change', syncCardSelection);
            });
            document.querySelectorAll('.nm-method-card, .nm-both-card').forEach(function(lbl) {
                lbl.addEventListener('click', function() {
                    setTimeout(syncCardSelection, 10);
                });
            });

            btnNext1.addEventListener('click', function() {
                hideErr('nmE1');
                var sel = document.querySelector('input[name="nm_method"]:checked');
                if (!sel) {
                    showErr('nmE1', 'nmE1Msg', 'Please select a notification method to continue.');
                    return;
                }
                nmMethod = sel.value;

                document.getElementById('nmFPhone').style.display =
                    (nmMethod === 'phone' || nmMethod === 'both') ? 'block' : 'none';
                document.getElementById('nmFEmail').style.display =
                    (nmMethod === 'email' || nmMethod === 'both') ? 'block' : 'none';

                document.getElementById('nmPhone').value = '';
                document.getElementById('nmEmail').value = '';
                document.getElementById('nmPhone').classList.remove('nm-field-err');
                document.getElementById('nmEmail').classList.remove('nm-field-err');

                goTo(2, false);
                setTimeout(function() {
                    var first = (nmMethod === 'email') ?
                        document.getElementById('nmEmail') :
                        document.getElementById('nmPhone');
                    if (first) first.focus();
                }, 80);
            });

            btnSkip.addEventListener('click', function() {
                try {
                    localStorage.setItem(LS_SKIP_KEY, '1');
                } catch (e) {
                    /* ignore */
                }
                postForm('api/student_notification_api.php', {
                    action: 'skip'
                }).catch(function() {});
                closeModal();
            });
        }

        /* ─────────────────────────────────────────────────────────
           STEP 2 — Enter Contact Details
        ───────────────────────────────────────────────────────── */
        function initStep2() {
            btnBack1.addEventListener('click', function() {
                hideErr('nmE2');
                goTo(1, true);
            });

            btnNext2.addEventListener('click', function() {
                if (nmBusy) return;
                hideErr('nmE2');

                nmPhone = document.getElementById('nmPhone').value.trim();
                nmEmail = document.getElementById('nmEmail').value.trim();

                var phoneNeeded = (nmMethod === 'phone' || nmMethod === 'both');
                var emailNeeded = (nmMethod === 'email' || nmMethod === 'both');

                document.getElementById('nmPhone').classList.remove('nm-field-err');
                document.getElementById('nmEmail').classList.remove('nm-field-err');

                if (phoneNeeded && !nmPhone) {
                    showErr('nmE2', 'nmE2Msg', 'Please enter your phone number.');
                    document.getElementById('nmPhone').classList.add('nm-field-err');
                    document.getElementById('nmPhone').focus();
                    return;
                }
                if (emailNeeded && !nmEmail) {
                    showErr('nmE2', 'nmE2Msg', 'Please enter your email address.');
                    document.getElementById('nmEmail').classList.add('nm-field-err');
                    document.getElementById('nmEmail').focus();
                    return;
                }
                if (emailNeeded && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(nmEmail)) {
                    showErr('nmE2', 'nmE2Msg', 'Please enter a valid email address.');
                    document.getElementById('nmEmail').classList.add('nm-field-err');
                    document.getElementById('nmEmail').focus();
                    return;
                }

                nmBusy = true;
                setLoad(btnNext2, true);

                // 1. Save preference — FormData POST (same as login_otp.php pattern)
                postForm('api/student_notification_api.php', {
                        action: 'save_preference',
                        preference: nmMethod,
                        phone: nmPhone,
                        email: nmEmail
                    })
                    .then(function(saved) {
                        if (!saved.success) throw new Error(saved.message || 'Failed to save.');

                        // 2. Send OTP — FormData POST (same as login_otp.php pattern)
                        var sendUrl = 'api/otp_api.php';
                        var sendFirst = phoneNeeded ?
                            postForm(sendUrl, {
                                action: 'send_phone_otp',
                                phone: nmPhone
                            }) :
                            postForm(sendUrl, {
                                action: 'send_email_otp',
                                email: nmEmail
                            });

                        var sendChain = sendFirst.then(function(d1) {
                            if (!d1.success) throw new Error(d1.message || 'Failed to send code.');
                            if (nmMethod !== 'both') return d1;
                            return postForm(sendUrl, {
                                    action: 'send_email_otp',
                                    email: nmEmail
                                })
                                .then(function(d2) {
                                    if (!d2.success) throw new Error(d2.message || 'Failed to send email code.');
                                    return d2;
                                });
                        });

                        return sendChain.then(function() {
                            // 3. Prepare OTP step — identical to loginStep2 in index.php
                            initOtp('nmOtpRow');
                            if (nmMethod === 'both') {
                                document.getElementById('nmOtpRow2Wrap').style.display = 'block';
                                document.getElementById('nmSecondLabel').textContent = 'email';
                                document.getElementById('nmMasked2').textContent = maskEmail(nmEmail);
                                initOtp('nmOtpRow2');
                            } else {
                                document.getElementById('nmOtpRow2Wrap').style.display = 'none';
                            }

                            document.getElementById('nmMasked').textContent =
                                (nmMethod === 'email') ? maskEmail(nmEmail) : maskPhone(nmPhone);

                            var icon = document.getElementById('nmOtpIcon');
                            if (icon) icon.innerHTML =
                                nmMethod === 'phone' ? '<i class="fas fa-mobile-screen-button"></i>' :
                                nmMethod === 'email' ? '<i class="fas fa-envelope-open-text"></i>' :
                                '<i class="fas fa-layer-group"></i>';

                            // Start 5:00 timer — identical to mmss() call in index.php
                            mmss('nmTimerVal', 'nmTimerPill', 300);

                            // Start 30s resend cooldown — identical to cdwn() call in index.php
                            nmResends = 0;
                            btnResend.disabled = true;
                            btnResend.classList.remove('on');
                            btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend · <span id="nmResendCdwn">30</span>s';
                            cdwn('nmResendCdwn', 30, function() {
                                btnResend.disabled = false;
                                btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend Code';
                                btnResend.classList.add('on');
                            });

                            goTo(3, false);

                            setTimeout(function() {
                                var firstBox = document.querySelector('#nmOtpRow .bm-otp-box');
                                if (firstBox) firstBox.focus();
                            }, 80);
                        });
                    })
                    .catch(function(err) {
                        showErr('nmE2', 'nmE2Msg', err.message || 'Connection error. Please check your internet and try again.');
                    })
                    .finally(function() {
                        setLoad(btnNext2, false);
                        nmBusy = false;
                    });
            });
        }

        /* ─────────────────────────────────────────────────────────
           STEP 3 — Verify OTP
           Mirrors loginOtpForm + loginResendBtn from index.php exactly
        ───────────────────────────────────────────────────────── */
        function initStep3() {

            // Back button — same as loginBackBtn
            btnBack2.addEventListener('click', function() {
                clearTimeout(nmTimerID);
                hideErr('nmE3');
                goTo(2, true);
            });

            // Verify button — mirrors loginOtpForm submit handler in index.php
            btnVerify.addEventListener('click', function() {
                if (nmBusy) return;
                hideErr('nmE3');

                var otp1 = getOtp('nmOtpRow');
                var otp2 = (nmMethod === 'both') ? getOtp('nmOtpRow2') : null;

                // Validate length — same guard as in index.php
                if (otp1.length < OTP_LENGTH) {
                    shakeOtp('nmOtpRow');
                    showErr('nmE3', 'nmE3Msg', 'Please enter all ' + OTP_LENGTH + ' digits.');
                    return;
                }
                if (nmMethod === 'both' && otp2.length < OTP_LENGTH) {
                    shakeOtp('nmOtpRow2');
                    showErr('nmE3', 'nmE3Msg', 'Please enter both ' + OTP_LENGTH + '-digit codes.');
                    return;
                }

                nmBusy = true;
                setLoad(btnVerify, true);

                // Determine verify action — FormData POST, same as index.php
                var verifyUrl = 'api/otp_api.php';
                var verifyChain;

                if (nmMethod === 'phone') {
                    verifyChain = postForm(verifyUrl, {
                        action: 'verify_phone_otp',
                        otp: otp1
                    });
                } else if (nmMethod === 'email') {
                    verifyChain = postForm(verifyUrl, {
                        action: 'verify_email_otp',
                        otp: otp1
                    });
                } else {
                    // both: verify phone first, then email
                    verifyChain = postForm(verifyUrl, {
                            action: 'verify_phone_otp',
                            otp: otp1
                        })
                        .then(function(d) {
                            if (!d.success) {
                                d._failRow = 'nmOtpRow';
                                return d;
                            }
                            return postForm(verifyUrl, {
                                    action: 'verify_email_otp',
                                    otp: otp2
                                })
                                .then(function(d2) {
                                    if (!d2.success) d2._failRow = 'nmOtpRow2';
                                    return d2;
                                });
                        });
                }

                verifyChain
                    .then(function(d) {
                        if (d.success) {
                            // ✅ — same success path as loginOtpForm in index.php
                            clearTimeout(nmTimerID);
                            try {
                                localStorage.removeItem(LS_SKIP_KEY);
                            } catch (e) {
                                /* ignore */
                            }

                            // Hide the notif dot (bell) — same as loginModal success
                            var dot = document.getElementById('notifDot');
                            if (dot) dot.style.display = 'none';

                            goTo(4, false);
                        } else {
                            // ❌ — same failure path as loginOtpForm in index.php
                            var failRow = d._failRow || 'nmOtpRow';
                            shakeOtp(failRow);
                            showErr('nmE3', 'nmE3Msg', d.message || 'Invalid code. Please try again.');
                            clearOtp(failRow);
                            var firstBox = document.querySelector('#' + failRow + ' .bm-otp-box');
                            if (firstBox) firstBox.focus();
                        }
                    })
                    .catch(function() {
                        showErr('nmE3', 'nmE3Msg', 'Connection error. Please try again.');
                    })
                    .finally(function() {
                        setLoad(btnVerify, false);
                        nmBusy = false;
                    });
            });

            // Resend button — mirrors loginResendBtn handler in index.php exactly
            btnResend.addEventListener('click', function() {
                if (nmResends >= MAX_RESEND || nmBusy) return;
                nmResends++;
                btnResend.disabled = true;
                btnResend.classList.remove('on');

                var resendUrl = 'api/otp_api.php';
                var resendChain;

                if (nmMethod === 'phone') {
                    resendChain = postForm(resendUrl, {
                        action: 'resend_otp',
                        type: 'phone'
                    });
                } else if (nmMethod === 'email') {
                    resendChain = postForm(resendUrl, {
                        action: 'resend_otp',
                        type: 'email'
                    });
                } else {
                    resendChain = postForm(resendUrl, {
                            action: 'resend_otp',
                            type: 'phone'
                        })
                        .then(function() {
                            return postForm(resendUrl, {
                                action: 'resend_otp',
                                type: 'email'
                            });
                        });
                }

                resendChain
                    .then(function(d) {
                        if (d.success) {
                            // Reset boxes — same as loginResendBtn success in index.php
                            clearOtp('nmOtpRow');
                            if (nmMethod === 'both') clearOtp('nmOtpRow2');

                            // Restart timer — same mmss() call
                            mmss('nmTimerVal', 'nmTimerPill', 300);

                            // Restart 30s cooldown — same cdwn() call
                            btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend · <span id="nmResendCdwn">30</span>s';
                            cdwn('nmResendCdwn', 30, function() {
                                if (nmResends < MAX_RESEND) {
                                    btnResend.disabled = false;
                                    btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend Code';
                                    btnResend.classList.add('on');
                                }
                            });

                            // Green success strip
                            var strip = document.getElementById('nmE3');
                            if (strip) {
                                strip.style.background = 'rgba(82,183,136,.08)';
                                strip.style.borderColor = 'rgba(82,183,136,.3)';
                                strip.style.color = 'var(--nm-green)';
                                strip.style.borderLeft = '3px solid var(--nm-green)';
                                strip.classList.add('show');
                                var rem = MAX_RESEND - nmResends;
                                document.getElementById('nmE3Msg').textContent =
                                    d.message || ('New code sent. (' + rem + ' resend' + (rem !== 1 ? 's' : '') + ' left)');
                                setTimeout(function() {
                                    hideErr('nmE3');
                                    strip.style.cssText = '';
                                }, 3500);
                            }

                            var firstBox = document.querySelector('#nmOtpRow .bm-otp-box');
                            if (firstBox) firstBox.focus();
                        } else {
                            nmResends = Math.max(0, nmResends - 1);
                            btnResend.disabled = nmResends >= MAX_RESEND;
                            if (!btnResend.disabled) {
                                btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend Code';
                                btnResend.classList.add('on');
                            }
                            showErr('nmE3', 'nmE3Msg', d.message || 'Failed to resend. Please try again.');
                        }
                    })
                    .catch(function() {
                        nmResends = Math.max(0, nmResends - 1);
                        btnResend.disabled = nmResends >= MAX_RESEND;
                        if (!btnResend.disabled) {
                            btnResend.innerHTML = '<i class="fas fa-rotate-right fa-xs"></i> Resend Code';
                            btnResend.classList.add('on');
                        }
                        showErr('nmE3', 'nmE3Msg', 'Connection error. Could not resend code.');
                    });
            });
        }

        /* ─────────────────────────────────────────────────────────
           STEP 4 — Done
        ───────────────────────────────────────────────────────── */
        function initStep4() {
            btnDone.addEventListener('click', closeModal);
        }

        /* ─────────────────────────────────────────────────────────
           AUTO-SHOW LOGIC
        ───────────────────────────────────────────────────────── */
        function shouldAutoShow() {
            // ?show_notif=1 (Join Now button) ALWAYS opens the modal —
            // regardless of whether a preference is already saved.
            if (PHP_FORCE) {
                try {
                    localStorage.removeItem(LS_SKIP_KEY);
                } catch (e) {
                    /* ignore */
                }
                return true;
            }
            // Auto-show on normal page load only when PHP says no preference yet.
            if (!PHP_ELIGIBLE) return false;
            try {
                if (localStorage.getItem(LS_SKIP_KEY) === '1') return false;
            } catch (e) {
                /* private mode — show anyway */
            }
            return true;
        }

        /* ─────────────────────────────────────────────────────────
           INIT
        ───────────────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', function() {
            overlay = document.getElementById('nmOverlay');
            card = document.getElementById('nmCard');
            heroTitle = document.getElementById('nmHeroTitle');
            heroSub = document.getElementById('nmHeroSub');
            timerPill = document.getElementById('nmTimerPill');
            timerVal = document.getElementById('nmTimerVal');

            panels = {
                p1: document.getElementById('nmP1'),
                p2: document.getElementById('nmP2'),
                p3: document.getElementById('nmP3'),
                p4: document.getElementById('nmP4')
            };
            stepInds = {
                s1: document.getElementById('nmSI1'),
                s2: document.getElementById('nmSI2'),
                s3: document.getElementById('nmSI3')
            };

            btnNext1 = document.getElementById('nmBtnNext1');
            btnNext2 = document.getElementById('nmBtnNext2');
            btnBack1 = document.getElementById('nmBtnBack1');
            btnVerify = document.getElementById('nmBtnVerify');
            btnBack2 = document.getElementById('nmBtnBack2');
            btnResend = document.getElementById('nmBtnResend');
            btnSkip = document.getElementById('nmBtnSkip');
            btnDone = document.getElementById('nmBtnDone');

            initStep1();
            initStep2();
            initStep3();
            initStep4();

            window.openNotifModal = openModal;

            if (shouldAutoShow()) setTimeout(openModal, 900);
        });

    })();
</script>