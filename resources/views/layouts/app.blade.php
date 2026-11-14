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
    <link rel="stylesheet" href="{{ asset('css/app-dashboard.css') }}">

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

        /* Critical M3 Sidebar & Master Canvas Framework */
        @media (min-width: 992px) {
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
                border-right: 1px solid var(--outline-variant, #cac4d0) !important;
                overflow-y: auto !important;
                box-sizing: border-box !important;
                transform: none !important;
            }
            .m3-top-navbar {
                margin-left: 264px !important;
                width: calc(100% - 264px) !important;
                position: sticky !important;
                top: 0 !important;
                z-index: 1020 !important;
                background: var(--surface-container-lowest, #ffffff) !important;
                border-bottom: 1px solid var(--outline-variant, #cac4d0) !important;
                min-height: 64px !important;
            }
            .main-content {
                margin-left: 264px !important;
                width: calc(100% - 264px) !important;
                min-height: calc(100vh - 64px) !important;
                box-sizing: border-box !important;
                background: var(--surface, #fef7ff) !important;
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
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-bottom: 76px !important;
            }
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
            height: 42px !important;
            margin: 2px 4px !important;
            padding: 0 16px !important;
            border-radius: 9999px !important;
            color: var(--on-surface-variant, #49454f) !important;
            text-decoration: none !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            transition: background-color 0.15s ease, color 0.15s ease !important;
        }

        .menu-item:hover {
            background-color: var(--surface-container-high, #e6e0e9) !important;
            color: var(--on-surface, #1d1b20) !important;
        }

        .menu-item.active {
            background-color: var(--secondary-container, #e8def8) !important;
            color: var(--on-secondary-container, #1d192b) !important;
            font-weight: 700 !important;
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
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2.5 text-decoration-none">
                @include('partials.brand-logo', ['style' => 'width: 36px; height: 36px;'])
                <div class="m3-brand-text">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="m3-brand-title">JAWIRBLAST</span>
                        <span class="m3-badge m3-badge-info" style="font-size: 0.65rem; padding: 2px 7px;">{{ __('nav.pro_badge') }}</span>
                    </div>
                    <span class="m3-brand-subtitle">{{ __('nav.brand_tagline') }}</span>
                </div>
            </a>
            <button type="button" class="btn btn-sm btn-icon rounded-circle d-lg-none" id="sidebarCloseBtn" aria-label="{{ __('nav.close_menu') }}" style="width: 32px; height: 32px; color: var(--on-surface-variant);">
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