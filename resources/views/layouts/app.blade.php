<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-ui="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'JAWIRBLAST' }} — WhatsApp Broadcast Engine</title>

    <!-- BeerCSS Material You & Google Material Symbols -->
    <link href="https://cdn.jsdelivr.net/npm/beercss@3.9.4/dist/cdn/beer.min.css" rel="stylesheet">
    <script type="module" src="https://cdn.jsdelivr.net/npm/beercss@3.9.4/dist/cdn/beer.min.js"></script>
    <script type="module" src="https://cdn.jsdelivr.net/npm/material-dynamic-colors@1.1.2/dist/cdn/material-dynamic-colors.min.js"></script>

    <style>
        :root {
            --primary: #006c4c;
            --primary-container: #89f8c7;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--surface, #f7f9f8);
            color: var(--on-surface, #191c1a);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--surface-container, #ffffff);
            border-radius: 1.5rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            padding: 2rem;
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
                border: 1px solid var(--outline-variant, #e0e3e0) !important;
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
</head>
<body class="surface light">

    @if (session('success'))
        <div class="snackbar top active green white-text">
            <i>check_circle</i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="snackbar top active red white-text">
            <i>error</i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @php
        $isAuthPage = request()->routeIs('login', 'register');
    @endphp

    @if (!$isAuthPage)
        <!-- Desktop Left Navigation Rail (m & l viewports) -->
        <nav class="left m l surface-container">
            <header class="padding">
                <i class="extra primary-text">send</i>
                <h6 class="max bold primary-text no-margin">JAWIRBLAST</h6>
            </header>
            <a href="/dashboard" class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                <i>dashboard</i>
                <span>{{ __('nav.dashboard') }}</span>
            </a>
            <a href="/campaigns" class="{{ request()->is('campaigns*') ? 'active' : '' }}">
                <i>campaign</i>
                <span>{{ __('nav.campaigns') }}</span>
            </a>
            <a href="/contacts" class="{{ request()->is('contacts') ? 'active' : '' }}">
                <i>contacts</i>
                <span>{{ __('nav.contacts') }}</span>
            </a>
            <a href="/contacts/groups" class="{{ request()->is('contacts/groups*') ? 'active' : '' }}">
                <i>group_work</i>
                <span>{{ __('nav.contact_groups') }}</span>
            </a>
            <a href="/whatsapp" class="{{ request()->is('whatsapp*') || request()->is('wa*') ? 'active' : '' }}">
                <i>qr_code</i>
                <span>{{ __('nav.whatsapp_session') }}</span>
            </a>

            <div class="max"></div>
            <div class="divider"></div>

            <!-- Multi-Language Switcher (ID, EN, ZH) -->
            <div class="row middle-align padding small no-margin">
                <a href="{{ route('lang.switch', 'id') }}" class="chip small {{ app()->getLocale() === 'id' ? 'fill' : '' }}">ID</a>
                <a href="{{ route('lang.switch', 'en') }}" class="chip small {{ app()->getLocale() === 'en' ? 'fill' : '' }}">EN</a>
                <a href="{{ route('lang.switch', 'zh') }}" class="chip small {{ app()->getLocale() === 'zh' ? 'fill' : '' }}">ZH</a>
            </div>

            <!-- Dark / Light Theme Toggle -->
            <button type="button" class="circle transparent theme-toggle" data-ui="toggle" onclick="toggleTheme()" title="{{ __('nav.toggle_theme') }}">
                <i id="theme-icon">dark_mode</i>
            </button>

            @auth
            <div class="padding center-align no-margin">
                <span class="small bold text-secondary block truncate">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="no-margin margin-top">
                    @csrf
                    <button type="submit" class="button border error round small responsive">
                        <i>logout</i>
                        <span>{{ __('nav.logout') }}</span>
                    </button>
                </form>
            </div>
            @endauth
        </nav>

        <!-- Mobile Top App Bar (s viewport) -->
        <nav class="top s surface-container">
            <i class="primary-text">send</i>
            <h6 class="max bold primary-text no-margin">JAWIRBLAST</h6>
            <a href="{{ route('lang.switch', 'id') }}" class="chip small {{ app()->getLocale() === 'id' ? 'fill' : '' }}">ID</a>
            <a href="{{ route('lang.switch', 'en') }}" class="chip small {{ app()->getLocale() === 'en' ? 'fill' : '' }}">EN</a>
            <a href="{{ route('lang.switch', 'zh') }}" class="chip small {{ app()->getLocale() === 'zh' ? 'fill' : '' }}">ZH</a>
            <button type="button" class="circle transparent theme-toggle" data-ui="toggle" onclick="toggleTheme()">
                <i id="theme-icon-m">dark_mode</i>
            </button>
        </nav>

        <!-- Mobile Bottom Navigation Bar (s viewport) -->
        <nav class="bottom s surface-container">
            <a href="/dashboard" class="{{ request()->is('dashboard*') ? 'active' : '' }}">
                <i>dashboard</i>
                <span>{{ __('nav.dashboard') }}</span>
            </a>
            <a href="/campaigns" class="{{ request()->is('campaigns*') ? 'active' : '' }}">
                <i>campaign</i>
                <span>{{ __('nav.campaigns') }}</span>
            </a>
            <a href="/contacts" class="{{ request()->is('contacts') ? 'active' : '' }}">
                <i>contacts</i>
                <span>{{ __('nav.contacts') }}</span>
            </a>
            <a href="/whatsapp" class="{{ request()->is('whatsapp*') || request()->is('wa*') ? 'active' : '' }}">
                <i>qr_code</i>
                <span>{{ __('nav.whatsapp_session') }}</span>
            </a>
            <a href="/contacts/groups" class="{{ request()->is('contacts/groups*') ? 'active' : '' }}">
                <i>group_work</i>
                <span>{{ __('nav.contact_groups') }}</span>
            </a>
        </nav>
    @endif

    <main class="responsive surface padding">
        @yield('content')
    </main>

    <footer class="center-align padding text-secondary small">
        JAWIRBLAST &copy; {{ date('Y') }} — WhatsApp High-Performance Broadcast & Telegram Integration
    </footer>

    <script>
    function toggleTheme() {
        const isDark = document.body.classList.contains('dark');
        if (isDark) {
            document.body.classList.remove('dark');
            document.body.classList.add('light');
            document.documentElement.setAttribute('data-ui', 'light');
            localStorage.setItem('jawirblast_theme', 'light');
            updateThemeIcons('dark_mode');
        } else {
            document.body.classList.remove('light');
            document.body.classList.add('dark');
            document.documentElement.setAttribute('data-ui', 'dark');
            localStorage.setItem('jawirblast_theme', 'dark');
            updateThemeIcons('light_mode');
        }
    }
    function updateThemeIcons(icon) {
        const iconEl = document.getElementById('theme-icon');
        if (iconEl) iconEl.textContent = icon;
        const iconM = document.getElementById('theme-icon-m');
        if (iconM) iconM.textContent = icon;
    }
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('jawirblast_theme') || '{{ session("theme", "light") }}';
        if (savedTheme === 'dark') {
            document.body.classList.remove('light');
            document.body.classList.add('dark');
            document.documentElement.setAttribute('data-ui', 'dark');
            updateThemeIcons('light_mode');
        }
    });
    </script>

    @stack('scripts')
</body>
</html>
