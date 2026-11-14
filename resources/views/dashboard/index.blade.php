@extends('layouts.app')

@section('content')
<div class="d-flex flex-column gap-4">
    <!-- Row 1: Greeting Hero Banner (.m3-dash-hero) -->
    <div class="row g-3">
        <div class="col-12">
            <div class="m3-dash-hero">
                <div class="m3-dash-overline">{{ __('dashboard.hero_overline') }}</div>
                <h1 class="m3-dash-headline">
                    {{ __('dashboard.welcome', ['name' => $user->name]) }}
                </h1>
                <p class="m3-dash-subtitle">
                    {{ __('dashboard.subtitle') }}
                </p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('campaigns.create') }}" class="btn-m3-primary">
                        <i class="fas fa-plus"></i>
                        <span>{{ __('dashboard.quick_broadcast') }}</span>
                    </a>
                    <a href="{{ route('contacts.index') }}" class="btn-m3-tonal">
                        <i class="fas fa-address-book"></i>
                        <span>{{ __('dashboard.manage_contacts') }}</span>
                    </a>
                    <a href="{{ route('whatsapp.index') }}" class="btn-m3-outline">
                        <i class="fab fa-whatsapp"></i>
                        <span>{{ __('dashboard.connect_wa') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: 4 Metric Cards (.m3-dash-metric-card) -->
    <div class="row g-3">
        <!-- Card 1: Total Kontak -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('contacts.index') }}" class="m3-dash-metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-semibold">{{ __('dashboard.stats_total_contacts') }}</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--surface-container); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ number_format($totalContacts ?? 0, 0, ',', '.') }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        <!-- Card 2: Kampanye Berjalan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('campaigns.index') }}" class="m3-dash-metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-semibold">{{ __('dashboard.stats_active_campaigns') }}</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--surface-container); color: var(--primary);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ number_format($activeCampaigns ?? 0, 0, ',', '.') }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        <!-- Card 3: Pesan Terkirim -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('campaigns.index') }}" class="m3-dash-metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-semibold">{{ __('dashboard.stats_messages_sent') }}</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--surface-container); color: #28a745;">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ number_format($sentMessagesCount ?? 0, 0, ',', '.') }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        <!-- Card 4: WhatsApp Engine -->
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ route('whatsapp.index') }}" class="m3-dash-metric-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-semibold">{{ __('dashboard.wa_session_title') }}</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--surface-container); color: #25d366;">
                        <i class="fab fa-whatsapp fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val" style="font-size: 1.05rem;">
                        @if ($waSession && $waSession->status === 'connected')
                            <span class="m3-badge m3-badge-success">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> {{ __('dashboard.wa_connected') }}
                            </span>
                        @elseif ($waSession && $waSession->status === 'connecting')
                            <span class="m3-badge m3-badge-warning">
                                <i class="fas fa-circle-notch fa-spin" style="font-size: 0.65rem;"></i> {{ __('dashboard.wa_connecting') }}
                            </span>
                        @else
                            <span class="m3-badge m3-badge-danger">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> {{ __('dashboard.wa_disconnected') }}
                            </span>
                        @endif
                    </div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Row 3: Grid 2 Kolom (Col-lg-8 & Col-lg-4) -->
    <div class="row g-4">
        <!-- Kolom Kiri: Tabel Kampanye Terkini (Col-lg-8) -->
        <div class="col-12 col-lg-8">
            <div class="m3-card">
                <div class="m3-card-header flex-wrap gap-2">
                    <div>
                        <h2 class="m3-section-title">{{ __('dashboard.recent_campaigns') }}</h2>
                        <p class="text-secondary small m-0">{{ __('dashboard.recent_campaigns_desc') }}</p>
                    </div>
                    <a href="{{ route('campaigns.index') }}" class="btn-m3-outline" style="font-size: 0.78rem; padding: 5px 14px;">
                        <span>{{ __('dashboard.view_all_campaigns') }}</span>
                        <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>

                @if ($recentCampaigns->isEmpty())
                    <div class="text-center py-5">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--primary);">
                            <i class="fas fa-bullhorn fs-4"></i>
                        </div>
                        <h5 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('dashboard.no_recent_campaigns') }}</h5>
                        <p class="text-secondary small mb-3">{{ __('dashboard.subtitle') }}</p>
                        <a href="{{ route('campaigns.create') }}" class="btn-m3-primary">
                            <i class="fas fa-plus"></i>
                            <span>{{ __('dashboard.btn_start_first') }}</span>
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="m3-table m3-table-responsive">
                            <thead>
                                <tr>
                                    <th>{{ __('dashboard.col_campaign_name') }}</th>
                                    <th>{{ __('dashboard.col_target') }}</th>
                                    <th>{{ __('dashboard.col_progress') }}</th>
                                    <th>{{ __('dashboard.col_status') }}</th>
                                    <th>{{ __('dashboard.col_date') }}</th>
                                    <th class="text-end">{{ __('dashboard.col_action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentCampaigns as $c)
                                    @php
                                        $total = $c->total_contacts ?? 1;
                                        $sent = $c->sent_count ?? 0;
                                        $pct = $total > 0 ? min(100, round(($sent / $total) * 100)) : 0;
                                    @endphp
                                    <tr>
                                        <td data-label="{{ __('dashboard.col_campaign_name') }}">
                                            <div class="fw-bold" style="color: var(--on-surface);">{{ $c->title ?? $c->name }}</div>
                                            <small class="text-secondary">ID: #{{ $c->id }}</small>
                                        </td>
                                        <td data-label="{{ __('dashboard.col_target') }}">
                                            <span class="fw-semibold">{{ number_format($c->total_contacts ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                        <td data-label="{{ __('dashboard.col_progress') }}" style="min-width: 140px;">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px; border-radius: var(--radius-pill); background: var(--surface-container);">
                                                    <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%; background: var(--primary); border-radius: var(--radius-pill);" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="small fw-bold text-secondary" style="font-size: 0.75rem;">{{ $pct }}%</span>
                                            </div>
                                        </td>
                                        <td data-label="{{ __('dashboard.col_status') }}">
                                            @if ($c->status === 'completed')
                                                <span class="m3-badge m3-badge-success">{{ __('dashboard.status_completed') }}</span>
                                            @elseif ($c->status === 'processing' || $c->status === 'running')
                                                <span class="m3-badge m3-badge-info">
                                                    <i class="fas fa-spinner fa-spin small"></i> {{ __('dashboard.status_processing') }}
                                                </span>
                                            @elseif ($c->status === 'paused')
                                                <span class="m3-badge m3-badge-warning">{{ __('dashboard.status_paused') }}</span>
                                            @elseif ($c->status === 'failed')
                                                <span class="m3-badge m3-badge-danger">{{ __('dashboard.status_failed') }}</span>
                                            @else
                                                <span class="m3-badge m3-badge-secondary">{{ __('dashboard.status_pending') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="{{ __('dashboard.col_date') }}">
                                            <span class="text-secondary small">{{ $c->created_at ? $c->created_at->format('d M Y') : '-' }}</span>
                                        </td>
                                        <td data-label="{{ __('dashboard.col_action') }}" class="text-end">
                                            <a href="{{ route('campaigns.show', $c->id) }}" class="btn-m3-outline" style="font-size: 0.75rem; padding: 4px 10px;" title="{{ __('dashboard.view_detail') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Kolom Kanan: Engine Status & Quick Actions (Col-lg-4) -->
        <div class="col-12 col-lg-4 d-flex flex-column gap-3">
            <!-- Card 1: Baileys WhatsApp Engine Status -->
            <div class="m3-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(37, 211, 102, 0.15); color: #25d366;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h3 class="m3-section-title" style="font-size: 1rem;">{{ __('dashboard.wa_session_title') }}</h3>
                    </div>
                    @if ($waSession && $waSession->status === 'connected')
                        <span class="m3-badge m3-badge-success" style="font-size: 0.7rem;">{{ __('dashboard.wa_connected') }}</span>
                    @else
                        <span class="m3-badge m3-badge-danger" style="font-size: 0.7rem;">{{ __('dashboard.wa_disconnected') }}</span>
                    @endif
                </div>

                <div class="p-3 mb-3" style="background: var(--surface-container); border-radius: var(--radius-md);">
                    <div class="small text-secondary mb-1">{{ __('dashboard.wa_paired_phone', ['phone' => '']) }}</div>
                    <div class="fw-bold" style="color: var(--on-surface);">
                        @if ($waSession && $waSession->user_jid)
                            {{ explode('@', $waSession->user_jid)[0] }}
                        @else
                            <span class="text-secondary fw-normal">{{ __('dashboard.wa_no_phone') }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 small text-secondary mb-3">
                    <i class="fas fa-shield-alt text-primary"></i>
                    <span>{{ __('dashboard.wa_anti_ban_active') }}</span>
                </div>

                <a href="{{ route('whatsapp.index') }}" class="btn-m3-tonal w-100 justify-content-center">
                    <i class="fas fa-qrcode"></i>
                    <span>{{ $waSession && $waSession->status === 'connected' ? __('dashboard.wa_action_manage') : __('dashboard.wa_action_connect') }}</span>
                </a>
            </div>

            <!-- Card 2: Telegram Bot Integration Card -->
            <div class="m3-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(0, 136, 204, 0.15); color: #0088cc;">
                            <i class="fab fa-telegram"></i>
                        </div>
                        <h3 class="m3-section-title" style="font-size: 1rem;">{{ __('dashboard.telegram_card_title') }}</h3>
                    </div>
                    @if ($user->telegram_id)
                        <span class="m3-badge m3-badge-success" style="font-size: 0.7rem;">
                            <i class="fas fa-check small"></i>
                        </span>
                    @endif
                </div>

                @if ($user->telegram_id)
                    <div class="p-3 mb-3" style="background: var(--surface-container); border-radius: var(--radius-md);">
                        <div class="small text-secondary mb-1">{{ __('dashboard.telegram_connected_status') }}</div>
                        <div class="fw-bold" style="color: var(--on-surface);">{{ __('dashboard.telegram_id_label', ['id' => $user->telegram_id]) }}</div>
                    </div>
                @else
                    <div class="p-3 mb-3" style="background: var(--surface-container); border-radius: var(--radius-md);">
                        <div class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('dashboard.telegram_not_linked') }}</div>
                        <p class="text-secondary small m-0">{{ __('dashboard.telegram_not_linked_desc') }}</p>
                    </div>
                @endif
            </div>

            <!-- Card 3: Quick System Actions -->
            <div class="m3-card">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-bolt text-primary"></i>
                    <h3 class="m3-section-title" style="font-size: 1rem;">{{ __('dashboard.quick_actions_title') }}</h3>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('contacts.index') }}" class="btn-m3-outline w-100 justify-content-start">
                        <i class="fas fa-file-import text-primary me-1"></i>
                        <span>{{ __('dashboard.quick_import_contacts') }}</span>
                    </a>
                    <a href="{{ route('contacts.groups.index') }}" class="btn-m3-outline w-100 justify-content-start">
                        <i class="fas fa-layer-group text-primary me-1"></i>
                        <span>{{ __('dashboard.quick_new_group') }}</span>
                    </a>
                    <a href="{{ route('campaigns.create') }}" class="btn-m3-outline w-100 justify-content-start">
                        <i class="fas fa-paper-plane text-primary me-1"></i>
                        <span>{{ __('dashboard.quick_test_message') }}</span>
                    </a>
                    <a href="{{ route('campaigns.index') }}" class="btn-m3-outline w-100 justify-content-start">
                        <i class="fas fa-history text-primary me-1"></i>
                        <span>{{ __('dashboard.quick_logs') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection