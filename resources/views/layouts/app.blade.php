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
    <link rel="stylesheet" href="{{ asset('css/app-dashboard.css') }}?v={{ file_exists(public_path('css/app-dashboard.css')) ? filemtime(public_path('css/app-dashboard.css')) : time() }}">

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
                box-sizing: border-box !important;
                transform: none !important;
                scrollbar-width: none !important;
            }

            #sidebar.sidebar::-webkit-scrollbar {
                display: none !important;
            }

            .m3-top-navbar {
                margin-left: 264px !important;
                width: calc(100% - 264px) !important;
                position: sticky !important;
                top: 0 !important;
                z-index: 1020 !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-bottom: none !important;
                border: none !important;
                box-shadow: none !important;
                min-height: 64px !important;
            }

            .app-container {
                background: var(--surface-container-low, #f3edf7) !important;
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

            #mobileMenuBtn, #sidebarCloseBtn, .sidebar-backdrop, .mobile-bottom-nav {
                display: none !important;
            }
        }

        @media (max-width: 991.98px) {
            #sidebar.sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 280px !important;
                max-width: 85vw !important;
                height: 100vh !important;
                z-index: 1050 !important;
                display: flex !important;
                flex-direction: column !important;
                background: var(--surface-container-low, #f3edf7) !important;
                border-right: 1px solid var(--outline-variant, #cac4d0) !important;
                transform: translateX(-105%) !important;
                transition: transform 0.25s ease !important;
            }
            #sidebar.sidebar.show {
                transform: translateX(0) !important;
                box-shadow: 4px 0 28px rgba(0, 0, 0, 0.25) !important;
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
        }

        /* === M3 EXPRESSIVE SIDEBAR BRAND HEADER === */
        .m3-brand-header {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 20px 16px 16px 16px !important;
            text-decoration: none !important;
            color: inherit !important;
            min-height: 64px !important;
            box-sizing: border-box !important;
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
            margin-top: auto !important;
            padding: 14px 16px !important;
            border-top: 1px solid var(--outline-variant, #cac4d0) !important;
            background: var(--surface-container-low, #f3edf7) !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
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

    @php
        $currentLocale = app()->getLocale();
        $user = auth()->user();
    @endphp

    <!-- M3 Expressive Sidebar Navigation Drawer (264px Desktop / Slide-over Mobile) -->
    <div id="sidebar" class="sidebar">
        <!-- Sidebar Brand Header -->
        <div class="m3-brand-header d-flex align-items-center justify-content-between">
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
            <button type="button" class="btn btn-sm btn-icon rounded-circle d-lg-none" id="sidebarCloseBtn" aria-label="{{ __('nav.close_menu') }}" style="width: 32px; height: 32px; color: var(--on-surface-variant); flex-shrink: 0;">
                <i class="fas fa-times"></i>
            </button>
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
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

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

    <!-- Mobile Bottom Navigation Bar (Visible on Viewports < 992px) -->
    <nav class="mobile-bottom-nav">
        <a href="{{ route('dashboard') }}" class="mobile-bottom-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
            <i class="fas fa-chart-pie"></i>
            <span>{{ __('nav.dashboard') }}</span>
        </a>
        <a href="{{ route('campaigns.index') }}" class="mobile-bottom-item {{ request()->routeIs('campaigns*') ? 'active' : '' }}">
            <i class="fas fa-bullhorn"></i>
            <span>{{ __('nav.campaigns') }}</span>
        </a>
        <a href="{{ route('contacts.index') }}" class="mobile-bottom-item {{ request()->routeIs('contacts.index') ? 'active' : '' }}">
            <i class="fas fa-address-book"></i>
            <span>{{ __('nav.contacts') }}</span>
        </a>
        <a href="{{ route('whatsapp.index') }}" class="mobile-bottom-item {{ request()->routeIs('whatsapp*') || request()->routeIs('wa*') ? 'active' : '' }}">
            <i class="fab fa-whatsapp"></i>
            <span>{{ __('nav.whatsapp_session') }}</span>
        </a>
        <button type="button" class="mobile-bottom-item btn border-0 bg-transparent" id="mobileBottomMenuBtn">
            <i class="fas fa-bars"></i>
            <span>{{ __('nav.menu') }}</span>
        </button>
    </nav>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Unified Theme Toggle & Mobile Sidebar Scripts -->
    <script>
        (function() {
            // Theme Toggle
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

            // Mobile Sidebar Drawer Toggle
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileBottomMenuBtn = document.getElementById('mobileBottomMenuBtn');
            const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

            function openSidebar() {
                if (sidebar) sidebar.classList.add('show');
                if (sidebarBackdrop) sidebarBackdrop.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
                document.body.style.overflow = '';
            }

            if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openSidebar);
            if (mobileBottomMenuBtn) mobileBottomMenuBtn.addEventListener('click', openSidebar);
            if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
            if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);
        })();
    </script>

    @stack('scripts')
</body>
</html>