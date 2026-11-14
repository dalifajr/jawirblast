@extends('layouts.app')

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
    /* ==========================================================================
       Font Awesome Icon Protection (Prevent BeerCSS <i> Font Overriding)
       ========================================================================== */
    .fa, .fas, .far, .fab, [class*="fa-"] {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
        font-style: normal !important;
        line-height: 1 !important;
        display: inline-block;
    }

    /* ==========================================================================
       Flat Material You (Android 17 / BeerCSS M3 Expressive) Color & Tonal Tokens
       ========================================================================== */
    :root {
        --primary: #006c4c;
        --on-primary: #ffffff;
        --primary-container: #89f8c7;
        --on-primary-container: #002114;
        --secondary: #4d6357;
        --on-secondary: #ffffff;
        --secondary-container: #cfe8d8;
        --on-secondary-container: #0a1f16;
        --surface-container-lowest: #ffffff;
        --surface-container-low: #f4f6f4;
        --surface-container: #eceeed;
        --surface-container-high: #e6e8e7;
        --surface-container-highest: #e0e3e1;
        --outline: #707973;
        --outline-variant: #bfc9c2;
        --error: #ba1a1a;
        --error-container: #ffdad6;
        --on-error-container: #410002;
    }

    body.dark, [data-ui="dark"] {
        --primary: #89f8c7;
        --on-primary: #003825;
        --primary-container: #005138;
        --on-primary-container: #89f8c7;
        --secondary: #b3ccbd;
        --on-secondary: #1f352a;
        --secondary-container: #364b40;
        --on-secondary-container: #cfe8d8;
        --surface-container-lowest: #0e1110;
        --surface-container-low: #191c1b;
        --surface-container: #1d201f;
        --surface-container-high: #282b29;
        --surface-container-highest: #333634;
        --outline: #8a938c;
        --outline-variant: #404943;
        --error: #ffb4ab;
        --error-container: #93000a;
        --on-error-container: #ffdad6;
    }

    /* ==========================================================================
       Page Canvas & Theme Toggle Floating Button
       ========================================================================== */
    .auth-page-container {
        position: relative;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 120px);
        padding: 1.5rem 1rem;
    }

    .auth-theme-fab {
        position: fixed;
        top: 1.25rem;
        right: 1.25rem;
        width: 44px;
        height: 44px;
        border-radius: 9999px;
        background: var(--surface-container);
        border: 1px solid var(--outline-variant);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        z-index: 1050;
        transition: transform 0.2s ease, background 0.2s ease;
    }
    .auth-theme-fab:hover {
        transform: scale(1.08);
        background: var(--surface-container-high);
    }

    /* ==========================================================================
       Concentric Squircle Dual-Column Layout (Desktop >= 992px & Mobile Reflow)
       ========================================================================== */
    .auth-wrapper {
        width: 100%;
        max-width: 1020px;
        background: var(--surface-container-lowest);
        border-radius: 28px; /* M3 Concentric Squircle R_outer */
        border: 1px solid var(--outline-variant);
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.06);
        display: flex;
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }

    /* Left Form Column (540px) */
    .auth-form-column {
        flex: 1.2 1 540px;
        padding: 2.75rem 2.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background: var(--surface-container-lowest);
    }

    /* Right Showcase Column (420px) */
    .auth-showcase-column {
        flex: 1 1 420px;
        background: var(--primary-container);
        color: var(--on-primary-container);
        padding: 2.75rem 2.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        background-image: radial-gradient(rgba(0, 108, 76, 0.12) 1.5px, transparent 1.5px);
        background-size: 18px 18px;
    }
    body.dark .auth-showcase-column, [data-ui="dark"] .auth-showcase-column {
        background-image: radial-gradient(rgba(137, 248, 199, 0.12) 1.5px, transparent 1.5px);
    }

    @media (max-width: 991.98px) {
        .auth-wrapper {
            max-width: 480px;
            flex-direction: column;
            border-radius: 24px;
        }
        .auth-form-column {
            padding: 2rem 1.5rem;
            flex: 1 1 100%;
        }
        .auth-showcase-column {
            display: none !important;
        }
    }

    @media (max-width: 480px) {
        .auth-page-container {
            padding: 0.75rem 0.5rem;
        }
        .auth-form-column {
            padding: 1.5rem 1.15rem;
        }
    }

    /* ==========================================================================
       Brand Header & Dynamic Title
       ========================================================================== */
    .brand-header-badge {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1.5rem;
    }
    .brand-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--primary);
        color: var(--on-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(0, 108, 76, 0.25);
    }
    .brand-text h4 {
        margin: 0;
        font-weight: 800;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        color: var(--primary);
        line-height: 1.15;
    }
    .brand-text span {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--outline);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .auth-heading-area {
        margin-bottom: 1.5rem;
    }
    .auth-title {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0 0 4px 0;
        color: var(--on-surface, #191c1a);
    }
    .auth-subtitle {
        font-size: 0.875rem;
        color: var(--outline);
        margin: 0;
        line-height: 1.45;
    }

    /* ==========================================================================
       M3 Segmented Full Pill Switcher (Tab "Masuk" & "Daftar")
       ========================================================================== */
    .segmented-pill-switcher {
        display: flex;
        background: var(--surface-container);
        border-radius: 9999px; /* M3 Full Pill */
        padding: 4px;
        border: 1px solid var(--outline-variant);
        margin-bottom: 1.75rem;
        gap: 4px;
    }
    .segmented-pill-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9px 16px;
        border-radius: 9999px;
        border: none;
        background: transparent;
        color: var(--outline);
        font-size: 0.925rem;
        font-weight: 600;
        cursor: pointer;
        min-height: 44px; /* Thumb-friendly target */
        transition: all 0.25s cubic-bezier(0.2, 0, 0, 1);
        text-decoration: none;
    }
    .segmented-pill-btn.active {
        background: var(--surface-container-lowest);
        color: var(--primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        font-weight: 700;
    }
    .segmented-pill-btn:hover:not(.active) {
        color: var(--on-surface, #191c1a);
        background: rgba(0, 0, 0, 0.03);
    }

    /* ==========================================================================
       M3 Floating & Squircle Outlined Inputs (12px concentric radius)
       ========================================================================== */
    .m3-form-field {
        margin-bottom: 1.15rem;
    }
    .m3-field-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--on-surface, #191c1a);
        margin-bottom: 6px;
    }
    .m3-input-group {
        display: flex;
        position: relative;
        border-radius: 12px; /* M3 Squircle R_inner */
        border: 1px solid var(--outline-variant);
        background: var(--surface-container-lowest);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        overflow: hidden;
    }
    .m3-input-group:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(0, 108, 76, 0.18);
    }
    body.dark .m3-input-group:focus-within, [data-ui="dark"] .m3-input-group:focus-within {
        box-shadow: 0 0 0 3px rgba(137, 248, 199, 0.25);
    }
    .m3-input-addon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 46px;
        background: var(--surface-container-low);
        color: var(--outline);
        border-right: 1px solid var(--outline-variant);
        font-size: 1rem;
        flex-shrink: 0;
    }
    .m3-input-control {
        flex: 1;
        min-height: 48px; /* Thumb-friendly touch target */
        border: none;
        outline: none;
        padding: 10px 14px;
        background: transparent;
        color: var(--on-surface, #191c1a);
        font-size: 0.95rem;
        font-family: inherit;
        width: 100%;
    }
    .m3-input-control::placeholder {
        color: var(--outline);
        opacity: 0.75;
    }
    .m3-password-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        background: transparent;
        border: none;
        color: var(--outline);
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.2s ease;
    }
    .m3-password-toggle:hover {
        color: var(--primary);
    }

    /* Checkbox & Helpers */
    .m3-checkbox-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
    }
    .m3-checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.875rem;
        color: var(--on-surface, #191c1a);
        cursor: pointer;
        user-select: none;
    }
    .m3-checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
    }

    /* ==========================================================================
       Primary & Secondary Expressive Buttons (9999px Full Pill)
       ========================================================================== */
    .m3-btn-primary {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        min-height: 48px;
        border-radius: 9999px;
        background: var(--primary);
        color: var(--on-primary);
        font-weight: 700;
        font-size: 0.975rem;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 108, 76, 0.24);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .m3-btn-primary:hover {
        filter: brightness(1.08);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0, 108, 76, 0.32);
    }

    .m3-btn-telegram {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        min-height: 48px;
        border-radius: 9999px;
        background: #0088cc;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.95rem;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 136, 204, 0.25);
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .m3-btn-telegram:hover {
        background: #0077b5;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0, 136, 204, 0.35);
        color: #ffffff;
    }

    .m3-divider {
        position: relative;
        text-align: center;
        margin: 1.5rem 0;
    }
    .m3-divider hr {
        border: none;
        border-top: 1px solid var(--outline-variant);
        margin: 0;
    }
    .m3-divider span {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: var(--surface-container-lowest);
        padding: 0 14px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--outline);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* ==========================================================================
       Alerts & Error Containers (M3 Tonal Colors)
       ========================================================================== */
    .m3-alert-box {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 0.875rem;
        line-height: 1.45;
        margin-bottom: 1.25rem;
        border: 1px solid transparent;
    }
    .m3-alert-error {
        background: var(--error-container);
        color: var(--on-error-container);
        border-color: rgba(186, 26, 26, 0.2);
    }
    .m3-alert-success {
        background: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }
    body.dark .m3-alert-success, [data-ui="dark"] .m3-alert-success {
        background: #1e3a24;
        color: #89f8c7;
        border-color: #2e5937;
    }
    .m3-alert-icon {
        font-size: 1.15rem;
        margin-top: 1px;
        flex-shrink: 0;
    }
    .m3-alert-content {
        flex: 1;
    }
    .m3-alert-title {
        font-weight: 700;
        margin-bottom: 4px;
    }
    .m3-alert-list {
        margin: 0;
        padding-left: 18px;
    }

    /* ==========================================================================
       Right Showcase Section & Feature Cards (Desktop >= 992px)
       ========================================================================== */
    .showcase-badge-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }
    .m3-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 9999px;
        background: rgba(0, 108, 76, 0.15);
        color: var(--on-primary-container);
        font-size: 0.775rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    body.dark .m3-status-chip, [data-ui="dark"] .m3-status-chip {
        background: rgba(137, 248, 199, 0.18);
    }
    .m3-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #00c853;
        box-shadow: 0 0 6px #00c853;
        animation: pulseStatus 2s infinite;
    }
    @keyframes pulseStatus {
        0% { transform: scale(0.95); opacity: 0.8; }
        50% { transform: scale(1.15); opacity: 1; }
        100% { transform: scale(0.95); opacity: 0.8; }
    }
    .m3-version-chip {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--on-primary-container);
        opacity: 0.8;
    }

    .showcase-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1.75rem;
    }
    .showcase-brand-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: var(--on-primary-container);
        color: var(--primary-container);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .showcase-title {
        font-size: 1.45rem;
        font-weight: 800;
        margin: 0;
        line-height: 1.15;
        color: var(--on-primary-container);
    }
    .showcase-subtitle {
        font-size: 0.825rem;
        margin: 2px 0 0 0;
        color: var(--on-primary-container);
        opacity: 0.85;
    }

    .showcase-feature-cards {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 1.75rem;
    }
    .showcase-feature-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.6);
        transition: transform 0.2s ease;
    }
    body.dark .showcase-feature-item, [data-ui="dark"] .showcase-feature-item {
        background: rgba(0, 0, 0, 0.2);
        border-color: rgba(255, 255, 255, 0.08);
    }
    .showcase-feature-item:hover {
        transform: translateX(4px);
    }
    .showcase-feature-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: var(--on-primary-container);
        color: var(--primary-container);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .feature-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--on-primary-container);
        margin-bottom: 2px;
    }
    .feature-desc {
        font-size: 0.775rem;
        color: var(--on-primary-container);
        opacity: 0.85;
        line-height: 1.35;
    }

    /* Support Card */
    .showcase-support-card {
        padding: 14px 16px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.7);
    }
    body.dark .showcase-support-card, [data-ui="dark"] .showcase-support-card {
        background: rgba(0, 0, 0, 0.25);
        border-color: rgba(255, 255, 255, 0.1);
    }
    .support-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.825rem;
        font-weight: 700;
        color: var(--on-primary-container);
        margin-bottom: 8px;
    }
    .support-links {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .support-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--on-primary-container);
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.2s ease;
    }
    .support-link:hover {
        opacity: 0.75;
    }

    /* ==========================================================================
       Language Selector & Footer
       ========================================================================== */
    .lang-selector-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 1.5rem;
    }
    .lang-chip {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid var(--outline-variant);
        color: var(--outline);
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .lang-chip.active, .lang-chip:hover {
        background: var(--primary);
        color: var(--on-primary);
        border-color: var(--primary);
    }

    /* ==========================================================================
       Telegram Modal Dialog (M3 Concentric 28px Squircle)
       ========================================================================== */
    dialog#modal-telegram {
        border: 1px solid var(--outline-variant);
        border-radius: 28px;
        background: var(--surface-container-lowest);
        color: var(--on-surface, #191c1a);
        max-width: 440px;
        width: 90%;
        padding: 2rem;
        box-shadow: 0 20px 48px rgba(0, 0, 0, 0.16);
    }
    dialog#modal-telegram::backdrop {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
    }
</style>
@endpush

@section('content')
<div class="auth-page-container">

    <!-- Theme Toggle Floating Action Button -->
    <button type="button" class="auth-theme-fab" id="authThemeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon" id="authThemeIcon"></i>
    </button>

    <div class="auth-wrapper">

        <!-- ====================================================================
             LEFT SECTION: Form Card (Masuk & Daftar Tabs)
             ==================================================================== -->
        <div class="auth-form-column">
            <div>
                <!-- Brand Header -->
                <div class="brand-header-badge">
                    <div class="brand-icon-box">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="brand-text">
                        <h4>JAWIRBLAST</h4>
                        <span>{{ __('WhatsApp Broadcast Engine') }}</span>
                    </div>
                </div>

                <!-- Dynamic Headings -->
                <div class="auth-heading-area">
                    <h3 class="auth-title" id="auth-title">{{ __('Selamat Datang') }}</h3>
                    <p class="auth-subtitle" id="auth-subtitle">{{ __('Silakan masuk untuk mengelola kampanye siaran WhatsApp Anda.') }}</p>
                </div>

                <!-- Concentric M3 Segmented Full Pill Switcher -->
                <div class="segmented-pill-switcher" role="tablist">
                    <button type="button" class="segmented-pill-btn active" id="tab-btn-login" onclick="switchAuthTab('login')" role="tab" aria-selected="true">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>{{ __('Masuk') }}</span>
                    </button>
                    <button type="button" class="segmented-pill-btn" id="tab-btn-register" onclick="switchAuthTab('register')" role="tab" aria-selected="false">
                        <i class="fas fa-user-plus"></i>
                        <span>{{ __('Daftar') }}</span>
                    </button>
                </div>

                <!-- Session Flash Messages & Validation Errors -->
                @if (session('status'))
                    <div class="m3-alert-box m3-alert-success">
                        <div class="m3-alert-icon"><i class="fas fa-info-circle"></i></div>
                        <div class="m3-alert-content">{{ session('status') }}</div>
                    </div>
                @endif

                @if (session('success'))
                    <div class="m3-alert-box m3-alert-success">
                        <div class="m3-alert-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="m3-alert-content">{{ session('success') }}</div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="m3-alert-box m3-alert-error">
                        <div class="m3-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="m3-alert-content">{{ session('error') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="m3-alert-box m3-alert-error">
                        <div class="m3-alert-icon"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="m3-alert-content">
                            <div class="m3-alert-title">{{ __('Perhatian: Terjadi Kesalahan') }}</div>
                            <ul class="m3-alert-list">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <!-- ============================================================
                     TAB PANE 1: MASUK (LOGIN)
                     ============================================================ -->
                <div id="pane-login" class="auth-tab-pane">
                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf

                        <!-- Email Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="login_email">{{ __('Alamat Email') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" id="login_email" class="m3-input-control"
                                       value="{{ old('email') }}" required autofocus autocomplete="username"
                                       placeholder="nama@domain.com">
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="login_password">{{ __('Kata Sandi') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="login_password" class="m3-input-control"
                                       required autocomplete="current-password" placeholder="••••••••">
                                <button type="button" class="m3-password-toggle" onclick="togglePasswordVisibility('login_password', this)" title="{{ __('Lihat Sandi') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="m3-checkbox-row">
                            <label class="m3-checkbox-label" for="remember_login">
                                <input type="checkbox" name="remember" id="remember_login" value="1" {{ old('remember') ? 'checked' : '' }}>
                                <span>{{ __('Ingat Saya di perangkat ini') }}</span>
                            </label>
                        </div>

                        <!-- Cloudflare Turnstile Box (Graceful Fallback) -->
                        @php
                            $turnstileKey = config('services.turnstile.site_key') ?: env('CLOUDFLARE_TURNSTILE_SITE_KEY', '');
                        @endphp
                        @if($turnstileKey)
                            <div class="d-flex justify-content-center my-3">
                                <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-theme="auto"></div>
                            </div>
                        @endif

                        <!-- Submit Login CTA -->
                        <button type="submit" class="m3-btn-primary">
                            <span>{{ __('Masuk Sekarang') }}</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="m3-divider">
                        <hr>
                        <span>{{ __('ATAU') }}</span>
                    </div>

                    <!-- Telegram Dual-Mode Auth Button -->
                    <button type="button" class="m3-btn-telegram" id="btn-telegram-login">
                        <i class="fab fa-telegram"></i>
                        <span>{{ __('Masuk dengan Telegram') }}</span>
                    </button>

                    <!-- Optional Fallback Form for Telegram Request Route Alias -->
                    <form id="tg-request-form" action="{{ Route::has('auth.telegram.request') ? route('auth.telegram.request') : (Route::has('telegram.token') ? route('telegram.token') : '#') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>

                <!-- ============================================================
                     TAB PANE 2: DAFTAR (REGISTER)
                     ============================================================ -->
                <div id="pane-register" class="auth-tab-pane" style="display: none;">
                    <form method="POST" action="{{ route('register.post') }}">
                        @csrf

                        <!-- Name Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="reg_name">{{ __('Nama Lengkap') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-user"></i></span>
                                <input type="text" name="name" id="reg_name" class="m3-input-control"
                                       value="{{ old('name') }}" required autocomplete="name"
                                       placeholder="{{ __('Nama Lengkap Anda') }}">
                            </div>
                        </div>

                        <!-- Email Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="reg_email">{{ __('Alamat Email') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" id="reg_email" class="m3-input-control"
                                       value="{{ old('email') }}" required autocomplete="email"
                                       placeholder="nama@domain.com">
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="reg_password">{{ __('Kata Sandi (Min. 8 Karakter)') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="reg_password" class="m3-input-control"
                                       required autocomplete="new-password" placeholder="••••••••">
                                <button type="button" class="m3-password-toggle" onclick="togglePasswordVisibility('reg_password', this)" title="{{ __('Lihat Sandi') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Password Confirmation Input -->
                        <div class="m3-form-field">
                            <label class="m3-field-label" for="reg_password_confirmation">{{ __('Konfirmasi Kata Sandi') }}</label>
                            <div class="m3-input-group">
                                <span class="m3-input-addon"><i class="fas fa-shield-halved"></i></span>
                                <input type="password" name="password_confirmation" id="reg_password_confirmation" class="m3-input-control"
                                       required autocomplete="new-password" placeholder="••••••••">
                                <button type="button" class="m3-password-toggle" onclick="togglePasswordVisibility('reg_password_confirmation', this)" title="{{ __('Lihat Sandi') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Cloudflare Turnstile Box (Graceful Fallback) -->
                        @if($turnstileKey)
                            <div class="d-flex justify-content-center my-3">
                                <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-theme="auto"></div>
                            </div>
                        @endif

                        <!-- Submit Register CTA -->
                        <button type="submit" class="m3-btn-primary" id="btn-register-submit">
                            <span>{{ __('Daftar Akun Baru') }}</span>
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer: Multi-Language Switcher Chips & Copyright -->
            <div class="auth-form-footer">
                <div class="lang-selector-row">
                    <span class="text-secondary small">{{ __('Bahasa:') }}</span>
                    <a href="{{ route('lang.switch', 'id') }}" class="lang-chip {{ app()->getLocale() === 'id' ? 'active' : '' }}">ID</a>
                    <a href="{{ route('lang.switch', 'en') }}" class="lang-chip {{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
                    <a href="{{ route('lang.switch', 'zh') }}" class="lang-chip {{ app()->getLocale() === 'zh' ? 'active' : '' }}">ZH</a>
                </div>
            </div>
        </div>

        <!-- ====================================================================
             RIGHT SECTION: Info & Showcase Card (Desktop >= 992px)
             ==================================================================== -->
        <div class="auth-showcase-column">
            <div>
                <!-- Top Status Badge -->
                <div class="showcase-badge-row">
                    <span class="m3-status-chip">
                        <span class="m3-status-dot"></span>
                        <span>{{ __('Baileys Engine Online') }}</span>
                    </span>
                    <span class="m3-version-chip">JAWIRBLAST v2.4</span>
                </div>

                <!-- Showcase Brand Header -->
                <div class="showcase-brand">
                    <div class="showcase-brand-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h3 class="showcase-title">JAWIRBLAST</h3>
                        <p class="showcase-subtitle">{{ __('High-Performance WhatsApp Marketing Engine') }}</p>
                    </div>
                </div>

                <!-- Feature Highlights -->
                <div class="showcase-feature-cards">
                    <div class="showcase-feature-item">
                        <div class="showcase-feature-icon"><i class="fas fa-qrcode"></i></div>
                        <div>
                            <div class="feature-title">{{ __('Multi-Session Baileys QR') }}</div>
                            <div class="feature-desc">{{ __('Koneksi multi-device WhatsApp langsung tanpa batas API resmi & bebas biaya langganan bulanan.') }}</div>
                        </div>
                    </div>

                    <div class="showcase-feature-item">
                        <div class="showcase-feature-icon"><i class="fas fa-shield-halved"></i></div>
                        <div>
                            <div class="feature-title">{{ __('Anti-Ban Pacing & Spintax') }}</div>
                            <div class="feature-desc">{{ __('Jeda dinamis, simulasi ketikan manusia, dan permutasi pesan unik untuk mitigasi blokir nomor.') }}</div>
                        </div>
                    </div>

                    <div class="showcase-feature-item">
                        <div class="showcase-feature-icon"><i class="fas fa-bolt"></i></div>
                        <div>
                            <div class="feature-title">{{ __('High-Throughput Queue') }}</div>
                            <div class="feature-desc">{{ __('Dispatcher antrean ribuan pesan dengan pelacakan status pengiriman real-time.') }}</div>
                        </div>
                    </div>

                    <div class="showcase-feature-item">
                        <div class="showcase-feature-icon"><i class="fab fa-telegram"></i></div>
                        <div>
                            <div class="feature-title">{{ __('Telegram Bot Integration') }}</div>
                            <div class="feature-desc">{{ __('Login instan 1-klik, bot notifikasi darurat admin, dan pemantauan siaran kampanye.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Admin Box -->
            <div class="showcase-support-card">
                <div class="support-header">
                    <i class="fas fa-headset text-success"></i>
                    <span>{{ __('Kontak & Bantuan Teknis:') }}</span>
                </div>
                <div class="support-links">
                    <a href="https://wa.me/6282269245660" target="_blank" class="support-link">
                        <i class="fab fa-whatsapp text-success"></i>
                        <span>082269245660 (WhatsApp Admin)</span>
                    </a>
                    <a href="https://t.me/dzulfikrialifajri" target="_blank" class="support-link">
                        <i class="fab fa-telegram" style="color: #0088cc;"></i>
                        <span>@dzulfikrialifajri (Telegram)</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ========================================================================
     Telegram Authentication Polling Modal (M3 Concentric Squircle Dialog)
     ======================================================================== -->
<dialog id="modal-telegram">
    <div style="text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: #0088cc; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1rem; box-shadow: 0 4px 16px rgba(0, 136, 204, 0.3);">
            <i class="fab fa-telegram"></i>
        </div>
        <h4 style="font-weight: 700; margin: 0 0 8px 0; color: var(--on-surface);">{{ __('Otentikasi Telegram') }}</h4>
        <p style="font-size: 0.875rem; color: var(--outline); margin: 0 0 1.5rem 0; line-height: 1.45;">
            {{ __('Buka bot Telegram dan klik tombol') }} <b>START</b> {{ __('untuk verifikasi login otomatis.') }}
        </p>

        <div id="telegram-loading" style="margin: 1.5rem 0;">
            <progress class="circle" style="margin: 0 auto 12px auto;"></progress>
            <p style="font-size: 0.85rem; color: var(--outline); margin: 0;">{{ __('Menghubungkan ke bot Telegram...') }}</p>
        </div>

        <div id="telegram-ready" style="display: none; margin: 1.5rem 0;">
            <a id="telegram-deep-link" href="#" target="_blank" class="m3-btn-telegram" style="margin-bottom: 12px;">
                <i class="fas fa-up-right-from-square"></i>
                <span>{{ __('Buka Bot Telegram Sekarang') }}</span>
            </a>
            <p style="font-size: 0.8125rem; color: var(--outline); margin: 0;">
                <i class="fas fa-spinner fa-spin me-1"></i>
                {{ __('Menunggu konfirmasi di aplikasi Telegram Anda...') }}
            </p>
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="button" class="button transparent" onclick="document.getElementById('modal-telegram').close()" style="border-radius: 9999px;">
                {{ __('Tutup') }}
            </button>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
    // Tab switching controller
    function switchAuthTab(tabName) {
        const loginBtn = document.getElementById('tab-btn-login');
        const registerBtn = document.getElementById('tab-btn-register');
        const loginPane = document.getElementById('pane-login');
        const registerPane = document.getElementById('pane-register');
        const titleEl = document.getElementById('auth-title');
        const subtitleEl = document.getElementById('auth-subtitle');

        if (tabName === 'register') {
            titleEl.innerText = "{{ __('Daftar Akun Baru') }}";
            subtitleEl.innerText = "{{ __('Lengkapi formulir untuk membuat akun broadcast baru.') }}";

            loginBtn.classList.remove('active');
            loginBtn.setAttribute('aria-selected', 'false');

            registerBtn.classList.add('active');
            registerBtn.setAttribute('aria-selected', 'true');

            loginPane.style.display = 'none';
            registerPane.style.display = 'block';

            if (history.replaceState) {
                history.replaceState(null, null, '#register');
            }
        } else {
            titleEl.innerText = "{{ __('Selamat Datang') }}";
            subtitleEl.innerText = "{{ __('Silakan masuk untuk mengelola kampanye siaran WhatsApp Anda.') }}";

            registerBtn.classList.remove('active');
            registerBtn.setAttribute('aria-selected', 'false');

            loginBtn.classList.add('active');
            loginBtn.setAttribute('aria-selected', 'true');

            registerPane.style.display = 'none';
            loginPane.style.display = 'block';

            if (history.replaceState) {
                history.replaceState(null, null, '#login');
            }
        }
    }

    // Toggle password visibility
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    // Initialize state on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-switch to register tab on error with register input or initial parameter
        @if (old('name') || $errors->has('name') || $errors->has('password_confirmation') || ($initialTab ?? '') === 'register')
            switchAuthTab('register');
        @else
            if (window.location.hash === '#register') {
                switchAuthTab('register');
            }
        @endif

        // Theme Toggle Sync
        const authThemeToggle = document.getElementById('authThemeToggle');
        const authThemeIcon = document.getElementById('authThemeIcon');

        function updateAuthThemeIcon() {
            const isDark = document.body.classList.contains('dark') || document.documentElement.getAttribute('data-ui') === 'dark';
            if (authThemeIcon) {
                if (isDark) {
                    authThemeIcon.classList.remove('fa-moon');
                    authThemeIcon.classList.add('fa-sun');
                } else {
                    authThemeIcon.classList.remove('fa-sun');
                    authThemeIcon.classList.add('fa-moon');
                }
            }
        }
        updateAuthThemeIcon();

        if (authThemeToggle) {
            authThemeToggle.addEventListener('click', function () {
                if (typeof toggleTheme === 'function') {
                    toggleTheme();
                } else {
                    const isDark = document.body.classList.contains('dark');
                    if (isDark) {
                        document.body.classList.remove('dark');
                        document.body.classList.add('light');
                        document.documentElement.setAttribute('data-ui', 'light');
                        localStorage.setItem('jawirblast_theme', 'light');
                    } else {
                        document.body.classList.remove('light');
                        document.body.classList.add('dark');
                        document.documentElement.setAttribute('data-ui', 'dark');
                        localStorage.setItem('jawirblast_theme', 'dark');
                    }
                }
                updateAuthThemeIcon();
            });
        }

        // Telegram Polling Modal Implementation
        const btnTg = document.getElementById('btn-telegram-login');
        const modalTg = document.getElementById('modal-telegram');
        const loadingTg = document.getElementById('telegram-loading');
        const readyTg = document.getElementById('telegram-ready');
        const deepLinkEl = document.getElementById('telegram-deep-link');
        let pollInterval = null;

        if (btnTg && modalTg) {
            btnTg.addEventListener('click', async function () {
                modalTg.showModal();
                loadingTg.style.display = 'block';
                readyTg.style.display = 'none';

                try {
                    const requestUrl = "{{ Route::has('auth.telegram.request') ? route('auth.telegram.request') : route('telegram.token') }}";
                    const res = await fetch(requestUrl);
                    const data = await res.json();

                    if (data.success && data.deep_link) {
                        loadingTg.style.display = 'none';
                        readyTg.style.display = 'block';
                        deepLinkEl.href = data.deep_link;

                        // Start AJAX polling
                        if (pollInterval) clearInterval(pollInterval);
                        pollInterval = setInterval(async function () {
                            try {
                                const checkRes = await fetch("{{ url('/auth/telegram/check') }}/" + data.token);
                                const checkData = await checkRes.json();

                                if (checkData.authenticated && checkData.redirect_url) {
                                    clearInterval(pollInterval);
                                    window.location.href = checkData.redirect_url;
                                }
                            } catch (err) {
                                console.error("Telegram polling error:", err);
                            }
                        }, 2000);
                    } else {
                        throw new Error("Invalid response format");
                    }
                } catch (e) {
                    console.error("Failed to generate telegram token:", e);
                    loadingTg.innerHTML = '<p style="color: var(--error); font-size: 0.85rem;">{{ __("Gagal memuat bot Telegram. Pastikan konfigurasi bot aktif.") }}</p>';
                }
            });

            modalTg.addEventListener('close', function () {
                if (pollInterval) clearInterval(pollInterval);
            });
        }
    });
</script>
@endpush
