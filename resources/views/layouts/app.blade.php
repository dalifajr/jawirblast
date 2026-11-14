<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'JAWIRBLAST' }} — {{ __('nav.brand_tagline') }}</title>

    <!-- Early Theme Initializer (Prevents FOIT / Flash of Wrong Theme) -->
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('jawirblast_theme') || 
                                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            } catch (e) {}
        })();
    </script>

    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    <!-- Font Awesome 6.5.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- M3 Expressive Lavender Design Tokens & Styles -->
    <link rel="stylesheet" href="{{ asset('css/app-dashboard.css') }}?v={{ time() }}">

    <!-- Design System Compatibility Tokens (beercss & material-dynamic-colors) -->
    <meta name="design-framework" content="beercss material-dynamic-colors">
    <script type="module" src="https://cdn.jsdelivr.net/npm/material-dynamic-colors@1.1.2/dist/cdn/material-dynamic-colors.min.js"></script>

    <style>
        :root {
            --primary: #65558f;
            --primary-container: #eaddff;
            --sidebar-width: 264px;
            --top-navbar-height: 64px;
        }

        /* === CONNECTED HEADER & SIDEBAR WITH ROUNDED CORNERS (M3 EXPRESSIVE CANVAS) === */
        /* Reference: D:\bot_tele_jualan\web\public\css\app.css line 4019-4049 */
        @media (min-width: 992px) {
            body {
                background-color: var(--surface-container-low, #f3edf7) !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .m3-top-navbar {
                position: fixed !important;
                top: 0 !important;
                left: 264px !important;
                width: calc(100% - 264px) !important;
                height: 64px !important;
                min-height: 64px !important;
                z-index: 1030 !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-bottom: none !important;
                border: none !important;
                box-shadow: none !important;
                padding-left: 1.5rem !important;
                padding-right: 1.5rem !important;
            }

            #sidebar.sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 264px !important;
                height: 100vh !important;
                z-index: 1040 !important;
                display: flex !important;
                flex-direction: column !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-right: none !important;
                border: none !important;
                box-shadow: none !important;
                overflow-y: auto !important;
                overscroll-behavior: contain !important;
                box-sizing: border-box !important;
                transform: none !important;
                scrollbar-width: none !important;
            }

            #sidebar.sidebar::-webkit-scrollbar {
                display: none !important;
            }

            .app-container {
                padding-top: 64px !important;
                min-height: 100vh !important;
                background: var(--surface-container-low, #f3edf7) !important;
                box-sizing: border-box !important;
            }

            .main-content {
                margin-left: 264px !important;
                width: calc(100% - 264px) !important;
                min-height: calc(100vh - 64px) !important;
                box-sizing: border-box !important;
                background-color: var(--surface-container-lowest, #ffffff) !important;
                border-top-left-radius: 28px !important;
                border-top: 1px solid var(--outline-variant, #cac4d0) !important;
                border-left: 1px solid var(--outline-variant, #cac4d0) !important;
                position: relative !important;
                z-index: 10 !important;
                box-shadow: -2px -2px 14px rgba(0, 0, 0, 0.03) !important;
                padding-bottom: 2rem !important;
            }

            [data-bs-theme="dark"] body,
            [data-bs-theme="dark"] #sidebar.sidebar,
            [data-bs-theme="dark"] .m3-top-navbar,
            [data-bs-theme="dark"] .app-container {
                background: var(--surface-container-low, #1d1b20) !important;
            }

            [data-bs-theme="dark"] .main-content {
                background-color: var(--surface-container-lowest, #0f0d13) !important;
                border-top-color: var(--outline-variant, #49454f) !important;
                border-left-color: var(--outline-variant, #49454f) !important;
            }

            #mobileMenuBtn, #sidebarCloseBtn, .sidebar-backdrop, .sidebar-overlay, .mobile-bottom-nav {
                display: none !important;
            }
        }

        /* === PAGE LOADER OVERLAY: MATERIAL DESIGN 3 SPINNER POPUP === */
        #pageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 0, 0, 0.32);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 1;
            visibility: visible;
            transition: opacity 0.25s cubic-bezier(0.2, 0, 0, 1), visibility 0.25s cubic-bezier(0.2, 0, 0, 1);
            pointer-events: all;
        }

        #pageLoader.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .m3-loader-card {
            background: var(--surface-container-high, #eceef4);
            color: var(--on-surface, #191c20);
            border: 1px solid var(--outline-variant, #c4c6d0);
            border-radius: var(--radius-xl, 24px);
            padding: 16px 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
            user-select: none;
            animation: m3LoaderCardPop 0.25s cubic-bezier(0.2, 0, 0, 1);
        }

        [data-bs-theme="dark"] .m3-loader-card {
            background: var(--surface-container-high, #2b2930) !important;
            color: var(--on-surface, #e6e0e9) !important;
            border-color: var(--outline-variant, #49454f) !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45) !important;
        }

        @keyframes m3LoaderCardPop {
            from {
                transform: scale(0.92);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .m3-spinner-circle {
            width: 26px;
            height: 26px;
            border: 3.5px solid var(--secondary-container, #e8def8);
            border-top-color: var(--primary, #65558f);
            border-radius: 50%;
            animation: m3SpinnerRotate 0.85s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            flex-shrink: 0;
        }

        @keyframes m3SpinnerRotate {
            to {
                transform: rotate(360deg);
            }
        }

        .m3-loader-text {
            font-family: var(--font-poppins, sans-serif);
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        /* === SIDEBAR OVERLAY (SCRIM DI MOBILE) === */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1055;
            opacity: 0;
            transition: opacity 0.25s cubic-bezier(0.2, 0, 0, 1);
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* === MOBILE (< 992px): BOTTOM SHEET CARD DRAWER & BOTTOM NAVIGATION === */
        @media (max-width: 991.98px) {
            body {
                padding-bottom: calc(68px + env(safe-area-inset-bottom)) !important;
            }

            #sidebar.sidebar {
                position: fixed !important;
                top: auto !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100vw !important;
                max-width: 100% !important;
                height: 74vh;
                max-height: 94vh;
                border-radius: 28px 28px 0 0 !important;
                border-top: 1px solid var(--outline-variant, #cac4d0) !important;
                border-right: none !important;
                border-left: none !important;
                border-bottom: none !important;
                background: var(--surface-container-low, #f3edf7) !important;
                box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.16) !important;
                transform: translateY(105%) !important;
                transition: transform 0.35s cubic-bezier(0.2, 0, 0, 1), height 0.35s cubic-bezier(0.2, 0, 0, 1) !important;
                z-index: 1060 !important;
                display: flex !important;
                flex-direction: column !important;
                overflow-y: auto !important;
                overscroll-behavior: contain !important;
                touch-action: pan-y !important;
                padding-bottom: calc(24px + env(safe-area-inset-bottom)) !important;
                will-change: transform, height;
            }

            #sidebar.sidebar.show {
                transform: translateY(0) !important;
            }

            #sidebar.sidebar.is-dragging {
                transition: none !important;
                user-select: none !important;
                -webkit-user-select: none !important;
            }

            #sidebar.sidebar.is-dragging .sheet-handle-bar {
                width: 50px !important;
                height: 5px !important;
                background-color: var(--primary, #65558f) !important;
                opacity: 1 !important;
            }

            .sheet-handle-area {
                width: 100%;
                padding: 10px 0 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: grab;
                touch-action: none;
                user-select: none;
                -webkit-user-select: none;
            }

            .sheet-handle-bar {
                width: 38px;
                height: 4.5px;
                border-radius: 9999px;
                background-color: var(--outline-variant, #cac4d0);
                opacity: 0.85;
                transition: width 0.2s ease, background-color 0.2s ease;
            }

            .m3-top-navbar {
                margin-left: 0 !important;
                width: 100% !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-bottom: 1px solid var(--outline-variant, #cac4d0) !important;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-bottom: 76px !important;
                border-top-left-radius: 0 !important;
                border: none !important;
                background: var(--surface-container-lowest, #ffffff) !important;
            }

            /* Mobile Bottom Navigation Bar */
            .mobile-bottom-nav {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                height: calc(64px + env(safe-area-inset-bottom)) !important;
                padding-bottom: env(safe-area-inset-bottom) !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-top: 1px solid var(--outline-variant, #cac4d0) !important;
                z-index: 1045 !important;
                display: flex !important;
                align-items: stretch !important;
                justify-content: space-around !important;
                box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.04) !important;
            }

            .mobile-nav-item {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 4px 0 !important;
                color: var(--on-surface-variant, #49454f) !important;
                text-decoration: none !important;
                font-family: var(--font-poppins, sans-serif) !important;
                font-size: 0.6875rem !important;
                font-weight: 600 !important;
                line-height: 1.2 !important;
                background: transparent !important;
                border: none !important;
                cursor: pointer !important;
                transition: color 0.15s cubic-bezier(0.2, 0, 0, 1) !important;
                -webkit-tap-highlight-color: transparent !important;
            }

            .mobile-nav-item i {
                font-size: 1.1rem !important;
                margin-bottom: 3px !important;
                width: 52px !important;
                height: 30px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 14px !important;
                transition: background-color 0.2s cubic-bezier(0.2, 0, 0, 1), color 0.2s cubic-bezier(0.2, 0, 0, 1) !important;
            }

            .mobile-nav-item:hover:not(.active) i {
                background: var(--surface-container-high, #e6e0e9) !important;
                color: var(--on-surface, #1d1b20) !important;
            }

            .mobile-nav-item.active {
                color: var(--on-secondary-container, #1d192b) !important;
                font-weight: 700 !important;
            }

            .mobile-nav-item.active i {
                background: var(--secondary-container, #e8def8) !important;
                color: var(--on-secondary-container, #1d192b) !important;
            }

            [data-bs-theme="dark"] .mobile-bottom-nav {
                background: var(--surface-container-low, #1d1b20) !important;
                border-top-color: var(--outline-variant, #49454f) !important;
            }
        }

        /* === M3 EXPRESSIVE SIDEBAR BRAND HEADER === */
        .m3-brand-header {
            position: sticky !important;
            top: 0 !important;
            z-index: 30 !important;
            background: var(--surface-container-low, #f3edf7) !important;
            border-bottom: 1px solid var(--outline-variant, #cac4d0) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 16px !important;
            text-decoration: none !important;
            color: inherit !important;
            min-height: 64px !important;
            box-sizing: border-box !important;
        }

        [data-bs-theme="dark"] .m3-brand-header {
            background: var(--surface-container-low, #1d1b20) !important;
            border-bottom-color: var(--outline-variant, #49454f) !important;
        }

        .m3-brand-header a {
            color: inherit !important;
            text-decoration: none !important;
        }

        .m3-cloud-icon {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            border-radius: 14px !important;
            background-color: var(--primary-container, #eaddff) !important;
            color: var(--primary, #65558f) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.15rem !important;
            flex-shrink: 0 !important;
        }

        .m3-brand-text {
            display: flex !important;
            flex-direction: column !important;
            line-height: 1.2 !important;
            overflow: hidden !important;
        }

        .m3-brand-title {
            font-family: var(--font-poppins, sans-serif) !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em !important;
            color: var(--on-surface, #1d1b20) !important;
            white-space: nowrap !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        .m3-brand-subtitle {
            font-family: var(--font-poppins, sans-serif) !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            color: var(--on-surface-variant, #49454f) !important;
            white-space: nowrap !important;
            text-overflow: ellipsis !important;
            overflow: hidden !important;
        }

        /* === M3 EXPRESSIVE MENU ITEMS & HEADERS === */
        .menu-group {
            padding: 0.35rem 0.5rem !important;
            border-bottom: 1px solid var(--outline-variant, #cac4d0) !important;
        }

        .menu-group:last-of-type {
            border-bottom: none !important;
        }

        .menu-header {
            padding: 0.65rem 1rem 0.25rem 1rem !important;
            font-family: var(--font-poppins, sans-serif) !important;
            font-size: 0.6875rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.06em !important;
            color: var(--on-surface-variant, #49454f) !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .menu-header i {
            font-size: 0.75rem !important;
            opacity: 0.8 !important;
        }

        .menu-items-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 2px !important;
        }

        .menu-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            height: 44px !important;
            margin: 3px 12px !important;
            padding: 0 16px !important;
            border-radius: 9999px !important;
            border: none !important;
            color: var(--on-surface-variant, #49454f) !important;
            text-decoration: none !important;
            font-family: var(--font-poppins, sans-serif) !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            transition: background-color 0.15s cubic-bezier(0.2, 0, 0, 1), color 0.15s cubic-bezier(0.2, 0, 0, 1) !important;
        }

        .menu-item:hover {
            background-color: var(--surface-container-high, #e6e0e9) !important;
            color: var(--on-surface, #1d1b20) !important;
        }

        .menu-item.active {
            background-color: var(--secondary-container, #e8def8) !important;
            color: var(--on-secondary-container, #1d192b) !important;
            font-weight: 600 !important;
        }

        .menu-item i,
        .menu-icon-box i {
            color: inherit !important;
        }

        .menu-icon-box {
            width: 24px !important;
            height: 24px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0.95rem !important;
            flex-shrink: 0 !important;
        }

        .menu-label-text {
            flex-grow: 1 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        /* Sidebar User Mini Footer */
        .sidebar-user-footer {
            position: sticky !important;
            bottom: 0 !important;
            z-index: 25 !important;
            margin-top: auto !important;
            padding: 14px 16px !important;
            border-top: 1px solid var(--outline-variant, #cac4d0) !important;
            background: var(--surface-container-low, #f3edf7) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }

        [data-bs-theme="dark"] .sidebar-user-footer {
            background: var(--surface-container-low, #1d1b20) !important;
            border-top-color: var(--outline-variant, #49454f) !important;
        }

        /* === MODAL & BACKDROP STACKING CONTEXT FIX === */
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal {
            z-index: 1055 !important;
        }
        .dropdown-menu {
            z-index: 1035 !important;
        }

        .m3-avatar-circle {
            width: 38px !important;
            height: 38px !important;
            min-width: 38px !important;
            border-radius: 50% !important;
            background: var(--primary-container, #eaddff) !important;
            color: var(--primary, #65558f) !important;
            font-weight: 700 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0.95rem !important;
        }

        /* Mobile Table to Card Transformation (Pattern from D:\bot_tele_jualan) */
        @media (max-width: 768px) {
            .table-responsive-cards thead {
                display: none;
            }
            .table-responsive-cards tbody,
            .table-responsive-cards tr,
            .table-responsive-cards td {
                display: block;
                width: 100%;
            }
            .table-responsive-cards tr {
                background: var(--surface-container, #ffffff);
                border: 1px solid var(--outline-variant, #cac4d0) !important;
                border-radius: 12px;
                margin-bottom: 1.25rem;
                padding: 1rem;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
                position: relative;
            }
            .table-responsive-cards td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none !important;
                padding: 0.6rem 0 !important;
                text-align: right;
                font-size: 0.9rem;
            }
            .table-responsive-cards td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--on-surface-variant, #64748b);
                text-align: left;
                font-size: 0.8rem;
                text-transform: uppercase;
                margin-right: 1rem;
            }
        }
    </style>

    @stack('styles')
</head>
<body class="light">

    <!-- Material Design 3 Expressive Page Loader Overlay (Android 17 Spinning Card) -->
    <div id="pageLoader" class="fade-out">
        <div class="m3-loader-card d-inline-flex align-items-center gap-3">
            <div class="m3-spinner-circle" role="status" aria-label="{{ __('nav.loading') }}"></div>
            <span class="m3-loader-text">{{ __('nav.loading') }}</span>
        </div>
    </div>

    @php
        $currentLocale = app()->getLocale();
        $user = auth()->user();
    @endphp

    @if(session()->has('admin_impersonator_id'))
    <div class="bg-warning text-warning-emphasis py-2 px-4 d-flex justify-content-between align-items-center position-fixed w-100 shadow-sm" style="z-index: 1070; top: 0; left: 0; font-size: 0.9rem; height: 40px;">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-user-secret"></i> 
            <span>{{ __('users.impersonating_banner', ['name' => Auth::user()->name]) }}</span>
        </div>
        <form action="{{ route('admin.users.stop-impersonating') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-xs btn-outline-dark rounded-pill px-3 py-0 font-weight-bold" style="font-size: 0.8rem; border-width: 2px; line-height: 1.5;">
                <i class="fas fa-sign-out-alt me-1"></i> {{ __('users.stop_impersonating') }}
            </button>
        </form>
    </div>
    @endif

    <div class="app-container">
    <!-- M3 Expressive Sidebar Navigation Drawer (Desktop 264px / Mobile Draggable Bottom Sheet) -->
    <div id="sidebar" class="sidebar">
        <!-- Mobile Bottom Sheet Grab Handle Area (Interactive Swipeable Realtime Gestures) -->
        <div class="sheet-handle-area d-lg-none" id="sheetHandleArea" title="{{ __('nav.swipe_hint') }}">
            <div class="sheet-handle-bar"></div>
        </div>

        <!-- Mobile Sheet Top Header (Visible on Mobile < 992px) -->
        <div class="sheet-header-mobile d-lg-none">
            <div class="d-flex align-items-center justify-content-between px-3.5 py-2.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                <div class="d-flex align-items-center gap-2.5">
                    @include('partials.brand-logo', ['style' => 'width: 32px; height: 32px; min-width: 32px;'])
                    <div class="d-flex flex-column" style="line-height: 1.15;">
                        <div class="d-flex align-items-center gap-1.5">
                            <span class="fw-bold" style="font-size: 0.92rem; color: var(--on-surface);">JAWIRBLAST</span>
                            <span class="m3-badge m3-badge-info" style="font-size: 0.62rem; padding: 1px 6px; border-radius: 9999px;">{{ __('nav.pro_badge') }}</span>
                        </div>
                        <small class="text-secondary" style="font-size: 0.7rem;">{{ __('nav.brand_tagline') }}</small>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-icon rounded-circle" id="sidebarCloseBtn" aria-label="{{ __('nav.close_menu') }}" style="width: 32px; height: 32px; color: var(--on-surface-variant);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Desktop Sidebar Brand Header (Visible on Desktop >= 992px) -->
        <div class="m3-brand-header d-none d-lg-flex align-items-center justify-content-between">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-3 text-decoration-none" style="color: inherit; overflow: hidden;">
                @include('partials.brand-logo', ['style' => 'width: 40px; height: 40px; min-width: 40px; flex-shrink: 0;'])
                <div class="m3-brand-text">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="m3-brand-title">JAWIRBLAST</span>
                        <span class="m3-badge m3-badge-info" style="font-size: 0.65rem; padding: 2px 8px; border-radius: 9999px;">{{ __('nav.pro_badge') }}</span>
                    </div>
                    <span class="m3-brand-subtitle">{{ __('nav.brand_tagline') }}</span>
                </div>
            </a>
        </div>

        <!-- Navigation Group 1: Menu Utama -->
        <div class="menu-group">
            <div class="menu-header">
                <i class="fas fa-compass"></i>
                <span>{{ __('nav.main_menu') }}</span>
            </div>
            <div class="menu-items-grid">
                <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.dashboard') }}</span>
                </a>
                <a href="{{ route('campaigns.index') }}" class="menu-item {{ request()->routeIs('campaigns.index') || (request()->routeIs('campaigns.*') && !request()->routeIs('campaigns.create')) ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.campaigns') }}</span>
                </a>
                <a href="{{ route('contacts.index') }}" class="menu-item {{ request()->routeIs('contacts.index') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-address-book"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.contacts') }}</span>
                </a>
                <a href="{{ route('contacts.groups.index') }}" class="menu-item {{ request()->routeIs('contacts.groups*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.contact_groups') }}</span>
                </a>
                <a href="{{ route('whatsapp.index') }}" class="menu-item {{ request()->routeIs('whatsapp*') || request()->routeIs('wa*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.whatsapp_session') }}</span>
                </a>
                @if(auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.user_management') }}</span>
                </a>
                @endif
            </div>
        </div>

        <!-- Navigation Group 2: Kampanye Siaran -->
        <div class="menu-group">
            <div class="menu-header">
                <i class="fas fa-paper-plane"></i>
                <span>{{ __('nav.broadcast_menu') }}</span>
            </div>
            <div class="menu-items-grid">
                <a href="{{ route('campaigns.create') }}" class="menu-item {{ request()->routeIs('campaigns.create') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.create_campaign') }}</span>
                </a>
                <a href="{{ route('campaigns.index') }}" class="menu-item">
                    <div class="menu-icon-box">
                        <i class="fas fa-history"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.delivery_logs') }}</span>
                </a>
            </div>
        </div>

        <!-- Navigation Group 3: Pengaturan & Akun -->
        <div class="menu-group">
            <div class="menu-header">
                <i class="fas fa-cog"></i>
                <span>{{ __('nav.system_menu') }}</span>
            </div>
            <div class="menu-items-grid">
                <a href="{{ route('profile') }}" class="menu-item {{ request()->routeIs('profile*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.profile') }}</span>
                </a>
                @if(auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                    <div class="menu-icon-box">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.user_management') }}</span>
                </a>
                @endif
                <a href="{{ route('dashboard') }}#telegram" class="menu-item">
                    <div class="menu-icon-box">
                        <i class="fab fa-telegram"></i>
                    </div>
                    <span class="menu-label-text">{{ __('nav.telegram_settings') }}</span>
                </a>
            </div>
        </div>

        <!-- Sidebar User Mini Footer -->
        @auth
        <div class="sidebar-user-footer">
            <div class="m3-avatar-circle" style="width: 36px; height: 36px; font-size: 0.9rem;">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div class="sidebar-user-info">
                <div class="fw-bold small text-truncate" style="color: var(--on-surface);">{{ $user->name }}</div>
                <div class="text-secondary small text-truncate" style="font-size: 0.72rem;">{{ __('nav.user_role') }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-icon rounded-circle" title="{{ __('nav.logout') }}" style="width: 32px; height: 32px; color: var(--on-surface-variant);">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
        @endauth
    </div>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- M3 Expressive Top Platform Bar -->
    <header class="m3-top-navbar d-flex align-items-center px-3 px-md-4">
        <div class="container-fluid d-flex align-items-center justify-content-between p-0">
            <!-- Left Side: Mobile Menu Hamburger & Brand (Visible on Mobile < 992px) -->
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="m3-icon-btn d-lg-none" id="mobileMenuBtn" aria-label="{{ __('nav.menu') }}" title="{{ __('nav.menu') }}">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-flex align-items-center gap-2 d-lg-none">
                    @include('partials.brand-logo', ['style' => 'width: 30px; height: 30px;'])
                    <span class="fw-bold" style="font-size: 0.95rem; color: var(--on-surface);">JAWIRBLAST</span>
                </div>
            </div>

            <!-- Right Controls: Language Dropdown, Theme Toggle, User Profile -->
            <div class="d-flex align-items-center gap-2 ms-auto">
                <!-- Multi-Language Dropdown -->
                <div class="dropdown">
                    <button class="language-dropdown-btn dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('nav.select_language') }}">
                        @if ($currentLocale === 'id')
                            <span>🇮🇩</span> <span>ID</span>
                        @elseif ($currentLocale === 'zh')
                            <span>🇨🇳</span> <span>ZH</span>
                        @else
                            <span>🇬🇧</span> <span>EN</span>
                        @endif
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-m3" aria-labelledby="languageDropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between {{ $currentLocale === 'id' ? 'active' : '' }}" href="{{ route('lang.switch', 'id') }}">
                                <span class="d-flex align-items-center gap-2">
                                    <span>🇮🇩</span>
                                    <span>Bahasa Indonesia</span>
                                </span>
                                @if($currentLocale === 'id')
                                    <i class="fas fa-check small text-primary"></i>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between {{ $currentLocale === 'en' ? 'active' : '' }}" href="{{ route('lang.switch', 'en') }}">
                                <span class="d-flex align-items-center gap-2">
                                    <span>🇬🇧</span>
                                    <span>English</span>
                                </span>
                                @if($currentLocale === 'en')
                                    <i class="fas fa-check small text-primary"></i>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between {{ $currentLocale === 'zh' ? 'active' : '' }}" href="{{ route('lang.switch', 'zh') }}">
                                <span class="d-flex align-items-center gap-2">
                                    <span>🇨🇳</span>
                                    <span>中文 (简体)</span>
                                </span>
                                @if($currentLocale === 'zh')
                                    <i class="fas fa-check small text-primary"></i>
                                @endif
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Dark / Light Theme Toggle -->
                <button type="button" class="m3-icon-btn" id="themeToggle" title="{{ __('nav.toggle_theme') }}" aria-label="{{ __('nav.toggle_theme') }}">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                @auth
                <!-- User Profile Dropdown Menu -->
                <div class="dropdown">
                    <button class="m3-user-btn dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="m3-user-avatar">
                            {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                        </div>
                        <span class="d-none d-md-inline fw-semibold text-truncate" style="max-width: 130px; font-size: 0.85rem;">
                            {{ $user->name }}
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-m3 shadow-sm" aria-labelledby="userDropdown" style="min-width: 240px;">
                        <li class="px-3 py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                            <div class="fw-bold text-truncate" style="color: var(--on-surface);">{{ $user->name }}</div>
                            <div class="text-secondary small text-truncate">{{ $user->email }}</div>
                            <div class="mt-1">
                                @if ($user->telegram_id)
                                    <span class="m3-badge m3-badge-success" style="font-size: 0.68rem;">
                                        <i class="fab fa-telegram"></i> TG: {{ $user->telegram_id }}
                                    </span>
                                @else
                                    <span class="m3-badge m3-badge-secondary" style="font-size: 0.68rem;">
                                        <i class="fab fa-telegram"></i> {{ __('nav.telegram_not_connected') }}
                                    </span>
                                @endif
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('profile') }}">
                                <i class="fas fa-user-circle text-primary"></i>
                                <span>{{ __('nav.profile') }}</span>
                            </a>
                        </li>
                        @if(auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.users.index') }}">
                                <i class="fas fa-users-cog text-info"></i>
                                <span>{{ __('nav.user_management') }}</span>
                            </a>
                        </li>
                        @endif
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="m-0 p-1">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2 py-2">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>{{ __('nav.logout') }}</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Canvas (Offset 264px on Desktop) -->
    <div class="main-content">
        <main class="container-fluid px-3 px-md-4 py-4" style="max-width: 1400px; margin: 0 auto;">
            <!-- Flash Message Alerts -->
            @if (session('success'))
                <div class="m3-alert m3-alert-success d-flex align-items-center justify-content-between shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle fs-5"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="m3-alert m3-alert-danger d-flex align-items-center justify-content-between shadow-sm">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle fs-5"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
                </div>
            @endif

            @yield('content')
        </main>

        <!-- Footer Copyright -->
        <footer class="text-center py-4 text-secondary small">
            <p class="m-0">{!! __('dashboard.app_copyright', ['year' => date('Y')]) !!}</p>
        </footer>
    </div>
    </div><!-- /.app-container -->

    <!-- Mobile Bottom Navigation Bar (Visible on Viewports < 992px) -->
    <nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="{{ __('nav.menu') }}">
        <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" title="{{ __('nav.dashboard') }}">
            <i class="fas fa-chart-pie"></i>
            <span>{{ __('nav.dashboard') }}</span>
        </a>
        <a href="{{ route('campaigns.index') }}" class="mobile-nav-item {{ request()->routeIs('campaigns*') ? 'active' : '' }}" title="{{ __('nav.campaigns') }}">
            <i class="fas fa-bullhorn"></i>
            <span>{{ __('nav.campaigns') }}</span>
        </a>
        <a href="{{ route('contacts.index') }}" class="mobile-nav-item {{ request()->routeIs('contacts.index') ? 'active' : '' }}" title="{{ __('nav.contacts') }}">
            <i class="fas fa-address-book"></i>
            <span>{{ __('nav.contacts') }}</span>
        </a>
        <a href="{{ route('whatsapp.index') }}" class="mobile-nav-item {{ request()->routeIs('whatsapp*') || request()->routeIs('wa*') ? 'active' : '' }}" title="{{ __('nav.whatsapp_session') }}">
            <i class="fab fa-whatsapp"></i>
            <span>{{ __('nav.whatsapp_session') }}</span>
        </a>
        <button type="button" class="mobile-nav-item btn border-0 bg-transparent" id="bottomNavMenuToggle" aria-label="{{ __('nav.more_menu') }}" title="{{ __('nav.more_menu') }}">
            <i class="fas fa-bars"></i>
            <span>{{ __('nav.more_menu') }}</span>
        </button>
    </nav>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- M3 Expressive Interactive Controller: Page Loader, Theme & Mobile Card Bottom Sheet -->
    <script>
        (function() {
            // ==============================================================
            // 1. PAGE LOADER (Android 17 Material You Spinning Popup)
            // ==============================================================
            const pageLoader = document.getElementById('pageLoader');
            window.showPageLoader = function() {
                if (pageLoader) {
                    pageLoader.classList.remove('fade-out');
                }
            };
            window.hidePageLoader = function() {
                if (pageLoader) {
                    pageLoader.classList.add('fade-out');
                }
            };

            // Auto dismiss on page load
            window.addEventListener('load', function() {
                setTimeout(window.hidePageLoader, 80);
            });
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    window.hidePageLoader();
                }
            });

            // Trigger loader on navigation links (excluding modals, anchor hashtags, js voids, blank targets)
            document.addEventListener('click', function(e) {
                const targetLink = e.target.closest('a');
                if (!targetLink) return;

                const href = targetLink.getAttribute('href');
                const target = targetLink.getAttribute('target');
                const isDownload = targetLink.hasAttribute('download');
                const isDataToggle = targetLink.hasAttribute('data-bs-toggle');

                if (href && !href.startsWith('#') && !href.startsWith('javascript:') && 
                    target !== '_blank' && !isDownload && !isDataToggle && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                    // Cek apakah internal link
                    try {
                        const url = new URL(targetLink.href, window.location.origin);
                        if (url.origin === window.location.origin && url.pathname !== window.location.pathname) {
                            window.showPageLoader();
                        }
                    } catch (err) {}
                }
            });

            // Trigger loader on standard non-ajax form submissions
            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (!form.hasAttribute('data-no-loader') && !form.hasAttribute('target')) {
                    window.showPageLoader();
                }
            });

            // ==============================================================
            // 2. THEME TOGGLE CONTROLLER
            // ==============================================================
            const themeToggleBtn = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');

            function syncThemeIcon(theme) {
                if (!themeIcon) return;
                if (theme === 'dark') {
                    themeIcon.classList.remove('fa-moon');
                    themeIcon.classList.add('fa-sun');
                } else {
                    themeIcon.classList.remove('fa-sun');
                    themeIcon.classList.add('fa-moon');
                }
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            syncThemeIcon(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
                    const newTheme = activeTheme === 'dark' ? 'light' : 'dark';

                    document.documentElement.classList.add('theme-transitioning');
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    document.body.classList.remove('light', 'dark');
                    document.body.classList.add(newTheme);
                    localStorage.setItem('jawirblast_theme', newTheme);
                    syncThemeIcon(newTheme);

                    setTimeout(function() {
                        document.documentElement.classList.remove('theme-transitioning');
                    }, 300);
                });
            }

            // ==============================================================
            // 3. M3 DRAGGABLE BOTTOM SHEET CARD DRAWER (< 992px)
            // ==============================================================
            const sidebar = document.getElementById('sidebar');
            const bottomNavToggle = document.getElementById('bottomNavMenuToggle');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const closeBtn = document.getElementById('sidebarCloseBtn');
            const handleArea = document.getElementById('sheetHandleArea');
            const overlay = document.getElementById('sidebarOverlay');

            if (!sidebar) return;

            // Sheet States: 'closed' | 'normal' | 'expanded'
            let sheetState = 'closed';

            const getNormalHeight = () => Math.round(window.innerHeight * 0.74);
            const getExpandedHeight = () => Math.round(window.innerHeight * 0.94);

            const applySheetState = (state, animate = true) => {
                sheetState = state;
                sidebar.classList.remove('is-dragging');

                if (animate) {
                    sidebar.style.transition = 'transform 0.35s cubic-bezier(0.2, 0, 0, 1), height 0.35s cubic-bezier(0.2, 0, 0, 1)';
                    if (overlay) overlay.style.transition = 'opacity 0.3s cubic-bezier(0.2, 0, 0, 1)';
                } else {
                    sidebar.style.transition = 'none';
                    if (overlay) overlay.style.transition = 'none';
                }

                if (state === 'closed') {
                    sidebar.style.transform = 'translateY(105%)';
                    sidebar.classList.remove('show');
                    if (overlay) {
                        overlay.style.opacity = '0';
                        setTimeout(() => {
                            if (sheetState === 'closed') {
                                overlay.classList.remove('show');
                                overlay.style.opacity = '';
                            }
                        }, 300);
                    }
                    document.body.style.overflow = '';
                    if (bottomNavToggle) bottomNavToggle.classList.remove('active');
                } else if (state === 'normal') {
                    const h = getNormalHeight();
                    sidebar.style.height = h + 'px';
                    sidebar.style.maxHeight = h + 'px';
                    sidebar.style.transform = 'translateY(0)';
                    sidebar.classList.add('show');
                    if (overlay) {
                        overlay.classList.add('show');
                        overlay.style.opacity = '1';
                    }
                    document.body.style.overflow = 'hidden';
                    if (bottomNavToggle) bottomNavToggle.classList.add('active');
                } else if (state === 'expanded') {
                    const h = getExpandedHeight();
                    sidebar.style.height = h + 'px';
                    sidebar.style.maxHeight = h + 'px';
                    sidebar.style.transform = 'translateY(0)';
                    sidebar.classList.add('show');
                    if (overlay) {
                        overlay.classList.add('show');
                        overlay.style.opacity = '1';
                    }
                    document.body.style.overflow = 'hidden';
                    if (bottomNavToggle) bottomNavToggle.classList.add('active');
                }
            };

            const closeMobileSheet = () => applySheetState('closed', true);
            const openMobileSheet = () => applySheetState('normal', true);

            const toggleMobileSheet = () => {
                if (sidebar.classList.contains('show') && sheetState !== 'closed') {
                    closeMobileSheet();
                } else {
                    openMobileSheet();
                }
            };

            if (bottomNavToggle) {
                bottomNavToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleMobileSheet();
                });
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleMobileSheet();
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeMobileSheet();
                });
            }

            if (overlay) {
                overlay.addEventListener('click', closeMobileSheet);
            }

            // Close with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('show') && window.innerWidth < 992) {
                    closeMobileSheet();
                }
            });

            // Close sheet when clicking menu item links on mobile
            const menuLinks = sidebar.querySelectorAll('a.menu-item');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        closeMobileSheet();
                    }
                });
            });

            // ==============================================================
            // 4. REALTIME TOUCH & MOUSE DRAG GESTURES (SWIPE UP / DOWN)
            // ==============================================================
            let isDragging = false;
            let startY = 0;
            let startX = 0;
            let currentY = 0;
            let startTime = 0;
            let startHeight = 0;
            let isHorizontalGesture = false;

            const onDragStart = (clientY, clientX, target) => {
                if (window.innerWidth >= 992) return;
                if (!sidebar.classList.contains('show') || sheetState === 'closed') return;
                if (target && (target.closest('#sidebarCloseBtn') || target.closest('a'))) return;

                isDragging = true;
                isHorizontalGesture = false;
                startY = clientY;
                startX = clientX;
                currentY = clientY;
                startTime = performance.now();
                startHeight = sidebar.offsetHeight || (sheetState === 'expanded' ? getExpandedHeight() : getNormalHeight());

                sidebar.classList.add('is-dragging');
                sidebar.style.transition = 'none';
                if (overlay) overlay.style.transition = 'none';
            };

            const onDragMove = (clientY, clientX, e) => {
                if (!isDragging || window.innerWidth >= 992) return;

                const deltaX = clientX - startX;
                const deltaY = clientY - startY;

                if (!isHorizontalGesture && Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
                    isHorizontalGesture = true;
                    return;
                }
                if (isHorizontalGesture) return;

                if (e && e.cancelable) {
                    e.preventDefault();
                }

                currentY = clientY;
                const normalH = getNormalHeight();
                const expandedH = getExpandedHeight();

                if (sheetState === 'normal') {
                    if (deltaY < 0) {
                        // Geser naik (expand secara realtime)
                        const pullUp = -deltaY;
                        const targetH = startHeight + pullUp;
                        if (targetH <= expandedH) {
                            sidebar.style.height = targetH + 'px';
                            sidebar.style.maxHeight = targetH + 'px';
                            sidebar.style.transform = 'translateY(0)';
                        } else {
                            // Damped overscroll
                            const overflow = targetH - expandedH;
                            const damped = overflow * 0.22;
                            sidebar.style.height = expandedH + 'px';
                            sidebar.style.maxHeight = expandedH + 'px';
                            sidebar.style.transform = `translateY(-${damped}px)`;
                        }
                        if (overlay) overlay.style.opacity = '1';
                    } else {
                        // Geser turun (menuju ditutup)
                        sidebar.style.height = startHeight + 'px';
                        sidebar.style.maxHeight = startHeight + 'px';
                        sidebar.style.transform = `translateY(${deltaY}px)`;

                        const progress = Math.max(0, 1 - (deltaY / (startHeight * 0.75)));
                        if (overlay) overlay.style.opacity = progress.toString();
                    }
                } else { // sheetState === 'expanded'
                    if (deltaY < 0) {
                        const damped = Math.abs(deltaY) * 0.22;
                        sidebar.style.height = startHeight + 'px';
                        sidebar.style.maxHeight = startHeight + 'px';
                        sidebar.style.transform = `translateY(-${damped}px)`;
                        if (overlay) overlay.style.opacity = '1';
                    } else {
                        const pullDown = deltaY;
                        const targetH = startHeight - pullDown;
                        if (targetH >= normalH) {
                            sidebar.style.height = targetH + 'px';
                            sidebar.style.maxHeight = targetH + 'px';
                            sidebar.style.transform = 'translateY(0)';
                            if (overlay) overlay.style.opacity = '1';
                        } else {
                            sidebar.style.height = normalH + 'px';
                            sidebar.style.maxHeight = normalH + 'px';
                            const offsetDown = pullDown - (startHeight - normalH);
                            sidebar.style.transform = `translateY(${offsetDown}px)`;
                            const progress = Math.max(0, 1 - (offsetDown / (normalH * 0.75)));
                            if (overlay) overlay.style.opacity = progress.toString();
                        }
                    }
                }
            };

            const onDragEnd = () => {
                if (!isDragging || window.innerWidth >= 992) return;
                isDragging = false;
                sidebar.classList.remove('is-dragging');

                const deltaY = currentY - startY;
                const elapsed = Math.max(1, performance.now() - startTime);
                const velocity = deltaY / elapsed;
                const moveDist = Math.abs(deltaY);

                if (moveDist < 6) {
                    if (sheetState === 'normal') applySheetState('expanded', true);
                    else if (sheetState === 'expanded') applySheetState('normal', true);
                    return;
                }

                if (sheetState === 'normal') {
                    if (velocity < -0.32 || deltaY < -55) {
                        applySheetState('expanded', true);
                    } else if (velocity > 0.4 || deltaY > 90) {
                        applySheetState('closed', true);
                    } else {
                        applySheetState('normal', true);
                    }
                } else {
                    if (velocity > 0.75 || deltaY > (startHeight * 0.55)) {
                        applySheetState('closed', true);
                    } else if (velocity > 0.3 || deltaY > 65) {
                        applySheetState('normal', true);
                    } else {
                        applySheetState('expanded', true);
                    }
                }
            };

            // Pasang Touch & Mouse Event Listeners pada Handle Area
            const targetAreas = [handleArea, document.querySelector('.sheet-header-mobile')].filter(Boolean);
            targetAreas.forEach(area => {
                area.addEventListener('touchstart', (e) => {
                    if (e.touches.length === 1) {
                        onDragStart(e.touches[0].clientY, e.touches[0].clientX, e.target);
                    }
                }, { passive: true });

                area.addEventListener('mousedown', (e) => {
                    if (e.button === 0) {
                        onDragStart(e.clientY, e.clientX, e.target);
                    }
                });
            });

            window.addEventListener('touchmove', (e) => {
                if (isDragging && e.touches.length === 1) {
                    onDragMove(e.touches[0].clientY, e.touches[0].clientX, e);
                }
            }, { passive: false });

            window.addEventListener('mousemove', (e) => {
                if (isDragging) {
                    onDragMove(e.clientY, e.clientX, e);
                }
            });

            window.addEventListener('touchend', onDragEnd);
            window.addEventListener('mouseup', onDragEnd);
            window.addEventListener('touchcancel', onDragEnd);
        })();
    </script>

    @stack('modals')
    @stack('scripts')
</body>
</html>