@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1200px; margin: 0 auto;">
    <!-- Header & Action Toolbar -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('campaigns.index') }}" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: var(--surface-container, #ece6f0); color: var(--on-surface);" title="{{ __('common.back') }}">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold mb-0" style="color: var(--on-surface);">{{ $campaign->title }}</h4>
                    <span id="campaignStatusChip">
                        @if($campaign->status === 'draft')
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">{{ __('campaign.status_draft') }}</span>
                        @elseif($campaign->status === 'processing')
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--primary-container, #eaddff); color: var(--primary, #65558f);">
                                <i class="fas fa-rotate fa-spin me-1"></i>{{ __('campaign.status_processing') }}
                            </span>
                        @elseif($campaign->status === 'paused')
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                                <i class="fas fa-pause me-1"></i>{{ __('campaign.status_paused') }}
                            </span>
                        @elseif($campaign->status === 'completed')
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
                                <i class="fas fa-check me-1"></i>{{ __('campaign.status_completed') }}
                            </span>
                        @elseif(in_array($campaign->status, ['cancelled', 'canceled']))
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">
                                <i class="fas fa-times me-1"></i>{{ __('campaign.status_cancelled') }}
                            </span>
                        @else
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container-high); color: var(--on-surface);">{{ ucfirst($campaign->status) }}</span>
                        @endif
                    </span>
                </div>
                <p class="mb-0 small" style="color: var(--on-surface-variant); font-size: 0.8125rem;">
                    {{ __('common.created_at') }}: {{ $campaign->created_at->format('d M Y, H:i') }} · 
                    Jeda Pacing: {{ $campaign->min_delay_seconds }}-{{ $campaign->max_delay_seconds }} {{ __('common.seconds') }}
                </p>
            </div>
        </div>

        <!-- Dynamic Control Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            @if($campaign->isDraft())
                <form method="POST" action="{{ route('campaigns.start', $campaign->id) }}" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                        <i class="fas fa-paper-plane"></i>
                        <span>{{ __('campaign.start_broadcast') }}</span>
                    </button>
                </form>
            @elseif($campaign->isProcessing())
                <form method="POST" action="{{ route('campaigns.pause', $campaign->id) }}" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2" style="background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                        <i class="fas fa-pause"></i>
                        <span>{{ __('campaign.pause_broadcast') }}</span>
                    </button>
                </form>
            @elseif($campaign->isPaused())
                <form method="POST" action="{{ route('campaigns.resume', $campaign->id) }}" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                        <i class="fas fa-play"></i>
                        <span>{{ __('campaign.resume_broadcast') }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('campaigns.cancel', $campaign->id) }}" class="d-inline m-0" onsubmit="return confirm('{{ __('common.confirm') }}?')">
                    @csrf
                    <button type="submit" class="btn rounded-pill px-3.5 py-2.5 fw-semibold d-inline-flex align-items-center gap-2" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">
                        <i class="fas fa-times"></i>
                        <span>{{ __('campaign.cancel_broadcast') }}</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Progress Card Tonal M3 Expressive -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <span class="fw-bold" style="color: var(--on-surface);">{{ __('campaign.progress_title') }}</span>
                <span class="small" style="color: var(--on-surface-variant);"> ({{ $campaign->sent_count + $campaign->failed_count }} dari {{ $campaign->total_contacts }} pesan diproses)</span>
            </div>
            <h4 id="progressPercentLabel" class="fw-bold mb-0" style="color: var(--primary, #65558f);">{{ $campaign->progress_percent }}%</h4>
        </div>
        <div class="progress" style="height: 10px; border-radius: 9999px; background: var(--surface-container, #ece6f0);">
            <div id="campaignProgressBar" class="progress-bar rounded-pill" role="progressbar" style="width: {{ $campaign->progress_percent }}%; background: var(--primary, #65558f);" aria-valuenow="{{ $campaign->progress_percent }}" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <!-- 4 Metrics Counter Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                        <i class="fas fa-users fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('campaign.target_recipients') }}</div>
                        <h4 id="metricTotalContacts" class="fw-bold mb-0" style="color: var(--on-surface);">{{ number_format($campaign->total_contacts) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--secondary-container, #e8def8); color: var(--secondary, #625b71); flex-shrink: 0;">
                        <i class="fas fa-paper-plane fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('campaign.messages_sent') }}</div>
                        <h4 id="metricSentCount" class="fw-bold mb-0" style="color: var(--on-surface);">{{ number_format($campaign->sent_count) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #e8f5e9; color: #1b5e20; flex-shrink: 0;">
                        <i class="fas fa-check-double fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('campaign.messages_read') }}</div>
                        <h4 id="metricReadCount" class="fw-bold mb-0" style="color: #1b5e20;">{{ number_format($campaign->read_count) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #fde8e8; color: #b91c1c; flex-shrink: 0;">
                        <i class="fas fa-triangle-exclamation fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('campaign.messages_failed') }}</div>
                        <h4 id="metricFailedCount" class="fw-bold mb-0" style="color: #b91c1c;">{{ number_format($campaign->failed_count) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages Delivery Log Table -->
    <div class="card border-0 shadow-sm p-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
            <h6 class="fw-bold mb-0" style="color: var(--primary, #65558f);">Daftar Log Pengiriman Pesan</h6>
            
            <!-- Status Filter for Messages -->
            <form method="GET" action="{{ route('campaigns.show', $campaign->id) }}" class="m-0">
                <select name="status" class="form-select form-select-sm rounded-pill px-3 py-1.5" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0); font-size: 0.8125rem;">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>{{ __('campaign.all_status') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Menunggu (Pending)</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ __('campaign.messages_sent') }}</option>
                    <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>{{ __('campaign.messages_delivered') }}</option>
                    <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>{{ __('campaign.messages_read') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('campaign.messages_failed') }}</option>
                </select>
            </form>
        </div>

        @if($messages->isEmpty())
            <div class="text-center py-5" style="color: var(--on-surface-variant);">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">
                    <i class="fas fa-inbox fs-4"></i>
                </div>
                <p class="small mb-0">Tidak ada pesan untuk filter status ini.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--outline-variant, #cac4d0); color: var(--on-surface-variant); font-size: 0.8125rem;">
                            <th class="pb-3">Penerima</th>
                            <th class="pb-3">Isi Pesan (Spintax Resolved)</th>
                            <th class="pb-3">Waktu Kirim</th>
                            <th class="pb-3 text-end">Status WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $msg)
                            <tr style="border-bottom: 1px solid var(--outline-variant, #cac4d0);">
                                <td class="py-3">
                                    <div class="fw-bold" style="color: var(--on-surface);">{{ $msg->recipient_name ?: 'Pelanggan' }}</div>
                                    <div class="small" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ $msg->phone_number }}</div>
                                </td>
                                <td class="py-3" style="max-width: 450px;">
                                    <div class="small" style="white-space: pre-wrap; word-break: break-word; color: var(--on-surface); font-size: 0.8125rem;">{{ $msg->message_text }}</div>
                                    @if($msg->error_message)
                                        <div class="text-danger small mt-1" style="font-size: 0.75rem;">
                                            <i class="fas fa-triangle-exclamation me-1"></i>
                                            <span>{{ $msg->error_message }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 small" style="color: var(--on-surface-variant); font-size: 0.8125rem;">
                                    {{ $msg->sent_at ? $msg->sent_at->format('H:i:s, d M') : '-' }}
                                </td>
                                <td class="py-3 text-end">
                                    @if($msg->status === 'read')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
                                            <i class="fas fa-check-double me-1"></i>Dibaca
                                        </span>
                                    @elseif($msg->status === 'delivered')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20);">
                                            <i class="fas fa-check-double me-1"></i>Diterima
                                        </span>
                                    @elseif($msg->status === 'sent')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">
                                            <i class="fas fa-check me-1"></i>Terkirim
                                        </span>
                                    @elseif($msg->status === 'failed')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">
                                            <i class="fas fa-xmark me-1"></i>Gagal
                                        </span>
                                    @else
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                                            <i class="fas fa-clock me-1"></i>Antrean
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
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
                const bar = document.getElementById('campaignProgressBar');
                if (bar) {
                    bar.style.width = (data.progress_percent || 0) + '%';
                }
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
