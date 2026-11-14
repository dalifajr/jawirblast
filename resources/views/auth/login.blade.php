@extends('layouts.app')

@section('content')
<main class="main-container">
    <div class="auth-card">
        <div class="center-align margin-bottom">
            <h4 class="bold no-margin">JAWIRBLAST</h4>
            <p class="text-secondary small">Masuk ke Akun Anda</p>
        </div>

        @if ($errors->any())
            <article class="border error-container margin-bottom red lighten-5 padding round">
                <ul class="no-margin small red-text">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </article>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="margin-bottom">
            @csrf

            <div class="field label border round margin-bottom">
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                <label for="email">Alamat Email</label>
            </div>

            <div class="field label border round margin-bottom">
                <input type="password" name="password" id="password" required autocomplete="current-password">
                <label for="password">Kata Sandi</label>
            </div>

            <div class="row middle-align margin-bottom">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Ingat Saya</span>
                </label>
            </div>

            <button type="submit" class="button fill round responsive margin-bottom">
                <i>login</i>
                <span>Masuk Sekarang</span>
            </button>
        </form>

        <div class="divider margin-bottom"></div>

        <!-- Telegram Dual-Mode Auth Button -->
        <div class="center-align margin-bottom">
            <button type="button" class="button border round responsive blue-text" id="btn-telegram-login">
                <i>send</i>
                <span>Masuk dengan Telegram</span>
            </button>
        </div>

        <!-- Telegram Polling Modal Dialog -->
        <dialog id="modal-telegram" class="round">
            <div class="padding center-align">
                <h5>Otentikasi Telegram</h5>
                <p class="small text-secondary">Buka Telegram dan klik tombol <b>START</b> untuk login instan.</p>

                <div id="telegram-loading" class="margin-vertical">
                    <progress class="circle"></progress>
                    <p class="small text-secondary">Menghubungkan ke bot...</p>
                </div>

                <div id="telegram-ready" class="hide margin-vertical">
                    <a id="telegram-deep-link" href="#" target="_blank" class="button fill blue round responsive">
                        <i>open_in_new</i>
                        <span>Buka Bot Telegram</span>
                    </a>
                    <p class="small text-secondary margin-top">Menunggu konfirmasi di aplikasi Telegram Anda...</p>
                </div>

                <div class="margin-top">
                    <button type="button" class="button transparent" onclick="document.getElementById('modal-telegram').close()">Tutup</button>
                </div>
            </div>
        </dialog>

        <div class="center-align small margin-top">
            <span class="text-secondary">Belum memiliki akun?</span>
            <a href="{{ route('register') }}" class="bold">Daftar Akun Baru</a>
        </div>

        <div class="center-align small margin-top">
            <a href="{{ route('lang.switch', 'id') }}" class="chip small {{ app()->getLocale() === 'id' ? 'fill' : '' }}">ID</a>
            <a href="{{ route('lang.switch', 'en') }}" class="chip small {{ app()->getLocale() === 'en' ? 'fill' : '' }}">EN</a>
            <a href="{{ route('lang.switch', 'zh') }}" class="chip small {{ app()->getLocale() === 'zh' ? 'fill' : '' }}">ZH</a>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnTg = document.getElementById('btn-telegram-login');
    const modalTg = document.getElementById('modal-telegram');
    const loadingTg = document.getElementById('telegram-loading');
    const readyTg = document.getElementById('telegram-ready');
    const deepLinkEl = document.getElementById('telegram-deep-link');
    let pollInterval = null;

    if (btnTg && modalTg) {
        btnTg.addEventListener('click', async function () {
            modalTg.showModal();
            loadingTg.classList.remove('hide');
            readyTg.classList.add('hide');

            try {
                const res = await fetch("{{ route('telegram.token') }}");
                const data = await res.json();

                if (data.success && data.deep_link) {
                    loadingTg.classList.add('hide');
                    readyTg.classList.remove('hide');
                    deepLinkEl.href = data.deep_link;

                    // Start AJAX polling
                    if (pollInterval) clearInterval(pollInterval);
                    pollInterval = setInterval(async function () {
                        try {
                            const checkRes = await fetch("{{ url('/auth/telegram/check') }}/" + data.token);
                            const checkData = await checkRes.json();

                            if (checkData.authenticated && checkData.redirect_url) {
                                clearInterval(pollInterval);
                                window.location.href = checkData.redirect_url;
                            }
                        } catch (err) {
                            console.error("Polling error:", err);
                        }
                    }, 2000);
                }
            } catch (e) {
                console.error("Failed to generate telegram token:", e);
                loadingTg.innerHTML = '<p class="red-text small">Gagal memuat bot Telegram. Pastikan konfigurasi bot aktif.</p>';
            }
        });

        modalTg.addEventListener('close', function () {
            if (pollInterval) clearInterval(pollInterval);
        });
    }
});
</script>
@endpush
