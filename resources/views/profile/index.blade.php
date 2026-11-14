@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1200px; margin: 0 auto;">
    <!-- Alert Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 d-flex align-items-center shadow-sm" role="alert" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
            <i class="fas fa-check-circle me-2 fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4 shadow-sm" role="alert" style="background: #fde8e8; color: #b91c1c; border: 1px solid #f8b4b4;">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <strong>Perhatian:</strong>
            </div>
            <ul class="mb-0 ps-3 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Profile Header Card (M3 Expressive Lavender Squircle 28px) -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 gap-md-4">
                    <div class="m3-avatar-circle" style="width: 72px; height: 72px; min-width: 72px; border-radius: 50%; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.8rem; flex-shrink: 0;">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h4 class="fw-bold mb-0" style="color: var(--on-surface);">{{ $user->name }}</h4>
                            @if($user->role === 'superadmin')
                                <span class="badge rounded-pill px-3 py-1 fw-semibold" style="background: #eaddff; color: #21005d; border: 1px solid #d0bcff; font-size: 0.72rem;">
                                    <i class="fas fa-crown me-1 text-warning"></i>{{ __('profile.badge_superadmin') }}
                                </span>
                            @elseif($user->role === 'admin')
                                <span class="badge rounded-pill px-3 py-1 fw-semibold" style="background: #e8def8; color: #1d192b; border: 1px solid #cac4d0; font-size: 0.72rem;">
                                    <i class="fas fa-shield-alt me-1 text-primary"></i>{{ __('profile.badge_admin') }}
                                </span>
                            @else
                                <span class="badge rounded-pill px-3 py-1 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface-variant, #49454f); font-size: 0.72rem;">
                                    <i class="fas fa-user me-1"></i>{{ __('profile.badge_user') }}
                                </span>
                            @endif
                        </div>
                        <div class="text-secondary small mb-2">
                            @if($user->username)
                                <span class="text-primary fw-medium">&#64;{{ $user->username }}</span> &bull; 
                            @endif
                            <span>{{ $user->email }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                    <i class="fas fa-sign-out-alt me-1"></i>{{ __('profile.logout_btn') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Telegram Integration Quick Card -->
            <div class="col-lg-4">
                <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold d-flex align-items-center gap-1.5" style="color: var(--on-surface-variant);">
                            <i class="fab fa-telegram text-primary"></i> {{ __('profile.telegram_connection') }}
                        </span>
                        @if($user->telegram_id)
                            <span class="badge rounded-pill bg-success-subtle text-success px-2 py-0.5" style="font-size: 0.72rem;">
                                <i class="fas fa-check me-1"></i>{{ __('profile.telegram_connected') }}
                            </span>
                        @else
                            <span class="badge rounded-pill bg-warning-subtle text-warning px-2 py-0.5" style="font-size: 0.72rem;">
                                <i class="fas fa-exclamation me-1"></i>{{ __('profile.telegram_not_connected') }}
                            </span>
                        @endif
                    </div>
                    <div class="fw-bold fs-6" style="color: var(--on-surface);">
                        {{ $user->telegram_id ? 'ID: ' . $user->telegram_id : __('profile.not_connected_label') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs M3 -->
    <div class="card border-0 shadow-sm mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
        <div class="px-4 pt-3 border-bottom" style="border-color: var(--outline-variant, #cac4d0) !important; background: var(--surface-container-low, #f3edf7); border-radius: 24px 24px 0 0;">
            <ul class="nav nav-tabs border-0 gap-2" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ session('active_tab', 'identity') === 'identity' ? 'active' : '' }} rounded-pill px-4 py-2 fw-semibold" id="tab-identity-btn" data-bs-toggle="tab" data-bs-target="#tab-identity-pane" type="button" role="tab">
                        <i class="fas fa-id-card me-1.5"></i>{{ __('profile.tab_identity') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ session('active_tab') === 'security' ? 'active' : '' }} rounded-pill px-4 py-2 fw-semibold" id="tab-security-btn" data-bs-toggle="tab" data-bs-target="#tab-security-pane" type="button" role="tab">
                        <i class="fas fa-shield-alt me-1.5"></i>{{ __('profile.tab_security') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 py-2 fw-semibold" id="tab-balance-btn" data-bs-toggle="tab" data-bs-target="#tab-balance-pane" type="button" role="tab">
                        <i class="fas fa-wallet me-1.5"></i>{{ __('profile.tab_balance') }}
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="profileTabsContent">
                <!-- TAB 1: DATA DIRI -->
                <div class="tab-pane fade {{ session('active_tab', 'identity') === 'identity' ? 'show active' : '' }}" id="tab-identity-pane" role="tabpanel">
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-pill px-3 py-2" required value="{{ old('name', $user->name) }}" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_username') }}</label>
                                <input type="text" name="username" class="form-control rounded-pill px-3 py-2" value="{{ old('username', $user->username) }}" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control rounded-pill px-3 py-2" required value="{{ old('email', $user->email) }}" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_telegram_id') }}</label>
                                <input type="text" name="telegram_id" class="form-control rounded-pill px-3 py-2" value="{{ old('telegram_id', $user->telegram_id) }}" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_avatar') }}</label>
                                <input type="file" name="avatar" class="form-control rounded-4 px-3 py-2" accept="image/png,image/jpeg,image/webp" style="border-color: var(--outline-variant, #cac4d0);">
                                <small class="text-secondary">{{ __('profile.avatar_hint') }}</small>
                            </div>
                            <div class="col-12 pt-2">
                                <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                                    <i class="fas fa-save me-1.5"></i>{{ __('profile.btn_save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: KEAMANAN & PASSWORD -->
                <div class="tab-pane fade {{ session('active_tab') === 'security' ? 'show active' : '' }}" id="tab-security-pane" role="tabpanel">
                    <form action="{{ route('profile.password') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-3" style="max-width: 600px;">
                            <div class="col-12">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_current_password') }} <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control rounded-pill px-3 py-2" required style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_new_password') }} <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control rounded-pill px-3 py-2" required minlength="8" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('profile.label_confirm_password') }} <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control rounded-pill px-3 py-2" required minlength="8" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="col-12 pt-2">
                                <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                                    <i class="fas fa-key me-1.5"></i>{{ __('profile.btn_update_password') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 3: SALDO & FINANSIAL -->
                <div class="tab-pane fade" id="tab-balance-pane" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                <small class="text-secondary d-block mb-1">{{ __('profile.wallet_balance') }}</small>
                                <h4 class="fw-bold mb-0 text-primary">Rp {{ number_format($user->wallet_balance ?? 0, 0, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                <small class="text-secondary d-block mb-1">{{ __('profile.referral_balance') }}</small>
                                <h4 class="fw-bold mb-0" style="color: var(--on-surface);">Rp {{ number_format($user->referral_balance ?? 0, 0, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                <small class="text-secondary d-block mb-1">{{ __('profile.total_earnings') }}</small>
                                <h4 class="fw-bold mb-0 text-success">Rp {{ number_format($user->total_earnings ?? 0, 0, ',', '.') }}</h4>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                <small class="text-secondary d-block mb-1">{{ __('profile.max_daily_earnings') }}</small>
                                <h4 class="fw-bold mb-0 text-info">Rp {{ number_format($user->max_daily_earnings ?? 0, 0, ',', '.') }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
