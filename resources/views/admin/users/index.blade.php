@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1400px; margin: 0 auto;">
    <!-- Header Title & Action CTA -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('users.title') }}</h4>
            <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">{{ __('users.subtitle') }}</p>
        </div>
        <div>
            <button type="button" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-user-plus"></i>
                <span>{{ __('users.create_user_btn') }}</span>
            </button>
        </div>
    </div>

    <!-- 4 M3 Concentric Metric Cards (2x2 Grid on Mobile, 4x1 on Desktop) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                        <i class="fas fa-users fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('users.total_users') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['total_users']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: #e8f5e9; color: #1b5e20; flex-shrink: 0;">
                        <i class="fas fa-user-check fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('users.active_users') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['active_users']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: #fde8e8; color: #b91c1c; flex-shrink: 0;">
                        <i class="fas fa-user-slash fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('users.suspended_users') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['suspended_users']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: var(--secondary-container, #e8def8); color: var(--secondary, #625b71); flex-shrink: 0;">
                        <i class="fas fa-wallet fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('users.total_circulating_balance') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.05rem;">Rp {{ number_format($stats['total_wallet_balance'], 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Filter Toolbar & User Table M3 Card (Edge-to-Edge Desktop Table / Mobile Responsive Cards) -->
    <div class="card border-0 shadow-sm mb-4 card-table-m3" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
        <!-- Integrated Toolbar: Search & Filter Controls -->
        <div class="p-3 border-bottom" style="border-color: var(--outline-variant, #cac4d0) !important;">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md">
                    <div class="input-group">
                        <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control rounded-end-pill py-2" placeholder="{{ __('users.search_placeholder') }}" value="{{ request('search') }}" style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                </div>
                <div class="col-6 col-md-auto" style="min-width: 150px;">
                    <select name="role" class="form-select rounded-pill py-2" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0); font-size: 0.85rem;">
                        <option value="all" {{ request('role', 'all') === 'all' ? 'selected' : '' }}>{{ __('users.all_roles') }}</option>
                        <option value="superadmin" {{ request('role') === 'superadmin' ? 'selected' : '' }}>{{ __('users.role_superadmin') }}</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>{{ __('users.role_admin') }}</option>
                        <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>{{ __('users.role_user') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-auto" style="min-width: 150px;">
                    <select name="status" class="form-select rounded-pill py-2" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0); font-size: 0.85rem;">
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>{{ __('users.all_status') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('users.status_active') }}</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('users.status_suspended') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn rounded-pill px-3.5 py-2 fw-semibold flex-grow-1 flex-md-grow-0" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);">
                        <i class="fas fa-filter me-1.5"></i>{{ __('users.filter_btn') }}
                    </button>
                    @if(request()->hasAny(['search', 'role', 'status']))
                        <a href="{{ route('admin.users.index') }}" class="btn rounded-pill px-3 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);">
                            <i class="fas fa-times me-1"></i>{{ __('users.reset_btn') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive" style="border-radius: 0 0 20px 20px;">
            <table class="table table-hover align-middle mb-0 table-responsive-cards w-100" style="margin: 0 !important; width: 100% !important;">
                <thead style="background: var(--surface-container-low, #f3edf7); border-bottom: 1px solid var(--outline-variant, #cac4d0);">
                    <tr>
                        <th class="ps-4 py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em; width: 60px;">{{ __('users.th_user_id') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_user_info') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_role') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_joined_date') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_time_joined') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_wallet_balance') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_referral_balance') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_total_earnings') }}</th>
                        <th class="py-3 text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_status') }}</th>
                        <th class="pe-4 py-3 text-end text-secondary small text-uppercase fw-bold" style="letter-spacing: 0.05em;">{{ __('users.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr style="cursor: pointer; transition: background 0.15s ease;" onclick="openUserDetail({{ $u->id }})" title="Klik untuk membuka detail lengkap pengguna">
                            <!-- ID -->
                            <td class="ps-4 py-3 text-secondary small fw-semibold" data-label="{{ __('users.th_user_id') }}">
                                #{{ $u->id }}
                            </td>

                            <!-- User Info (Avatar + Name + Username + Email) -->
                            <td class="py-3" data-label="{{ __('users.th_user_info') }}">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="m3-avatar-circle" style="width: 40px; height: 40px; min-width: 40px; border-radius: 50%; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem;">
                                        @if($u->avatar)
                                            <img src="{{ asset('storage/' . $u->avatar) }}" alt="{{ $u->name }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                        @else
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-truncate" style="color: var(--on-surface); font-size: 0.92rem;">
                                            {{ $u->name }}
                                            @if(auth()->id() === $u->id)
                                                <span class="badge rounded-pill bg-primary ms-1" style="font-size: 0.65rem;">{{ __('users.you_badge') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-secondary small text-truncate">
                                            @if($u->username)
                                                <span class="text-primary fw-medium">&#64;{{ $u->username }}</span> &bull; 
                                            @endif
                                            <span>{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="py-3" data-label="{{ __('users.th_role') }}">
                                @if($u->role === 'superadmin')
                                    <span class="badge rounded-pill px-3 py-1.5 fw-semibold" style="background: #eaddff; color: #21005d; border: 1px solid #d0bcff;">
                                        <i class="fas fa-crown me-1 text-warning"></i>{{ __('users.role_superadmin') }}
                                    </span>
                                @elseif($u->role === 'admin')
                                    <span class="badge rounded-pill px-3 py-1.5 fw-semibold" style="background: #e8def8; color: #1d192b; border: 1px solid #cac4d0;">
                                        <i class="fas fa-shield-alt me-1 text-primary"></i>{{ __('users.role_admin') }}
                                    </span>
                                @else
                                    <span class="badge rounded-pill px-3 py-1.5 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface-variant, #49454f);">
                                        <i class="fas fa-user me-1"></i>{{ __('users.role_user') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Tanggal Join -->
                            <td class="py-3 small" style="color: var(--on-surface-variant);" data-label="{{ __('users.th_joined_date') }}">
                                <i class="far fa-calendar-alt me-1 opacity-75"></i>
                                {{ $u->created_at ? $u->created_at->format('d M Y') : '-' }}
                            </td>

                            <!-- Lama Join -->
                            <td class="py-3 small" style="color: var(--on-surface-variant);" data-label="{{ __('users.th_time_joined') }}">
                                <i class="far fa-clock me-1 opacity-75"></i>
                                {{ $u->time_joined }}
                            </td>

                            <!-- Saldo Dompet -->
                            <td class="py-3 fw-bold" style="color: var(--primary, #65558f); font-size: 0.92rem;" data-label="{{ __('users.th_wallet_balance') }}">
                                Rp {{ number_format($u->wallet_balance ?? 0, 0, ',', '.') }}
                            </td>

                            <!-- Saldo Referral -->
                            <td class="py-3 small fw-semibold" style="color: var(--on-surface);" data-label="{{ __('users.th_referral_balance') }}">
                                Rp {{ number_format($u->referral_balance ?? 0, 0, ',', '.') }}
                            </td>

                            <!-- Total Pendapatan -->
                            <td class="py-3 small fw-semibold" style="color: #1b5e20;" data-label="{{ __('users.th_total_earnings') }}">
                                Rp {{ number_format($u->total_earnings ?? 0, 0, ',', '.') }}
                            </td>

                            <!-- Status -->
                            <td class="py-3" data-label="{{ __('users.th_status') }}">
                                @if($u->is_suspended)
                                    <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fde8e8; color: #b91c1c; border: 1px solid #f8b4b4;">
                                        <i class="fas fa-ban me-1"></i>{{ __('users.status_suspended') }}
                                    </span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
                                        <i class="fas fa-check-circle me-1"></i>{{ __('users.status_active') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Actions Dropdown -->
                            <td class="pe-4 py-3 text-end" data-label="{{ __('users.th_actions') }}" onclick="event.stopPropagation()">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-icon rounded-circle border-0" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" style="width: 34px; height: 34px; background: var(--surface-container-high, #e6e0e9); color: var(--on-surface);">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-radius: 16px; min-width: 200px; border-color: var(--outline-variant, #cac4d0) !important;">
                                        <!-- Impersonate -->
                                        @if(auth()->id() !== $u->id)
                                        <li>
                                            <form action="{{ route('admin.users.impersonate', $u->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2">
                                                    <i class="fas fa-user-secret text-warning"></i>
                                                    <span>{{ __('users.action_impersonate') }}</span>
                                                </button>
                                            </form>
                                        </li>
                                        @endif

                                        <!-- Detail -->
                                        <li>
                                            <button type="button" class="dropdown-item py-2 d-flex align-items-center gap-2" onclick="openUserDetail({{ $u->id }})">
                                                <i class="fas fa-id-card text-info"></i>
                                                <span>{{ __('users.action_detail') }}</span>
                                            </button>
                                        </li>

                                        <!-- Edit -->
                                        <li>
                                            <button type="button" class="dropdown-item py-2 d-flex align-items-center gap-2" onclick="openEditUser({{ $u->id }})">
                                                <i class="fas fa-edit text-primary"></i>
                                                <span>{{ __('users.action_edit') }}</span>
                                            </button>
                                        </li>

                                        <li><hr class="dropdown-divider my-1"></li>

                                        <!-- Suspend / Unsuspend -->
                                        @if(auth()->id() !== $u->id)
                                            @if($u->is_suspended)
                                            <li>
                                                <form action="{{ route('admin.users.unsuspend', $u->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-2 text-success d-flex align-items-center gap-2">
                                                        <i class="fas fa-unlock"></i>
                                                        <span>{{ __('users.action_unsuspend') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @else
                                            <li>
                                                <button type="button" class="dropdown-item py-2 text-warning d-flex align-items-center gap-2" onclick="openSuspendModal({{ $u->id }}, '{{ addslashes($u->name) }}')">
                                                    <i class="fas fa-user-lock"></i>
                                                    <span>{{ __('users.action_suspend') }}</span>
                                                </button>
                                            </li>
                                            @endif

                                            <!-- Delete -->
                                            <li>
                                                <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" class="m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun {{ addslashes($u->name) }}? Aksi ini permanen.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item py-2 text-danger d-flex align-items-center gap-2">
                                                        <i class="fas fa-trash-alt"></i>
                                                        <span>{{ __('users.action_delete') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="py-4">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-container-high, #e6e0e9); color: var(--on-surface-variant, #49454f);">
                                        <i class="fas fa-users-slash fs-4"></i>
                                    </div>
                                    <p class="text-secondary mb-0 fw-medium">{{ __('users.no_users_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-3 border-top d-flex justify-content-center" style="border-color: var(--outline-variant, #cac4d0) !important;">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
<!-- ============================================================== -->
<!-- MODAL 1: CREATE USER (TAMBAH PENGGUNA BARU) -->
<!-- ============================================================== -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold" id="createUserModalLabel" style="color: var(--on-surface);">{{ __('users.modal_create_title') }}</h5>
                    <p class="text-secondary small mb-0">{{ __('users.modal_create_desc') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-pill px-3 py-2" required placeholder="Contoh: Budi Santoso" style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_username') }}</label>
                            <input type="text" name="username" class="form-control rounded-pill px-3 py-2" placeholder="budisantoso" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_role') }} <span class="text-danger">*</span></label>
                            <select name="role" class="form-select rounded-pill px-3 py-2" required style="border-color: var(--outline-variant, #cac4d0);">
                                <option value="user" selected>{{ __('users.role_user') }}</option>
                                <option value="admin">{{ __('users.role_admin') }}</option>
                                <option value="superadmin">{{ __('users.role_superadmin') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_email') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control rounded-pill px-3 py-2" required placeholder="budi@example.com" style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_password') }} <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control rounded-pill px-3 py-2" required placeholder="Minimal 8 karakter" minlength="8" style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_wallet_balance') }}</label>
                            <input type="number" name="wallet_balance" class="form-control rounded-pill px-3 py-2" value="0" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_referral_balance') }}</label>
                            <input type="number" name="referral_balance" class="form-control rounded-pill px-3 py-2" value="0" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn rounded-pill px-4 py-2" data-bs-dismiss="modal" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface);">{{ __('users.btn_cancel') }}</button>
                    <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold" style="background: var(--primary, #65558f); color: #fff;">{{ __('users.btn_save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 2: EDIT USER (EDIT PENGGUNA) -->
<!-- ============================================================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="editUserModalLabel" style="color: var(--on-surface);">{{ __('users.modal_edit_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_name') }} <span class="text-danger">*</span></label>
                        <input type="text" id="edit_name" name="name" class="form-control rounded-pill px-3 py-2" required style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_username') }}</label>
                            <input type="text" id="edit_username" name="username" class="form-control rounded-pill px-3 py-2" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_role') }} <span class="text-danger">*</span></label>
                            <select id="edit_role" name="role" class="form-select rounded-pill px-3 py-2" required style="border-color: var(--outline-variant, #cac4d0);">
                                <option value="user">{{ __('users.role_user') }}</option>
                                <option value="admin">{{ __('users.role_admin') }}</option>
                                <option value="superadmin">{{ __('users.role_superadmin') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_email') }} <span class="text-danger">*</span></label>
                        <input type="email" id="edit_email" name="email" class="form-control rounded-pill px-3 py-2" required style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_password') }}</label>
                        <input type="password" name="password" class="form-control rounded-pill px-3 py-2" placeholder="{{ __('users.form_password_edit_hint') }}" minlength="8" style="border-color: var(--outline-variant, #cac4d0);">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_wallet_balance') }}</label>
                            <input type="number" id="edit_wallet_balance" name="wallet_balance" class="form-control rounded-pill px-3 py-2" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_referral_balance') }}</label>
                            <input type="number" id="edit_referral_balance" name="referral_balance" class="form-control rounded-pill px-3 py-2" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn rounded-pill px-4 py-2" data-bs-dismiss="modal" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface);">{{ __('users.btn_cancel') }}</button>
                    <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold" style="background: var(--primary, #65558f); color: #fff;">{{ __('users.btn_update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 3: DETAIL USER LENGKAP & DIAGRAM PERFORMA PESAN -->
<!-- ============================================================== -->
<div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
            <!-- Modal Header: User Profile Brief & Actions -->
            <div class="modal-header border-bottom px-4 pt-4 pb-3" style="border-color: var(--outline-variant, #cac4d0) !important; background: var(--surface-container-low, #f3edf7); border-radius: 28px 28px 0 0;">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 w-100">
                    <div class="d-flex align-items-center gap-3">
                        <div id="detailAvatarBox" class="m3-avatar-circle" style="width: 54px; height: 54px; min-width: 54px; border-radius: 50%; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.4rem;">
                            U
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="fw-bold mb-0" id="detailName" style="color: var(--on-surface);">Nama Pengguna</h5>
                                <span id="detailRoleBadge" class="badge rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">User</span>
                                <span id="detailStatusBadge" class="badge rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">Aktif</span>
                            </div>
                            <div class="text-secondary small mt-0.5">
                                <span id="detailUsername" class="text-primary fw-medium">&#64;username</span> &bull; 
                                <span id="detailEmail">email@example.com</span> &bull; 
                                <span><i class="far fa-calendar-alt me-1"></i><span id="detailJoinedDate">01 Jan 2026</span> (<span id="detailTimeJoined">1 hari lalu</span>)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons matching Table Actions -->
                    <div class="d-flex align-items-center gap-2" id="detailModalActions">
                        <form id="detailImpersonateForm" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="background: #fef08a; color: #854d0e; border: 1px solid #fde047;">
                                <i class="fas fa-user-secret"></i>
                                <span>{{ __('users.action_impersonate') }}</span>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface); border: 1px solid var(--outline-variant, #cac4d0);" onclick="editFromDetail()">
                            <i class="fas fa-edit me-1"></i>{{ __('users.action_edit') }}
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>

            <!-- Modal Nav Tabs M3 -->
            <div class="px-4 pt-3 border-bottom" style="border-color: var(--outline-variant, #cac4d0) !important; background: var(--surface-container-low, #f3edf7);">
                <ul class="nav nav-tabs border-0 gap-2" id="userDetailTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-4 py-2 fw-semibold" id="tab-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-overview-pane" type="button" role="tab" style="font-size: 0.875rem;">
                            <i class="fas fa-chart-line me-1.5"></i>{{ __('users.tab_overview') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-4 py-2 fw-semibold" id="tab-withdrawals-btn" data-bs-toggle="tab" data-bs-target="#tab-withdrawals-pane" type="button" role="tab" style="font-size: 0.875rem;">
                            <i class="fas fa-money-bill-wave me-1.5"></i>{{ __('users.tab_withdrawals') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-4 py-2 fw-semibold" id="tab-campaigns-btn" data-bs-toggle="tab" data-bs-target="#tab-campaigns-pane" type="button" role="tab" style="font-size: 0.875rem;">
                            <i class="fab fa-whatsapp me-1.5"></i>{{ __('users.tab_campaigns') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-4 py-2 fw-semibold" id="tab-settings-btn" data-bs-toggle="tab" data-bs-target="#tab-settings-pane" type="button" role="tab" style="font-size: 0.875rem;">
                            <i class="fas fa-sliders-h me-1.5"></i>{{ __('users.tab_settings') }}
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Modal Tab Content -->
            <div class="modal-body p-4">
                <div class="tab-content" id="userDetailTabsContent">
                    <!-- ============================================== -->
                    <!-- TAB 1: RINGKASAN & DIAGRAM PESAN TERKIRIM VS GAGAL -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade show active" id="tab-overview-pane" role="tabpanel">
                        <!-- 4 Mini Financial Metric Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                    <small class="text-secondary d-block mb-1">{{ __('users.card_current_balance') }}</small>
                                    <h5 class="fw-bold mb-0 text-primary" id="detailWalletBalance">Rp 0</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                    <small class="text-secondary d-block mb-1">{{ __('users.card_referral_balance') }}</small>
                                    <h5 class="fw-bold mb-0" id="detailReferralBalance" style="color: var(--on-surface);">Rp 0</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                    <small class="text-secondary d-block mb-1">{{ __('users.card_total_earnings') }}</small>
                                    <h5 class="fw-bold mb-0 text-success" id="detailTotalEarnings">Rp 0</h5>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                                    <small class="text-secondary d-block mb-1">{{ __('users.card_highest_earnings') }}</small>
                                    <h5 class="fw-bold mb-0 text-info" id="detailHighestEarnings">Rp 0</h5>
                                </div>
                            </div>
                        </div>

                        <!-- DIAGRAM PERFORMA PESAN BROADCAST: TERKIRIM VS GAGAL DALAM SATU DIAGRAM -->
                        <div class="p-3 rounded-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0);">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f);">
                                        <i class="fas fa-chart-bar small"></i>
                                    </div>
                                    <span class="fw-bold" style="color: var(--on-surface);">{{ __('users.chart_broadcast_title') }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3 small">
                                    <span class="d-inline-flex align-items-center gap-1.5"><span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; display: inline-block;"></span> {{ __('users.chart_legend_sent') }}</span>
                                    <span class="d-inline-flex align-items-center gap-1.5"><span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444; display: inline-block;"></span> {{ __('users.chart_legend_failed') }}</span>
                                </div>
                            </div>
                            <div style="height: 260px; position: relative;">
                                <canvas id="broadcastPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 2: RIWAYAT PENARIKAN (WITHDRAWALS) -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="tab-withdrawals-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="detailWithdrawalsTable">
                                <thead style="background: var(--surface-container-low, #f3edf7);">
                                    <tr>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_withdrawal_id') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_withdrawal_date') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_withdrawal_amount') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_withdrawal_method') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_withdrawal_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="detailWithdrawalsTbody">
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-secondary small">{{ __('users.no_withdrawals') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 3: KAMPANYE WHATSAPP -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="tab-campaigns-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="detailCampaignsTable">
                                <thead style="background: var(--surface-container-low, #f3edf7);">
                                    <tr>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_campaign_title') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_campaign_status') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_campaign_sent') }}</th>
                                        <th class="py-2.5 small fw-bold text-secondary">{{ __('users.th_campaign_failed') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="detailCampaignsTbody">
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-secondary small">{{ __('users.no_campaigns') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- TAB 4: PENGATURAN AKSES & SALDO -->
                    <!-- ============================================== -->
                    <div class="tab-pane fade" id="tab-settings-pane" role="tabpanel">
                        <form id="detailQuickSettingsForm" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_wallet_balance') }}</label>
                                    <input type="number" id="quick_wallet_balance" name="wallet_balance" class="form-control rounded-pill px-3 py-2" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_referral_balance') }}</label>
                                    <input type="number" id="quick_referral_balance" name="referral_balance" class="form-control rounded-pill px-3 py-2" min="0" style="border-color: var(--outline-variant, #cac4d0);">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">{{ __('users.form_role') }}</label>
                                    <select id="quick_role" name="role" class="form-select rounded-pill px-3 py-2" style="border-color: var(--outline-variant, #cac4d0);">
                                        <option value="user">{{ __('users.role_user') }}</option>
                                        <option value="admin">{{ __('users.role_admin') }}</option>
                                        <option value="superadmin">{{ __('users.role_superadmin') }}</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6 d-flex align-items-end">
                                    <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold w-100" style="background: var(--primary, #65558f); color: #fff;">
                                        <i class="fas fa-save me-1.5"></i>{{ __('users.btn_update') }}
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4" style="border-color: var(--outline-variant, #cac4d0);">

                        <!-- Suspend Action Box -->
                        <div class="p-3 rounded-4" style="background: #fff8f8; border: 1px solid #fed7d7;">
                            <h6 class="fw-bold text-danger mb-1"><i class="fas fa-shield-alt me-1.5"></i>Zona Keamanan Akun</h6>
                            <p class="text-secondary small mb-3">Batasi atau aktifkan kembali akses akun pengguna ke platform siaran.</p>
                            <div id="quickSuspendActionBox">
                                <!-- Dynamic form inserted via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL 4: SUSPEND REASON PROMPT MODAL -->
<!-- ============================================================== -->
<div class="modal fade" id="suspendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-ban me-1.5"></i>{{ __('users.action_suspend') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="suspendUserForm" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">
                    <p class="small text-secondary mb-3">Anda akan membatasi akses akun pengguna: <strong id="suspendUserName">-</strong>.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" style="color: var(--on-surface-variant);">Alasan Penangguhan / Pembatasan <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control rounded-4 p-3" rows="3" required placeholder="Contoh: Terdeteksi aktivitas spam atau pelanggaran ketentuan siaran." style="border-color: var(--outline-variant, #cac4d0);"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn rounded-pill px-4 py-2" data-bs-dismiss="modal" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface);">{{ __('users.btn_cancel') }}</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 py-2 fw-semibold">{{ __('users.action_suspend') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('styles')
<style>
    /* Table Card M3 Edge-to-Edge & Dropdown Overflow Fix */
    .card-table-m3 {
        overflow: visible !important;
        padding: 0 !important;
    }
    .card-table-m3 .table-responsive {
        overflow-x: auto;
        overflow-y: visible !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .card-table-m3 .table-responsive-cards {
        width: 100% !important;
        margin: 0 !important;
    }
    .card-table-m3 thead th:first-child {
        border-top-left-radius: 20px;
    }
    .card-table-m3 thead th:last-child {
        border-top-right-radius: 20px;
    }
    .card-table-m3 tbody tr:last-child td:first-child {
        border-bottom-left-radius: 20px;
    }
    .card-table-m3 tbody tr:last-child td:last-child {
        border-bottom-right-radius: 20px;
    }
    .card-table-m3 .dropdown-menu {
        z-index: 1035 !important;
    }

    /* Styling nav-tabs M3 Expressive */
    #userDetailTabs .nav-link {
        color: var(--on-surface-variant, #49454f);
        border: none;
        background: transparent;
        transition: all 0.2s ease;
    }
    #userDetailTabs .nav-link:hover {
        background: var(--surface-container-high, #e6e0e9);
        color: var(--on-surface, #1d1b20);
    }
    #userDetailTabs .nav-link.active {
        background: var(--secondary-container, #e8def8) !important;
        color: var(--on-secondary-container, #1d192b) !important;
    }
</style>
@endpush

@push('scripts')
<!-- Chart.js 4.4.2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let currentLoadedUser = null;
    let performanceChart = null;

    // Inisialisasi atau re-render Chart.js
    function renderPerformanceChart(labels, sentData, failedData) {
        const ctx = document.getElementById('broadcastPerformanceChart');
        if (!ctx) return;

        if (performanceChart) {
            performanceChart.destroy();
        }

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
        const textColor = isDark ? '#cac4d0' : '#49454f';

        performanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels && labels.length ? labels : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [
                    {
                        label: '{{ __("users.chart_legend_sent") }}',
                        data: sentData && sentData.length ? sentData : [0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.6
                    },
                    {
                        label: '{{ __("users.chart_legend_failed") }}',
                        data: failedData && failedData.length ? failedData : [0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10,
                        cornerRadius: 12
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: textColor,
                            font: { family: 'Poppins', size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor,
                            font: { family: 'Poppins', size: 11 },
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Buka Popup Modal Detail User via AJAX JSON API
    window.openUserDetail = function(userId) {
        if (window.showPageLoader) window.showPageLoader();

        fetch(`{{ url('admin/users') }}/${userId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(res => {
            if (window.hidePageLoader) window.hidePageLoader();
            if (!res.success) {
                alert('Gagal memuat data pengguna.');
                return;
            }

            const u = res.data;
            currentLoadedUser = u;

            // Isi Header Brief
            document.getElementById('detailName').textContent = u.name;
            document.getElementById('detailUsername').textContent = u.username ? '@' + u.username : '@user';
            document.getElementById('detailEmail').textContent = u.email;
            document.getElementById('detailJoinedDate').textContent = u.joined_date || '-';
            document.getElementById('detailTimeJoined').textContent = u.time_joined || '-';

            // Avatar
            const avatarBox = document.getElementById('detailAvatarBox');
            if (u.avatar) {
                avatarBox.innerHTML = `<img src="{{ asset('storage') }}/${u.avatar}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
            } else {
                avatarBox.textContent = (u.name || 'U').charAt(0).toUpperCase();
            }

            // Role Badge
            const roleBadge = document.getElementById('detailRoleBadge');
            if (u.role === 'superadmin') {
                roleBadge.className = 'badge rounded-pill px-2.5 py-1 bg-primary-subtle text-primary border border-primary';
                roleBadge.textContent = 'Superadmin';
            } else if (u.role === 'admin') {
                roleBadge.className = 'badge rounded-pill px-2.5 py-1 bg-secondary-subtle text-secondary border';
                roleBadge.textContent = 'Admin';
            } else {
                roleBadge.className = 'badge rounded-pill px-2.5 py-1 bg-light text-dark border';
                roleBadge.textContent = 'User';
            }

            // Status Badge
            const statusBadge = document.getElementById('detailStatusBadge');
            if (u.is_suspended) {
                statusBadge.className = 'badge rounded-pill px-2.5 py-1 bg-danger-subtle text-danger border border-danger';
                statusBadge.textContent = 'Dibatasi';
            } else {
                statusBadge.className = 'badge rounded-pill px-2.5 py-1 bg-success-subtle text-success border border-success';
                statusBadge.textContent = 'Aktif';
            }

            // Impersonate Form Action
            const impForm = document.getElementById('detailImpersonateForm');
            impForm.action = `{{ url('admin/users') }}/${u.id}/impersonate`;
            if ({{ auth()->id() }} === u.id) {
                impForm.style.display = 'none';
            } else {
                impForm.style.display = 'inline-block';
            }

            // TAB 1: Overview Values
            document.getElementById('detailWalletBalance').textContent = u.wallet_balance_formatted || 'Rp 0';
            document.getElementById('detailReferralBalance').textContent = u.referral_balance_formatted || 'Rp 0';
            document.getElementById('detailTotalEarnings').textContent = u.total_earnings_formatted || 'Rp 0';
            document.getElementById('detailHighestEarnings').textContent = u.max_daily_earnings_formatted || 'Rp 0';

            // Render Chart Performa
            setTimeout(() => {
                const chartData = res.chart || {};
                renderPerformanceChart(chartData.labels, chartData.sent, chartData.failed);
            }, 250);

            // TAB 2: Withdrawals Table
            const wTbody = document.getElementById('detailWithdrawalsTbody');
            const withdrawals = res.withdrawals || [];
            if (withdrawals.length > 0) {
                wTbody.innerHTML = withdrawals.map(w => `
                    <tr>
                        <td class="small fw-semibold text-secondary">#${w.id}</td>
                        <td class="small">${w.date}</td>
                        <td class="small fw-bold text-success">${w.amount}</td>
                        <td class="small">${w.payment_method} <span class="text-secondary">(${w.account_info})</span></td>
                        <td>
                            <span class="badge rounded-pill px-2 py-0.5 ${w.status === 'completed' ? 'bg-success' : (w.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark')}">${w.status}</span>
                        </td>
                    </tr>
                `).join('');
            } else {
                wTbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-secondary small">{{ __('users.no_withdrawals') }}</td></tr>`;
            }

            // TAB 3: Campaigns Table
            const cTbody = document.getElementById('detailCampaignsTbody');
            const campaigns = res.campaigns || [];
            if (campaigns.length > 0) {
                cTbody.innerHTML = campaigns.map(c => `
                    <tr>
                        <td class="small fw-semibold text-truncate" style="max-width: 200px;">${c.title}</td>
                        <td><span class="badge rounded-pill px-2 py-0.5 bg-secondary">${c.status}</span></td>
                        <td class="small text-success fw-bold">${c.sent_count}</td>
                        <td class="small text-danger fw-bold">${c.failed_count}</td>
                    </tr>
                `).join('');
            } else {
                cTbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-secondary small">{{ __('users.no_campaigns') }}</td></tr>`;
            }

            // TAB 4: Quick Settings
            document.getElementById('quick_wallet_balance').value = u.wallet_balance || 0;
            document.getElementById('quick_referral_balance').value = u.referral_balance || 0;
            document.getElementById('quick_role').value = u.role || 'user';
            document.getElementById('detailQuickSettingsForm').action = `{{ url('admin/users') }}/${u.id}`;

            const suspendBox = document.getElementById('quickSuspendActionBox');
            if ({{ auth()->id() }} === u.id) {
                suspendBox.innerHTML = `<span class="badge bg-secondary">Akun Anda sendiri tidak dapat dibatasi.</span>`;
            } else if (u.is_suspended) {
                suspendBox.innerHTML = `
                    <p class="small text-danger mb-2">Akun ini sedang dibatasi dengan alasan: <em>"${u.suspended_reason || 'Tidak ada alasan'}"</em></p>
                    <form action="{{ url('admin/users') }}/${u.id}/unsuspend" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1.5 fw-semibold">
                            <i class="fas fa-unlock me-1"></i>{{ __('users.action_unsuspend') }}
                        </button>
                    </form>
                `;
            } else {
                suspendBox.innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold" onclick="bootstrap.Modal.getInstance(document.getElementById('userDetailModal')).hide(); openSuspendModal(${u.id}, '${u.name}');">
                        <i class="fas fa-ban me-1"></i>{{ __('users.action_suspend') }}
                    </button>
                `;
            }

            // Show Modal
            const modalEl = document.getElementById('userDetailModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        })
        .catch(err => {
            if (window.hidePageLoader) window.hidePageLoader();
            console.error(err);
            alert('Terjadi kesalahan saat memuat detail pengguna.');
        });
    };

    // Trigger tab resize chart
    document.addEventListener('DOMContentLoaded', function() {
        const overviewTabBtn = document.getElementById('tab-overview-btn');
        if (overviewTabBtn) {
            overviewTabBtn.addEventListener('shown.bs.tab', function () {
                if (performanceChart) {
                    performanceChart.resize();
                }
            });
        }
    });

    // Buka Modal Edit Pengguna
    window.openEditUser = function(userId) {
        if (window.showPageLoader) window.showPageLoader();

        fetch(`{{ url('admin/users') }}/${userId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(res => {
            if (window.hidePageLoader) window.hidePageLoader();
            if (!res.success) return;

            const u = res.data;
            document.getElementById('edit_name').value = u.name;
            document.getElementById('edit_username').value = u.username || '';
            document.getElementById('edit_email').value = u.email;
            document.getElementById('edit_role').value = u.role || 'user';
            document.getElementById('edit_wallet_balance').value = u.wallet_balance || 0;
            document.getElementById('edit_referral_balance').value = u.referral_balance || 0;

            document.getElementById('editUserForm').action = `{{ url('admin/users') }}/${u.id}`;

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editUserModal'));
            modal.show();
        })
        .catch(() => {
            if (window.hidePageLoader) window.hidePageLoader();
            alert('Gagal mengambil data untuk diedit.');
        });
    };

    window.editFromDetail = function() {
        if (!currentLoadedUser) return;
        const detailModal = bootstrap.Modal.getInstance(document.getElementById('userDetailModal'));
        if (detailModal) detailModal.hide();
        openEditUser(currentLoadedUser.id);
    };

    // Modal Suspend Prompt
    window.openSuspendModal = function(userId, userName) {
        document.getElementById('suspendUserName').textContent = userName;
        document.getElementById('suspendUserForm').action = `{{ url('admin/users') }}/${userId}/suspend`;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal'));
        modal.show();
    };
</script>
@endpush
