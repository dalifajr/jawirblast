@extends('layouts.app')

@section('content')
<div class="responsive padding" style="max-width: 1200px; margin: 0 auto; width: 100%;">
    <!-- Top Navigation Bar -->
    <nav class="top round margin-bottom surface-container">
        <a href="{{ route('dashboard') }}" class="button transparent">
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
        <a href="{{ route('campaigns.index') }}" class="button primary round">
            <i>campaign</i>
            <span>Kampanye</span>
        </a>
        <a href="{{ route('contacts.groups.index') }}" class="button transparent">
            <i>group_work</i>
            <span>Grup</span>
        </a>
        <div class="max"></div>
        <span class="small text-secondary margin-right">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}" class="no-margin">
            @csrf
            <button type="submit" class="button border error round small">
                <i>logout</i>
                <span>Keluar</span>
            </button>
        </form>
    </nav>

    <!-- Header & Action CTA -->
    <header class="row middle-align margin-bottom">
        <div class="max">
            <h4 class="bold primary-text no-margin">Kampanye Broadcast</h4>
            <p class="text-secondary no-margin small">Kelola dan pantau pengiriman pesan massal WhatsApp berkecepatan tinggi.</p>
        </div>
        <div>
            <a href="{{ route('campaigns.create') }}" class="button primary round">
                <i>add</i>
                <span>Buat Kampanye Baru</span>
            </a>
        </div>
    </header>

    <!-- 4 Tonal Metric Cards -->
    <div class="grid margin-bottom">
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="primary-text" style="font-size: 2rem;">campaign</i>
                    <div class="max">
                        <div class="text-secondary small">Total Kampanye</div>
                        <h5 class="bold no-margin">{{ number_format($stats['total_campaigns']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="secondary-text" style="font-size: 2rem;">send</i>
                    <div class="max">
                        <div class="text-secondary small">Pesan Terkirim</div>
                        <h5 class="bold no-margin">{{ number_format($stats['sent_messages']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="primary-text" style="font-size: 2rem;">done_all</i>
                    <div class="max">
                        <div class="text-secondary small">Pesan Dibaca</div>
                        <h5 class="bold no-margin">{{ number_format($stats['read_messages']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="error-text" style="font-size: 2rem;">error</i>
                    <div class="max">
                        <div class="text-secondary small">Gagal Kirim</div>
                        <h5 class="bold no-margin">{{ number_format($stats['failed_messages']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card round surface border padding margin-bottom">
        <form method="GET" action="{{ route('campaigns.index') }}" class="row middle-align gap">
            <div class="field border round max no-margin">
                <input type="text" name="search" placeholder="Cari nama kampanye..." value="{{ request('search') }}">
                <i>search</i>
            </div>
            <div class="field border round no-margin" style="min-width: 180px;">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draf</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Sedang Berjalan</option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Dijeda</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
                <i>filter_list</i>
            </div>
            <button type="submit" class="button border round">
                <span>Filter</span>
            </button>
            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('campaigns.index') }}" class="button transparent round">
                    <i>clear</i>
                    <span>Reset</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Campaigns Table -->
    <div class="card round surface border padding">
        @if($campaigns->isEmpty())
            <div class="center-align padding margin-vertical">
                <i class="text-secondary" style="font-size: 4rem;">campaign</i>
                <h6 class="bold margin-top text-secondary">Belum ada kampanye ditemukan</h6>
                <p class="text-secondary small">Mulai buat kampanye broadcast pertama Anda untuk menyapa pelanggan secara massal.</p>
                <a href="{{ route('campaigns.create') }}" class="button primary round margin-top">
                    <i>add</i>
                    <span>Buat Kampanye Sekarang</span>
                </a>
            </div>
        @else
            <div class="responsive">
                <table class="border">
                    <thead>
                        <tr>
                            <th>Judul & Tanggal</th>
                            <th>Target</th>
                            <th style="min-width: 180px;">Progres Siaran</th>
                            <th>Status</th>
                            <th class="right-align">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $camp)
                            <tr>
                                <td>
                                    <div class="bold">{{ $camp->title }}</div>
                                    <div class="small text-secondary">Dibuat: {{ $camp->created_at->format('d M Y, H:i') }}</div>
                                </td>
                                <td>
                                    <span class="badge border round">{{ number_format($camp->total_contacts) }} Kontak</span>
                                </td>
                                <td>
                                    <div class="row middle-align no-wrap">
                                        <progress class="max" value="{{ $camp->sent_count + $camp->failed_count }}" max="{{ max(1, $camp->total_contacts) }}"></progress>
                                        <span class="small bold margin-left">{{ $camp->progress_percent }}%</span>
                                    </div>
                                    <div class="small text-secondary">
                                        {{ $camp->sent_count }} terkirim @if($camp->failed_count > 0) · <span class="error-text">{{ $camp->failed_count }} gagal</span> @endif
                                    </div>
                                </td>
                                <td>
                                    @if($camp->status === 'draft')
                                        <span class="chip border small">Draf</span>
                                    @elseif($camp->status === 'processing')
                                        <span class="chip primary white-text small">
                                            <i>sync</i>
                                            <span>Berjalan</span>
                                        </span>
                                    @elseif($camp->status === 'paused')
                                        <span class="chip amber white-text small">
                                            <i>pause</i>
                                            <span>Dijeda</span>
                                        </span>
                                    @elseif($camp->status === 'completed')
                                        <span class="chip green white-text small">
                                            <i>check_circle</i>
                                            <span>Selesai</span>
                                        </span>
                                    @elseif(in_array($camp->status, ['cancelled', 'canceled']))
                                        <span class="chip red white-text small">
                                            <i>cancel</i>
                                            <span>Dibatalkan</span>
                                        </span>
                                    @else
                                        <span class="chip error white-text small">{{ ucfirst($camp->status) }}</span>
                                    @endif
                                </td>
                                <td class="right-align">
                                    <div class="row no-wrap right-align gap">
                                        <a href="{{ route('campaigns.show', $camp->id) }}" class="button transparent circle small" title="Lihat Detail">
                                            <i>visibility</i>
                                        </a>

                                        @if($camp->isDraft())
                                            <form method="POST" action="{{ route('campaigns.start', $camp->id) }}" class="no-margin">
                                                @csrf
                                                <button type="submit" class="button primary circle small" title="Mulai Siaran">
                                                    <i>play_arrow</i>
                                                </button>
                                            </form>
                                        @elseif($camp->isProcessing())
                                            <form method="POST" action="{{ route('campaigns.pause', $camp->id) }}" class="no-margin">
                                                @csrf
                                                <button type="submit" class="button amber white-text circle small" title="Jeda Siaran">
                                                    <i>pause</i>
                                                </button>
                                            </form>
                                        @elseif($camp->isPaused())
                                            <form method="POST" action="{{ route('campaigns.resume', $camp->id) }}" class="no-margin">
                                                @csrf
                                                <button type="submit" class="button primary circle small" title="Lanjutkan Siaran">
                                                    <i>play_arrow</i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="margin-top">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
