@extends('layouts.app')

@section('content')
<div class="responsive padding" style="max-width: 960px; margin: 0 auto; width: 100%;">
    <!-- Header -->
    <header class="margin-bottom">
        <h4 class="bold primary-text" style="margin-bottom: 0.25rem;">{{ __('session.title') }}</h4>
        <p class="text-secondary" style="margin-top: 0;">{{ __('session.subtitle') }}</p>
    </header>

    <div class="row">
        <!-- Kolom Kiri: Kartu Status Sesi -->
        <div class="col s12 m5">
            <article class="card round surface border padding">
                <div class="row items-center" style="margin-bottom: 1rem;">
                    <i class="primary-text" style="font-size: 2rem;">phonelink</i>
                    <div class="max">
                        <h6 class="bold" style="margin: 0;">{{ __('session.status_card') }}</h6>
                    </div>
                </div>

                <div class="margin-bottom">
                    @php
                        $isConn = $session && $session->isConnected();
                        $isConnecting = $session && $session->isConnecting();
                    @endphp

                    <div id="status-badge-container">
                        @if ($isConn)
                            <span id="status-badge" class="badge green white-text round">{{ __('session.status_connected') }}</span>
                        @elseif ($isConnecting)
                            <span id="status-badge" class="badge amber black-text round">{{ __('session.status_connecting') }}</span>
                        @else
                            <span id="status-badge" class="badge grey white-text round">{{ __('session.status_disconnected') }}</span>
                        @endif
                    </div>
                </div>

                <div class="surface-container-low round padding margin-bottom small">
                    <p id="status-phone" style="margin: 0.25rem 0;">
                        <strong>{{ __('session.connected_phone', ['phone' => $session?->phone_number ?? '-']) }}</strong>
                    </p>
                    <p id="status-jid" class="text-secondary" style="margin: 0.25rem 0; word-break: break-all;">
                        {{ __('session.connected_jid', ['jid' => $session?->user_jid ?? '-']) }}
                    </p>
                    <p id="status-time" class="text-secondary" style="margin: 0.25rem 0;">
                        {{ __('session.last_active', ['time' => $session?->last_connected_at?->diffForHumans() ?? '-']) }}
                    </p>
                </div>

                <!-- Tombol Putuskan Sesi -->
                <form action="{{ route('wa.logout') }}" method="POST" id="disconnect-form" style="{{ $isConn ? '' : 'display:none;' }}" onsubmit="return confirm('{{ __('session.disconnect_confirm') }}')">
                    @csrf
                    <button type="submit" class="border error round responsive" id="btn-disconnect">
                        <i>link_off</i>
                        <span>{{ __('session.disconnect_btn') }}</span>
                    </button>
                </form>
            </article>
        </div>

        <!-- Kolom Kanan: Kartu Penautan Perangkat (QR Code / Pairing Code) -->
        <div class="col s12 m7">
            <article class="card round surface border padding" id="pairing-card" style="{{ $isConn ? 'display:none;' : '' }}">
                <h6 class="bold margin-bottom">{{ __('session.link_device_title') }}</h6>

                <!-- Segmented Tabs BeerCSS -->
                <nav class="tab border round margin-bottom" id="pairing-tabs">
                    <a class="active" id="tab-btn-qr" href="javascript:void(0)" onclick="switchTab('qr')">
                        <i>qr_code_scanner</i>
                        <span>{{ __('session.tab_qr_code') }}</span>
                    </a>
                    <a id="tab-btn-phone" href="javascript:void(0)" onclick="switchTab('phone')">
                        <i>smartphone</i>
                        <span>{{ __('session.tab_pairing_code') }}</span>
                    </a>
                </nav>

                <!-- Panel 1: QR Code -->
                <div id="panel-qr">
                    <div class="center-align padding">
                        <div class="surface-container border round padding inline-block" style="display:inline-block; min-width: 250px; min-height: 250px;">
                            <img id="qr-image" src="{{ $qrCode ?? '' }}" alt="WhatsApp QR Code" class="round" style="{{ empty($qrCode) ? 'display:none;' : '' }} max-width: 240px; height: auto;">
                            <div id="qr-loading" class="center-align padding" style="{{ !empty($qrCode) ? 'display:none;' : '' }}">
                                <progress class="circle"></progress>
                                <p class="small text-secondary margin-top">{{ __('session.status_connecting') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Petunjuk Scan QR -->
                    <div class="surface-container-low round padding margin-top small">
                        <ol style="padding-left: 1.25rem; margin: 0;">
                            <li style="margin-bottom: 0.25rem;">{{ __('session.qr_step_1') }}</li>
                            <li style="margin-bottom: 0.25rem;">{{ __('session.qr_step_2') }}</li>
                            <li>{{ __('session.qr_step_3') }}</li>
                        </ol>
                    </div>

                    <div class="center-align margin-top">
                        <button type="button" class="border primary round" id="btn-refresh-qr" onclick="refreshQr()">
                            <i>refresh</i>
                            <span>{{ __('session.qr_refresh_button') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Panel 2: 8-Digit Pairing Code -->
                <div id="panel-phone" style="display:none;">
                    <form id="form-pairing-code" onsubmit="requestPairingCode(event)">
                        <div class="field border round label prefix margin-bottom">
                            <i>phone</i>
                            <input type="text" id="phone-input" name="phone" placeholder="{{ __('session.pairing_phone_placeholder') }}" required pattern="^[0-9+]{8,20}$">
                            <label>{{ __('session.pairing_phone_label') }}</label>
                            <span class="helper">{{ __('session.pairing_phone_help') }}</span>
                        </div>

                        <div class="margin-top">
                            <button type="submit" class="primary round responsive" id="btn-request-pairing">
                                <i>key</i>
                                <span>{{ __('session.pairing_request_btn') }}</span>
                            </button>
                        </div>
                    </form>

                    <!-- Display Box Kode 8-Digit -->
                    <div id="pairing-code-result" class="surface-container border round padding center-align margin-top" style="display:none;">
                        <span class="small bold text-secondary">{{ __('session.pairing_code_display') }}</span>
                        <div id="pairing-code-text" style="font-family: 'Courier New', Courier, monospace; font-size: 2.5rem; font-weight: 800; letter-spacing: 0.35rem; color: var(--primary); margin: 0.5rem 0;"></div>
                        <p class="small text-secondary" style="margin: 0;">{{ __('session.pairing_step_info') }}</p>
                    </div>
                </div>
            </article>

            <!-- Kartu Status Saat Terhubung -->
            <article class="card round surface border padding green-light" id="connected-notice-card" style="{{ $isConn ? '' : 'display:none;' }}">
                <div class="row items-center">
                    <i class="green-text" style="font-size: 2.5rem;">verified</i>
                    <div class="max">
                        <h6 class="bold green-text" style="margin: 0;">{{ __('session.status_connected') }}</h6>
                        <p class="small" style="margin: 0.25rem 0 0 0;">WhatsApp Baileys Microservice siap mengirim pesan broadcast.</p>
                    </div>
                </div>
            </article>
        </div>
    </div>
</div>
@endsection

@push('scripts')
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
                badge.className = 'badge green white-text round';
                badge.textContent = '{{ __("session.status_connected") }}';
                disconnectForm.style.display = 'block';
                pairingCard.style.display = 'none';
                if (connectedCard) connectedCard.style.display = 'block';

                if (data.phone) {
                    phoneP.innerHTML = '<strong>{{ __("session.connected_phone", ["phone" => ":ph"]) }}</strong>'.replace(':ph', data.phone);
                }
                if (data.userJid) {
                    jidP.textContent = '{{ __("session.connected_jid", ["jid" => ":jd"]) }}'.replace(':jd', data.userJid);
                }
            } else if (isConnecting) {
                badge.className = 'badge amber black-text round';
                badge.textContent = '{{ __("session.status_connecting") }}';
            } else {
                badge.className = 'badge grey white-text round';
                badge.textContent = '{{ __("session.status_disconnected") }}';
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
