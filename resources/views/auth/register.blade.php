@extends('layouts.app')

@section('content')
<main class="main-container">
    <div class="auth-card">
        <div class="center-align margin-bottom">
            <h4 class="bold no-margin">JAWIRBLAST</h4>
            <p class="text-secondary small">Daftar Akun Pengguna Baru</p>
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

        <form method="POST" action="{{ route('register.post') }}">
            @csrf

            <div class="field label border round margin-bottom">
                <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                <label for="name">Nama Lengkap</label>
            </div>

            <div class="field label border round margin-bottom">
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="username">
                <label for="email">Alamat Email</label>
            </div>

            <div class="field label border round margin-bottom">
                <input type="password" name="password" id="password" required autocomplete="new-password">
                <label for="password">Kata Sandi (Min. 8 karakter)</label>
            </div>

            <div class="field label border round margin-bottom">
                <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">
                <label for="password_confirmation">Konfirmasi Kata Sandi</label>
            </div>

            <button type="submit" class="button fill round responsive margin-bottom">
                <i>person_add</i>
                <span>Daftar Akun Baru</span>
            </button>
        </form>

        <div class="divider margin-bottom"></div>

        <div class="center-align small">
            <span class="text-secondary">Sudah memiliki akun?</span>
            <a href="{{ route('login') }}" class="bold">Masuk di sini</a>
        </div>

        <div class="center-align small margin-top">
            <a href="{{ route('lang.switch', 'id') }}" class="chip small {{ app()->getLocale() === 'id' ? 'fill' : '' }}">ID</a>
            <a href="{{ route('lang.switch', 'en') }}" class="chip small {{ app()->getLocale() === 'en' ? 'fill' : '' }}">EN</a>
            <a href="{{ route('lang.switch', 'zh') }}" class="chip small {{ app()->getLocale() === 'zh' ? 'fill' : '' }}">ZH</a>
        </div>
    </div>
</main>
@endsection
