@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1040px; margin: 0 auto;">
    <!-- Page Header (Identical to M3 Expressive Header) -->
    <div class="mb-4">
        <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('session.title') }}</h4>
        <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">{{ __('session.subtitle') }}</p>
    </div>

    @php
        $isConn = $session && $session->isConnected();
        $isConnecting = $session && $session->isConnecting();
    @endphp

    <div class="row g-4">
        <!-- Kolom Kiri: Kartu Status Sesi -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm p-4 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                        <i class="fas fa-satellite-dish fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0" style="color: var(--on-surface);">{{ __('session.status_card') }}</h6>
                        <span style="font-size: 0.75rem; color: var(--on-surface-variant);">Baileys Native Protocol</span>
                    </div>
                </div>

                <div class="mb-3">
                    <div id="status-badge-container">
                        @if ($isConn)
                            <span id="status-badge" class="badge rounded-pill px-3 py-2 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
                                <i class="fas fa-circle-check me-1.5"></i>{{ __('session.status_connected') }}
                            </span>
                        @elseif ($isConnecting)
                            <span id="status-badge" class="badge rounded-pill px-3 py-2 fw-semibold" style="background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                                <i class="fas fa-spinner fa-spin me-1.5"></i>{{ __('session.status_connecting') }}
                            </span>
                        @else
                            <span id="status-badge" class="badge rounded-pill px-3 py-2 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface-variant, #49454f);">
                                <i class="fas fa-circle-xmark me-1.5"></i>{{ __('session.status_disconnected') }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Info Box Metadata Sesi -->
                <div class="p-3 mb-4 rounded-4" style="background: var(--surface-container-low, #f3edf7); font-size: 0.8125rem;">
                    <p id="status-phone" class="mb-1" style="color: var(--on-surface);">
                        <strong style="color: var(--primary);">{{ __('session.connected_phone', ['phone' => $session?->phone_number ?? '-']) }}</strong>
                    </p>
                    <p id="status-jid" class="mb-1 text-break" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                        {{ __('session.connected_jid', ['jid' => $session?->user_jid ?? '-']) }}
                    </p>
                    <p id="status-time" class="mb-0" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                        {{ __('session.last_active', ['time' => $session?->last_connected_at?->diffForHumans() ?? '-']) }}
                    </p>
                </div>

                <!-- Tombol Putuskan Sesi -->
                <form action="{{ route('wa.logout') }}" method="POST" id="disconnect-form" class="mt-auto" style="{{ $isConn ? '' : 'display:none;' }}" onsubmit="return confirm('{{ __('session.disconnect_confirm') }}')">
                    @csrf
                    <button type="submit" class="btn w-100 rounded-pill py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" id="btn-disconnect" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">
                        <i class="fas fa-phone-slash"></i>
                        <span>{{ __('session.disconnect_btn') }}</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Kartu Penautan Perangkat (QR Code / Pairing Code) -->
        <div class="col-12 col-lg-7">
            <!-- Kartu Penautan Device -->
            <div class="card border-0 shadow-sm p-4" id="pairing-card" style="{{ $isConn ? 'display:none;' : '' }}; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                <h6 class="fw-bold mb-3" style="color: var(--on-surface);">{{ __('session.link_device_title') }}</h6>

                <!-- Segmented Tabs M3 Pill -->
                <div class="d-inline-flex p-1 rounded-pill mb-4" id="pairing-tabs" style="background: var(--surface-container, #ece6f0); border: 1px solid var(--outline-variant, #cac4d0); width: fit-content; max-width: 100%;">
                    <button type="button" class="btn rounded-pill px-3.5 py-1.5 fw-semibold active" id="tab-btn-qr" onclick="switchTab('qr')" style="font-size: 0.8125rem; transition: all 0.2s;">
                        <i class="fas fa-qrcode me-1.5"></i>
                        <span>{{ __('session.tab_qr_code') }}</span>
                    </button>
                    <button type="button" class="btn rounded-pill px-3.5 py-1.5 fw-semibold" id="tab-btn-phone" onclick="switchTab('phone')" style="font-size: 0.8125rem; transition: all 0.2s;">
                        <i class="fas fa-mobile-screen-button me-1.5"></i>
                        <span>{{ __('session.tab_pairing_code') }}</span>
                    </button>
                </div>

                <!-- Panel 1: QR Code -->
                <div id="panel-qr">
                    <div class="text-center p-3">
                        <div class="p-3 rounded-4 d-inline-flex flex-column align-items-center justify-content-center" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0); min-width: 250px; min-height: 250px;">
                            <img id="qr-image" src="{{ $qrCode ?? '' }}" alt="WhatsApp QR Code" class="rounded-3 shadow-sm" style="{{ empty($qrCode) ? 'display:none;' : '' }}; max-width: 230px; height: auto;">
                            <div id="qr-loading" class="text-center py-4" style="{{ !empty($qrCode) ? 'display:none;' : '' }};">
                                <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="small mb-0" style="color: var(--on-surface-variant);">{{ __('session.status_connecting') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Petunjuk Scan QR -->
                    <div class="p-3 mt-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); font-size: 0.8125rem; color: var(--on-surface-variant);">
                        <ol class="mb-0 ps-3">
                            <li class="mb-1">{{ __('session.qr_step_1') }}</li>
                            <li class="mb-1">{{ __('session.qr_step_2') }}</li>
                            <li>{{ __('session.qr_step_3') }}</li>
                        </ol>
                    </div>

                    <div class="text-center mt-3">
                        <button type="button" class="btn rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2" id="btn-refresh-qr" onclick="refreshQr()" style="background: var(--primary, #65558f); color: #fff;">
                            <i class="fas fa-rotate"></i>
                            <span>{{ __('session.qr_refresh_button') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Panel 2: 8-Digit Pairing Code -->
                <div id="panel-phone" style="display:none;">
                    <form id="form-pairing-code" onsubmit="requestPairingCode(event)">
                        <div class="mb-3">
                            <label for="phone-input" class="form-label fw-semibold" style="font-size: 0.8125rem; color: var(--on-surface);">{{ __('session.pairing_phone_label') }}</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-phone"></i></span>
                                <input type="text" id="phone-input" name="phone" class="form-control rounded-end-pill py-2.5" placeholder="{{ __('session.pairing_phone_placeholder') }}" required pattern="^[0-9+]{8,20}$" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                            <div class="form-text mt-1.5" style="font-size: 0.75rem; color: var(--on-surface-variant);">{{ __('session.pairing_phone_help') }}</div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn w-100 rounded-pill py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" id="btn-request-pairing" style="background: var(--primary, #65558f); color: #fff;">
                                <i class="fas fa-key"></i>
                                <span>{{ __('session.pairing_request_btn') }}</span>
                            </button>
                        </div>
                    </form>

                    <!-- Display Box Kode 8-Digit -->
                    <div id="pairing-code-result" class="p-4 mt-3 text-center rounded-4" style="display:none; background: var(--surface-container-low, #f3edf7); border: 1px dashed var(--primary, #65558f);">
                        <span class="small fw-bold text-uppercase tracking-wider" style="color: var(--primary);">{{ __('session.pairing_code_display') }}</span>
                        <div id="pairing-code-text" class="my-2" style="font-family: 'Courier New', Courier, monospace; font-size: 2.25rem; font-weight: 800; letter-spacing: 0.4rem; color: var(--primary);"></div>
                        <p class="small mb-0" style="color: var(--on-surface-variant);">{{ __('session.pairing_step_info') }}</p>
                    </div>
                </div>
            </div>

            <!-- Kartu Status Saat Terhubung -->
            <div class="card border-0 shadow-sm p-4" id="connected-notice-card" style="{{ $isConn ? '' : 'display:none;' }}; background: #f0fdf4; border: 1px solid #bbf7d0 !important; border-radius: 28px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: #dcfce7; color: #15803d; flex-shrink: 0;">
                        <i class="fas fa-check fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1" style="color: #14532d;">{{ __('session.status_connected') }}</h6>
                        <p class="small mb-0" style="color: #166534;">{{ __('session.ready_notice') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    #pairing-tabs button.active {
        background: var(--primary, #65558f) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(101, 85, 143, 0.3) !important;
    }
    #pairing-tabs button:not(.active) {
        background: transparent !important;
        color: var(--on-surface-variant, #49454f) !important;
    }
    #pairing-tabs button:not(.active):hover {
        background: var(--surface-container-high, #e6e0e9) !important;
        color: var(--on-surface, #1d1b20) !important;
    }
</style>

<script>
    function switchTab(tab) {
        const qrBtn = document.getElementById('tab-btn-qr');
        const phoneBtn = document.getElementById('tab-btn-phone');
        const panelQr = document.getElementById('panel-qr');
        const panelPhone = document.getElementById('panel-phone');

        if (tab === 'qr') {
            qrBtn.classList.add('active');
            phoneBtn.classList.remove('active');
            panelQr.style.display = 'block';
            panelPhone.style.display = 'none';
        } else {
            phoneBtn.classList.add('active');
            qrBtn.classList.remove('active');
            panelPhone.style.display = 'block';
            panelQr.style.display = 'none';
        }
    }

    async function refreshQr() {
        const qrImg = document.getElementById('qr-image');
        const qrLoading = document.getElementById('qr-loading');
        qrImg.style.display = 'none';
        qrLoading.style.display = 'block';

        try {
            const res = await fetch('{{ route("wa.qr") }}', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success && data.qr) {
                qrImg.src = data.qr;
                qrImg.style.display = 'inline-block';
                qrLoading.style.display = 'none';
            } else {
                qrLoading.querySelector('p').textContent = data.message || 'QR belum siap, mencoba kembali...';
            }
        } catch (e) {
            console.error('Error refreshing QR:', e);
        }
    }

    async function requestPairingCode(e) {
        e.preventDefault();
        const phone = document.getElementById('phone-input').value;
        const btn = document.getElementById('btn-request-pairing');
        const resultBox = document.getElementById('pairing-code-result');
        const codeText = document.getElementById('pairing-code-text');

        btn.disabled = true;
        btn.querySelector('span').textContent = 'Meminta kode...';

        try {
            const res = await fetch('{{ route("wa.pairing_code") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ phone })
            });
            const data = await res.json();

            if (res.ok && data.success && data.code) {
                codeText.textContent = data.code;
                resultBox.style.display = 'block';
            } else {
                alert(data.message || 'Gagal meminta kode pairing.');
            }
        } catch (err) {
            alert('Kesalahan jaringan: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = '{{ __("session.pairing_request_btn") }}';
        }
    }

    // Polling Status Koneksi secara Realtime (Setiap 3 Detik)
    setInterval(async () => {
        try {
            const res = await fetch('{{ route("wa.status") }}', {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();

            const isConnected = data.status === 'CONNECTED' || data.db_status === 'connected';
            const isConnecting = data.status === 'CONNECTING' || data.db_status === 'connecting';

            const badge = document.getElementById('status-badge');
            const disconnectForm = document.getElementById('disconnect-form');
            const pairingCard = document.getElementById('pairing-card');
            const connectedCard = document.getElementById('connected-notice-card');
            const phoneP = document.getElementById('status-phone');
            const jidP = document.getElementById('status-jid');

            if (isConnected) {
                badge.className = 'badge rounded-pill px-3 py-2 fw-semibold';
                badge.style.background = '#e8f5e9';
                badge.style.color = '#1b5e20';
                badge.style.border = '1px solid #c8e6c9';
                badge.innerHTML = '<i class="fas fa-circle-check me-1.5"></i>{{ __("session.status_connected") }}';
                disconnectForm.style.display = 'block';
                pairingCard.style.display = 'none';
                if (connectedCard) connectedCard.style.display = 'block';

                if (data.phone) {
                    phoneP.innerHTML = '<strong style="color: var(--primary);">{{ __("session.connected_phone", ["phone" => ":ph"]) }}</strong>'.replace(':ph', data.phone);
                }
                if (data.userJid) {
                    jidP.textContent = '{{ __("session.connected_jid", ["jid" => ":jd"]) }}'.replace(':jd', data.userJid);
                }
            } else if (isConnecting) {
                badge.className = 'badge rounded-pill px-3 py-2 fw-semibold';
                badge.style.background = '#fff8e1';
                badge.style.color = '#f57f17';
                badge.style.border = '1px solid #ffecb3';
                badge.innerHTML = '<i class="fas fa-spinner fa-spin me-1.5"></i>{{ __("session.status_connecting") }}';
            } else {
                badge.className = 'badge rounded-pill px-3 py-2 fw-semibold';
                badge.style.background = 'var(--surface-container-high, #e6e0e9)';
                badge.style.color = 'var(--on-surface-variant, #49454f)';
                badge.style.border = 'none';
                badge.innerHTML = '<i class="fas fa-circle-xmark me-1.5"></i>{{ __("session.status_disconnected") }}';
                disconnectForm.style.display = 'none';
                pairingCard.style.display = 'block';
                if (connectedCard) connectedCard.style.display = 'none';
            }
        } catch (e) {
            // Abaikan kesalahan koneksi sementara
        }
    }, 3000);
</script>
@endpush
