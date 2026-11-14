<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="{{ request()->cookie('jualan-theme', 'light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('jualan-theme') || (document.cookie.match(/(?:^|; )jualan-theme=([^;]*)/) || [])[1] || 'light';
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                document.documentElement.style.colorScheme = savedTheme;
                if (!document.cookie.includes('jualan-theme=' + savedTheme)) {
                    document.cookie = "jualan-theme=" + savedTheme + ";path=/;max-age=31536000;SameSite=Lax";
                }
            } catch (e) {}
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.login_tab') }} / {{ __('auth.register_tab') }} — {{ config('app.name', 'JAWIRBLAST') }}</title>
    
    {{-- Google Fonts: Poppins (Clean Modern Material 3 Display & Body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {{-- Font Awesome 6 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Design System Stylesheet: auth.css --}}
    <link href="{{ asset('css/auth.css') }}?v={{ file_exists(public_path('css/auth.css')) ? filemtime(public_path('css/auth.css')) : time() }}" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Telegram && window.Telegram.WebApp) {
                const tg = window.Telegram.WebApp;
                tg.ready();
                if (tg.initData) {
                    const loader = document.getElementById('pageLoader');
                    if (loader) loader.classList.remove('fade-out');

                    fetch('{{ route("telegram.callback") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ init_data: tg.initData })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect_url || '{{ route("dashboard") }}';
                        } else {
                            if (loader) loader.classList.add('fade-out');
                        }
                    }).catch(err => {
                        console.error("Telegram WebApp Login Error:", err);
                        if (loader) loader.classList.add('fade-out');
                    });
                }
            }
        });
    </script>
</head>
<body style="cursor: default;">

  {{-- Theme Toggle Button (Circular M3 Action) --}}
  <button class="auth-theme-toggle" id="themeToggle" type="button" title="{{ __('auth.theme_toggle_title') }}" aria-label="{{ __('auth.theme_toggle_title') }}">
      <i class="fas fa-moon fs-5" id="themeIcon"></i>
  </button>

  {{-- Loader Overlay: Material You Android 17 Spinning Card --}}
  <div id="pageLoader" class="fade-out">
    <div class="m3-loader-card">
      <div class="m3-spinner-circle" role="status" aria-label="{{ __('auth.system_loading') }}"></div>
      <span class="fw-semibold small">{{ __('auth.system_loading') }}</span>
    </div>
  </div>

  <div class="login-wrapper">
    {{-- Left Section: Form --}}
    <div class="login-section">
      {{-- Logo & Brand Header --}}
      <div class="brand-header mb-4 d-flex align-items-center gap-3">
        @include('partials.brand-logo', ['style' => 'width: 44px; height: 44px;'])
        <div>
          <h4 class="fw-bold text-primary m-0" style="line-height: 1.15; letter-spacing: -0.5px;">{{ config('app.name', 'JAWIRBLAST') }}</h4>
          <span class="text-secondary small fw-medium">{{ __('auth.app_tagline') }}</span>
        </div>
      </div>

      <div class="mb-3">
        <h3 class="h4 fw-bold text-body mb-1" id="auth-title">{{ __('auth.welcome_title') }}</h3>
        <p class="small text-secondary mb-0" id="auth-subtitle">{{ __('auth.welcome_subtitle') }}</p>
      </div>

      {{-- Auth Tabs Navigation (Concentric M3 Segmented Full Pill) --}}
      <ul class="nav nav-pills nav-fill mb-4" role="tablist">
          <li class="nav-item" role="presentation">
              <button class="nav-link active py-2 small fw-semibold" id="tab-btn-login" type="button" onclick="switchAuthTab('login')">
                  <i class="fas fa-sign-in-alt me-1.5"></i>{{ __('auth.login_tab') }}
              </button>
          </li>
          <li class="nav-item" role="presentation">
              <button class="nav-link py-2 small fw-semibold text-muted" id="tab-btn-register" type="button" onclick="switchAuthTab('register')">
                  <i class="fas fa-user-plus me-1.5"></i>{{ __('auth.register_tab') }}
              </button>
          </li>
      </ul>

      {{-- Alerts Block --}}
      @if(session('error'))
          <div class="alert alert-danger py-2 small bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
          </div>
      @endif
      @if(session('success'))
          <div class="alert alert-success py-2 small bg-success bg-opacity-10 text-success rounded-3 mb-3">
              <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
          </div>
      @endif
      @if($errors->any())
          <div class="alert alert-danger py-2 small bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <ul class="mb-0 ps-3">
                  @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <div class="tab-content" id="authTabsContent">
          
          {{-- TAB MASUK (LOGIN) --}}
          <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
              <form action="{{ route('login.post') }}" method="POST">
                  @csrf
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('auth.email_label') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                          <input type="email" name="email" class="form-control" placeholder="{{ __('auth.email_placeholder') }}" value="{{ old('email') }}" required autofocus autocomplete="username" />
                      </div>
                  </div>
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('auth.password_label') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-lock"></i></span>
                          <input type="password" name="password" id="login-password" class="form-control" placeholder="{{ __('auth.password_placeholder') }}" required autocomplete="current-password" />
                          <button class="btn btn-outline-secondary border border-start-0" type="button" onclick="togglePasswordVisibility('login-password', this)">
                              <i class="fas fa-eye"></i>
                          </button>
                      </div>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mb-3">
                      <div class="form-check">
                          <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck" {{ old('remember') ? 'checked' : '' }}>
                          <label class="form-check-label small text-secondary" for="rememberCheck">{{ __('auth.remember_me') }}</label>
                      </div>
                  </div>

                  {{-- Cloudflare Turnstile (Opsional) --}}
                  @if(config('services.turnstile.site_key'))
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="auto"></div>
                  </div>
                  @endif

                  <button type="submit" class="btn btn-primary-m3 w-100 shadow-none d-flex align-items-center justify-content-center gap-2">
                      <span>{{ __('auth.login_button') }}</span>
                      <i class="fas fa-arrow-right"></i>
                  </button>
              </form>

              <div class="position-relative my-4 text-center">
                  <hr class="text-secondary opacity-25 m-0">
                  <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-secondary fw-semibold" style="font-size: 0.75rem;">{{ __('auth.or_divider') }}</span>
              </div>

              {{-- Tombol Login Telegram --}}
              <div class="d-grid">
                  <button type="button" class="m3-auth-tg-btn" id="btn-telegram-login">
                      <i class="fab fa-telegram fs-5 me-1.5"></i>{{ __('auth.telegram_login_btn') }}
                  </button>
              </div>
          </div>

          {{-- TAB DAFTAR (REGISTER) --}}
          <div class="tab-pane fade" id="register-pane" role="tabpanel">
              <form action="{{ route('register.post') }}" method="POST">
                  @csrf
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('auth.name_label') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                          <input type="text" name="name" class="form-control" placeholder="{{ __('auth.name_placeholder') }}" value="{{ old('name') }}" required />
                      </div>
                  </div>

                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('auth.email_label') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                          <input type="email" name="email" class="form-control" placeholder="{{ __('auth.email_placeholder') }}" value="{{ old('email') }}" required />
                      </div>
                  </div>

                  <div class="row g-2 mb-3">
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('auth.password_label') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-key"></i></span>
                              <input type="password" name="password" id="reg-password" class="form-control" placeholder="{{ __('auth.password_hint') }}" required />
                          </div>
                      </div>
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('auth.password_confirmation_label') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-lock"></i></span>
                              <input type="password" name="password_confirmation" id="reg-password-confirm" class="form-control" placeholder="{{ __('auth.password_confirmation_placeholder') }}" required />
                          </div>
                      </div>
                  </div>

                  {{-- Cloudflare Turnstile (Opsional) --}}
                  @if(config('services.turnstile.site_key'))
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="auto"></div>
                  </div>
                  @endif

                  <button type="submit" id="btn-register" class="btn btn-primary-m3 w-100 shadow-none d-flex align-items-center justify-content-center gap-2">
                      <span>{{ __('auth.register_button') }}</span>
                      <i class="fas fa-user-plus"></i>
                  </button>
              </form>
          </div>
      </div>

      {{-- Footer & Multi-Language Dropdown Menu --}}
      <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
        <div class="text-muted small">
          <span>© {{ date('Y') }}</span>
          <span class="fw-semibold ms-1 text-secondary">{{ config('app.name', 'JAWIRBLAST') }}</span>
          <span class="ms-1 text-secondary">- WhatsApp Engine</span>
        </div>

        {{-- Dropdown Menu Pemilih Bahasa M3 --}}
        <div class="dropdown">
          <button class="language-dropdown-btn dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-globe text-primary"></i>
            <span>
              @if(app()->getLocale() === 'en')
                English (EN)
              @elseif(app()->getLocale() === 'zh')
                中文 (ZH)
              @else
                Indonesia (ID)
              @endif
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-m3 dropdown-menu-end shadow-sm" aria-labelledby="languageDropdown">
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale() === 'id' ? 'active' : '' }}" href="{{ route('lang.switch', 'id') }}">
                <span>🇮🇩</span>
                <span>Bahasa Indonesia</span>
                @if(app()->getLocale() === 'id') <i class="fas fa-check ms-auto text-primary small"></i> @endif
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale() === 'en' ? 'active' : '' }}" href="{{ route('lang.switch', 'en') }}">
                <span>🇬🇧</span>
                <span>English</span>
                @if(app()->getLocale() === 'en') <i class="fas fa-check ms-auto text-primary small"></i> @endif
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale() === 'zh' ? 'active' : '' }}" href="{{ route('lang.switch', 'zh') }}">
                <span>🇨🇳</span>
                <span>中文 (Mandarin)</span>
                @if(app()->getLocale() === 'zh') <i class="fas fa-check ms-auto text-primary small"></i> @endif
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    {{-- Right Section: Info & Showcase (Mirrored from D:\bot_tele_jualan) --}}
    <div class="info-section d-none d-lg-flex">
      <div class="glass-card w-100">
        <div class="glass-header">
          <i class="fas fa-bullhorn me-1"></i>
          <span>{{ __('auth.showcase_title') }}</span>
        </div>
        
        <div class="small text-body mb-4" style="line-height: 1.6;">
          <p class="mb-2">{{ __('auth.showcase_desc') }}</p>
          <ul class="ps-3 mb-0" style="font-size: 0.8125rem;">
            <li>{{ __('auth.feature_adaptive_delay') }}</li>
            <li>{{ __('auth.feature_multi_session') }}</li>
            <li>{{ __('auth.feature_telegram_notify') }}</li>
            <li>{{ __('auth.feature_contact_groups') }}</li>
          </ul>
        </div>

        {{-- Kontak Admin Box (Identik referensi D:\bot_tele_jualan) --}}
        <div class="small mb-4 p-3 rounded-3 border" style="background-color: var(--surface-container-low); border-color: var(--outline-variant) !important;">
            <strong class="d-block mb-2 text-body">{{ __('auth.contact_admin_title') }}</strong>
            <a href="https://wa.me/6282269245660" target="_blank" class="text-body text-decoration-none mt-1 d-flex align-items-center gap-2">
                <i class="fab fa-whatsapp text-success fs-6"></i>
                <span>{{ __('auth.contact_whatsapp') }}</span>
            </a>
            <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-body text-decoration-none mt-1 d-flex align-items-center gap-2">
                <i class="fab fa-telegram text-primary fs-6"></i>
                <span>{{ __('auth.contact_telegram') }}</span>
            </a>
        </div>

        {{-- Status Sistem --}}
        <div class="d-flex justify-content-between align-items-center small text-secondary pt-2 border-top border-secondary border-opacity-10">
            <span>{{ __('auth.engine_status_label') }} <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">{{ __('auth.engine_status_online') }}</span></span>
            <span>{{ __('auth.engine_uptime') }}</span>
        </div>
      </div>

      <div class="small text-secondary opacity-75 text-center mt-3" style="font-size: 0.75rem;">
        <span>{{ __('auth.m3_footer_note') }}</span>
      </div>
    </div>
  </div>

  {{-- Telegram Polling Modal Dialog --}}
  <div class="modal fade" id="modal-telegram" tabindex="-1" aria-labelledby="modalTelegramLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 p-3" style="background: var(--surface-container-high); color: var(--on-surface);">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalTelegramLabel">
            <i class="fab fa-telegram text-primary me-2"></i>{{ __('auth.telegram_modal_title') }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center py-4">
          <p class="small text-secondary mb-4">{{ __('auth.telegram_modal_desc') }}</p>

          <div id="telegram-loading" class="my-3">
            <div class="m3-spinner-circle mx-auto mb-3" style="width: 36px; height: 36px;"></div>
            <p class="small text-secondary mb-0">{{ __('auth.telegram_loading') }}</p>
          </div>

          <div id="telegram-ready" class="d-none my-3">
            <a id="telegram-deep-link" href="#" target="_blank" class="m3-auth-tg-btn py-2.5 px-4 d-inline-flex">
              <i class="fas fa-external-link-alt me-2"></i>{{ __('auth.telegram_open_btn') }}
            </a>
            <p class="small text-secondary mt-3 mb-0">{{ __('auth.telegram_waiting') }}</p>
          </div>

          <div id="telegram-error" class="d-none alert alert-danger small py-2 mt-3"></div>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-center">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('auth.telegram_btn_cancel') }}</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Bootstrap 5.3 Bundle JS --}}
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Tab Switcher Masuk / Daftar dengan teks terlokalisasi
    function switchAuthTab(target) {
        const btnLogin = document.getElementById('tab-btn-login');
        const btnReg = document.getElementById('tab-btn-register');
        const paneLogin = document.getElementById('login-pane');
        const paneReg = document.getElementById('register-pane');
        const authTitle = document.getElementById('auth-title');
        const authSubtitle = document.getElementById('auth-subtitle');

        if (target === 'register') {
            btnLogin.classList.remove('active');
            btnLogin.classList.add('text-muted');
            btnReg.classList.add('active');
            btnReg.classList.remove('text-muted');

            paneLogin.classList.remove('show', 'active');
            paneReg.classList.add('show', 'active');

            if (authTitle) authTitle.innerText = "{{ __('auth.register_title') }}";
            if (authSubtitle) authSubtitle.innerText = "{{ __('auth.register_subtitle') }}";
        } else {
            btnReg.classList.remove('active');
            btnReg.classList.add('text-muted');
            btnLogin.classList.add('active');
            btnLogin.classList.remove('text-muted');

            paneReg.classList.remove('show', 'active');
            paneLogin.classList.add('show', 'active');

            if (authTitle) authTitle.innerText = "{{ __('auth.welcome_title') }}";
            if (authSubtitle) authSubtitle.innerText = "{{ __('auth.welcome_subtitle') }}";
        }
    }

    // Toggle Intip Sandi
    function togglePasswordVisibility(fieldId, btn) {
        const field = document.getElementById(fieldId);
        const icon = btn.querySelector('i');
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Theme Toggle Handler
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        function updateThemeIcon(theme) {
            if (!themeIcon) return;
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }

        const currentTheme = html.getAttribute('data-bs-theme') || 'light';
        updateThemeIcon(currentTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                const activeTheme = html.getAttribute('data-bs-theme') || 'light';
                const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';

                html.classList.add('theme-transitioning');
                html.setAttribute('data-bs-theme', nextTheme);
                html.style.colorScheme = nextTheme;
                localStorage.setItem('jualan-theme', nextTheme);
                document.cookie = "jualan-theme=" + nextTheme + ";path=/;max-age=31536000;SameSite=Lax";
                updateThemeIcon(nextTheme);

                setTimeout(() => {
                    html.classList.remove('theme-transitioning');
                }, 300);
            });
        }

        // Auto-switch tab if url hash or validation errors on registration
        @if($errors->has('name') || old('name') || ($initialTab ?? '') === 'register')
            switchAuthTab('register');
        @elseif(request()->routeIs('register') || request()->get('tab') === 'register')
            switchAuthTab('register');
        @endif

        // Telegram Auth Polling (Hardened Lifecycle)
        const btnTg = document.getElementById('btn-telegram-login');
        const modalEl = document.getElementById('modal-telegram');
        let pollTimer = null;
        let pollCount = 0;
        const maxPolls = 60; // 60 polls x 2s = 120s timeout

        if (btnTg && modalEl) {
            const bsModal = new bootstrap.Modal(modalEl);

            btnTg.addEventListener('click', function() {
                bsModal.show();
                const loadEl = document.getElementById('telegram-loading');
                const readyEl = document.getElementById('telegram-ready');
                const errEl = document.getElementById('telegram-error');
                const linkEl = document.getElementById('telegram-deep-link');

                loadEl.classList.remove('d-none');
                readyEl.classList.add('d-none');
                errEl.classList.add('d-none');
                pollCount = 0;
                if (pollTimer) clearInterval(pollTimer);

                fetch('{{ route("telegram.token") }}', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.token && data.bot_url) {
                        linkEl.href = data.bot_url;
                        loadEl.classList.add('d-none');
                        readyEl.classList.remove('d-none');

                        pollTimer = setInterval(function() {
                            pollCount++;
                            if (pollCount > maxPolls) {
                                clearInterval(pollTimer);
                                errEl.innerText = "{{ __('auth.telegram_expired') }}";
                                errEl.classList.remove('d-none');
                                readyEl.classList.add('d-none');
                                return;
                            }

                            fetch('{{ url("/auth/telegram/check") }}/' + data.token, {
                                headers: { 'Accept': 'application/json' }
                            })
                            .then(res => res.json())
                            .then(status => {
                                if (status.status === 'authenticated') {
                                    clearInterval(pollTimer);
                                    window.location.href = status.redirect_url || '{{ route("dashboard") }}';
                                } else if (status.status === 'expired') {
                                    clearInterval(pollTimer);
                                    errEl.innerText = "{{ __('auth.telegram_token_expired') }}";
                                    errEl.classList.remove('d-none');
                                    readyEl.classList.add('d-none');
                                }
                            })
                            .catch(() => {
                                // Ignore transient network hiccup during polling
                            });
                        }, 2000);
                    } else {
                        throw new Error(data.message || 'Token gagal dibuat');
                    }
                })
                .catch(err => {
                    loadEl.classList.add('d-none');
                    errEl.innerText = err.message || "{{ __('auth.telegram_connect_error') }}";
                    errEl.classList.remove('d-none');
                });
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                if (pollTimer) clearInterval(pollTimer);
            });
        }
    });
  </script>
</body>
</html>
