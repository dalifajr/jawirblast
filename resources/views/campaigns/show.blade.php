@extends('layouts.app')

@section('content')
<div class="responsive padding" style="max-width: 1200px; margin: 0 auto; width: 100%;">
    <!-- Header & Action Toolbar -->
    <header class="row middle-align margin-bottom">
        <a href="{{ route('campaigns.index') }}" class="button transparent circle margin-right">
            <i>arrow_back</i>
        </a>
        <div class="max">
            <div class="row middle-align gap">
                <h4 class="bold primary-text no-margin">{{ $campaign->title }}</h4>
                <span id="campaignStatusChip">
                    @if($campaign->status === 'draft')
                        <span class="chip border small">Draf</span>
                    @elseif($campaign->status === 'processing')
                        <span class="chip primary white-text small">
                            <i>sync</i>
                            <span>Sedang Berjalan</span>
                        </span>
                    @elseif($campaign->status === 'paused')
                        <span class="chip amber white-text small">
                            <i>pause</i>
                            <span>Dijeda</span>
                        </span>
                    @elseif($campaign->status === 'completed')
                        <span class="chip green white-text small">
                            <i>check_circle</i>
                            <span>Selesai</span>
                        </span>
                    @elseif(in_array($campaign->status, ['cancelled', 'canceled']))
                        <span class="chip red white-text small">
                            <i>cancel</i>
                            <span>Dibatalkan</span>
                        </span>
                    @else
                        <span class="chip error white-text small">{{ ucfirst($campaign->status) }}</span>
                    @endif
                </span>
            </div>
            <p class="text-secondary no-margin small">
                Dibuat: {{ $campaign->created_at->format('d M Y, H:i') }} · 
                Jeda Pacing: {{ $campaign->min_delay_seconds }}-{{ $campaign->max_delay_seconds }} detik
            </p>
        </div>

        <!-- Dynamic Control Action Buttons -->
        <div class="row no-wrap gap">
            @if($campaign->isDraft())
                <form method="POST" action="{{ route('campaigns.start', $campaign->id) }}" class="no-margin">
                    @csrf
                    <button type="submit" class="button primary round">
                        <i>play_arrow</i>
                        <span>Mulai Siaran</span>
                    </button>
                </form>
            @elseif($campaign->isProcessing())
                <form method="POST" action="{{ route('campaigns.pause', $campaign->id) }}" class="no-margin">
                    @csrf
                    <button type="submit" class="button amber white-text round">
                        <i>pause</i>
                        <span>Jeda Siaran</span>
                    </button>
                </form>
            @elseif($campaign->isPaused())
                <form method="POST" action="{{ route('campaigns.resume', $campaign->id) }}" class="no-margin">
                    @csrf
                    <button type="submit" class="button primary round">
                        <i>play_arrow</i>
                        <span>Lanjutkan</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('campaigns.cancel', $campaign->id) }}" class="no-margin" onsubmit="return confirm('Yakin ingin membatalkan kampanye ini?')">
                    @csrf
                    <button type="submit" class="button border error round">
                        <i>cancel</i>
                        <span>Batalkan</span>
                    </button>
                </form>
            @endif
        </div>
    </header>

    <!-- Progress Card Tonal M3 Expressive -->
    <div class="card round surface border padding margin-bottom">
        <div class="row middle-align margin-bottom">
            <div class="max">
                <span class="bold">Progres Pengiriman Siaran</span>
                <span class="text-secondary small"> ({{ $campaign->sent_count + $campaign->failed_count }} dari {{ $campaign->total_contacts }} pesan diproses)</span>
            </div>
            <h4 id="progressPercentLabel" class="bold primary-text no-margin">{{ $campaign->progress_percent }}%</h4>
        </div>
        <progress id="campaignProgressBar" class="max" value="{{ $campaign->sent_count + $campaign->failed_count }}" max="{{ max(1, $campaign->total_contacts) }}"></progress>
    </div>

    <!-- 4 Metrics Counter Cards -->
    <div class="grid margin-bottom">
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="primary-text" style="font-size: 2rem;">contacts</i>
                    <div class="max">
                        <div class="text-secondary small">Total Kontak</div>
                        <h5 id="metricTotalContacts" class="bold no-margin">{{ number_format($campaign->total_contacts) }}</h5>
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
                        <h5 id="metricSentCount" class="bold no-margin">{{ number_format($campaign->sent_count) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="primary-text" style="font-size: 2rem;">done_all</i>
                    <div class="max">
                        <div class="text-secondary small">Dibaca (Centang Biru)</div>
                        <h5 id="metricReadCount" class="bold no-margin">{{ number_format($campaign->read_count) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="s12 m6 l3">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="error-text" style="font-size: 2rem;">error</i>
                    <div class="max">
                        <div class="text-secondary small">Gagal / Dibatalkan</div>
                        <h5 id="metricFailedCount" class="bold no-margin">{{ number_format($campaign->failed_count) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages Delivery Log Table -->
    <div class="card round surface border padding">
        <div class="row middle-align margin-bottom">
            <h6 class="bold primary-text no-margin max">Daftar Log Pengiriman Pesan</h6>
            
            <!-- Status Filter for Messages -->
            <form method="GET" action="{{ route('campaigns.show', $campaign->id) }}" class="no-margin">
                <div class="field border round small no-margin">
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Menunggu (Pending)</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Terkirim</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Diterima (Delivered)</option>
                        <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Dibaca (Read)</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Gagal</option>
                    </select>
                </div>
            </form>
        </div>

        @if($messages->isEmpty())
            <div class="center-align padding margin-vertical text-secondary">
                <i style="font-size: 3rem;">inbox</i>
                <p class="small margin-top">Tidak ada pesan untuk filter status ini.</p>
            </div>
        @else
            <div class="responsive">
                <table class="border">
                    <thead>
                        <tr>
                            <th>Penerima</th>
                            <th>Isi Pesan (Spintax Resolved)</th>
                            <th>Waktu Kirim</th>
                            <th>Status WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $msg)
                            <tr>
                                <td>
                                    <div class="bold">{{ $msg->recipient_name ?: 'Pelanggan' }}</div>
                                    <div class="small text-secondary">{{ $msg->phone_number }}</div>
                                </td>
                                <td style="max-width: 450px;">
                                    <div class="small" style="white-space: pre-wrap; word-break: break-word;">{{ $msg->message_text }}</div>
                                    @if($msg->error_message)
                                        <div class="error-text small margin-top">
                                            <i>error</i>
                                            <span>{{ $msg->error_message }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="small text-secondary">
                                        {{ $msg->sent_at ? $msg->sent_at->format('H:i:s, d M') : '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($msg->status === 'read')
                                        <div class="row middle-align no-wrap primary-text" title="Pesan telah dibaca penerima">
                                            <i>done_all</i>
                                            <span class="small bold margin-left">Dibaca</span>
                                        </div>
                                    @elseif($msg->status === 'delivered')
                                        <div class="row middle-align no-wrap text-secondary" title="Pesan telah sampai di perangkat">
                                            <i>done_all</i>
                                            <span class="small margin-left">Diterima</span>
                                        </div>
                                    @elseif($msg->status === 'sent')
                                        <div class="row middle-align no-wrap text-secondary" title="Pesan terkirim dari server">
                                            <i>done</i>
                                            <span class="small margin-left">Terkirim</span>
                                        </div>
                                    @elseif($msg->status === 'failed')
                                        <div class="row middle-align no-wrap error-text" title="Pengiriman gagal">
                                            <i>error</i>
                                            <span class="small bold margin-left">Gagal</span>
                                        </div>
                                    @else
                                        <div class="row middle-align no-wrap text-secondary" title="Dalam antrean tunggu">
                                            <i>schedule</i>
                                            <span class="small margin-left">Antrean</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="margin-top">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if($campaign->isProcessing())
<script>
    // Polling status real-time saat kampanye sedang processing
    let pollInterval = setInterval(function() {
        fetch("{{ route('campaigns.status', $campaign->id) }}", {
            headers: {
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data) {
                // Update Progress bar dan label
                const processed = (data.sent_count || 0) + (data.failed_count || 0);
                document.getElementById('campaignProgressBar').value = processed;
                document.getElementById('progressPercentLabel').innerText = (data.progress_percent || 0) + '%';

                // Update Counters
                document.getElementById('metricSentCount').innerText = (data.sent_count || 0).toLocaleString();
                document.getElementById('metricReadCount').innerText = (data.read_count || 0).toLocaleString();
                document.getElementById('metricFailedCount').innerText = (data.failed_count || 0).toLocaleString();

                // Jika kampanye telah selesai atau dijeda/dibatalkan, reload halaman untuk update toolbar aksi
                if (data.is_completed || data.is_paused || data.is_cancelled || data.status !== 'processing') {
                    clearInterval(pollInterval);
                    window.location.reload();
                }
            }
        })
        .catch(err => console.error('Error polling campaign status:', err));
    }, 4000);
</script>
@endif
@endpush
