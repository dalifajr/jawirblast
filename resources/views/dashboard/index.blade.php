@extends('layouts.app')

@section('content')
<div class="padding">
    <nav class="top round margin-bottom surface-container">
        <a href="{{ route('dashboard') }}" class="button primary round">
            <i>dashboard</i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('whatsapp.index') }}" class="button transparent">
            <i>chat</i>
            <span>WhatsApp</span>
        </a>
        <a href="{{ route('contacts.index') }}" class="button transparent">
            <i>contacts</i>
            <span>Kontak</span>
        </a>
        <a href="{{ route('contacts.groups.index') }}" class="button transparent">
            <i>group_work</i>
            <span>Grup</span>
        </a>
        <div class="max"></div>
        <div class="row middle-align">
            <span class="small margin-right">{{ $user->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="no-margin">
                @csrf
                <button type="submit" class="button border error small round">
                    <i>logout</i>
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </nav>

    <div class="grid">
        <div class="s12 m8 l8">
            <div class="card round padding margin-bottom">
                <h5 class="bold">Selamat Datang, {{ $user->name }}!</h5>
                <p class="text-secondary">Anda telah berhasil masuk ke sistem pengiriman pesan broadcast JAWIRBLAST.</p>

                <div class="divider margin-vertical"></div>

                <div class="row">
                    <div class="s12 m6">
                        <p class="small text-secondary no-margin">Alamat Email</p>
                        <p class="bold">{{ $user->email }}</p>
                    </div>
                    <div class="s12 m6">
                        <p class="small text-secondary no-margin">Status Telegram</p>
                        <p class="bold">
                            @if ($user->telegram_id)
                                <span class="badge green white-text">Terhubung (ID: {{ $user->telegram_id }})</span>
                            @else
                                <span class="badge grey white-text">Belum Terhubung</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="s12 m4 l4">
            <div class="card round padding">
                <h6 class="bold">Ringkasan Sistem</h6>
                <p class="small text-secondary">Milestone 2 Fondasi Autentikasi & Integrasi Telegram Bot aktif.</p>
                <div class="margin-top">
                    <span class="chip small fill green white-text">Auth Active</span>
                    <span class="chip small fill blue white-text">Telegram Dual-Mode</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
