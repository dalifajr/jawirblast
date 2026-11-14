@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1200px; margin: 0 auto;">
    <!-- Header & Action CTA -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('campaign.title') }}</h4>
            <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">{{ __('campaign.subtitle') }}</p>
        </div>
        <div>
            <a href="{{ route('campaigns.create') }}" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                <i class="fas fa-plus"></i>
                <span>{{ __('campaign.create_campaign') }}</span>
            </a>
        </div>
    </div>

    <!-- 4 Tonal Metric Cards (2x2 Grid on Mobile, 4x1 on Desktop) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                        <i class="fas fa-bullhorn fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('campaign.total_campaigns') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['total_campaigns']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: var(--secondary-container, #e8def8); color: var(--secondary, #625b71); flex-shrink: 0;">
                        <i class="fas fa-paper-plane fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('campaign.messages_sent') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['sent_messages']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: #e8f5e9; color: #1b5e20; flex-shrink: 0;">
                        <i class="fas fa-check-double fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('campaign.messages_read') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['read_messages']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2.5 p-sm-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 20px;">
                <div class="d-flex align-items-center gap-2 gap-sm-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px; background: #fde8e8; color: #b91c1c; flex-shrink: 0;">
                        <i class="fas fa-triangle-exclamation fs-6 fs-sm-5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="small fw-medium text-truncate" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('campaign.messages_failed') }}</div>
                        <h4 class="fw-bold mb-0 text-truncate" style="color: var(--on-surface); font-size: 1.15rem;">{{ number_format($stats['failed_messages']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
        <form method="GET" action="{{ route('campaigns.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md">
                <div class="input-group">
                    <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control rounded-end-pill py-2" placeholder="{{ __('campaign.search_placeholder') }}" value="{{ request('search') }}" style="border-color: var(--outline-variant, #cac4d0);">
                </div>
            </div>
            <div class="col-12 col-md-auto" style="min-width: 180px;">
                <select name="status" class="form-select rounded-pill py-2" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0);">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>{{ __('campaign.all_status') }}</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('campaign.status_draft') }}</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>{{ __('campaign.status_processing') }}</option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>{{ __('campaign.status_paused') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('campaign.status_completed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('campaign.status_cancelled') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn rounded-pill px-3.5 py-2 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);">
                    <i class="fas fa-filter me-1.5"></i>{{ __('campaign.filter') }}
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('campaigns.index') }}" class="btn rounded-pill px-3 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);">
                        <i class="fas fa-times me-1"></i>{{ __('common.reset') }}
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Campaigns Table Card -->
    <div class="card border-0 shadow-sm p-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        @if($campaigns->isEmpty())
            <div class="text-center py-5">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">
                    <i class="fas fa-bullhorn fs-3"></i>
                </div>
                <h5 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('common.no_data') }}</h5>
                <p class="small mb-3" style="color: var(--on-surface-variant);">{{ __('campaign.subtitle') }}</p>
                <a href="{{ route('campaigns.create') }}" class="btn rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                    <i class="fas fa-plus me-1.5"></i>{{ __('campaign.create_campaign') }}
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--outline-variant, #cac4d0); color: var(--on-surface-variant); font-size: 0.8125rem;">
                            <th class="pb-3">{{ __('campaign.campaign_title') }}</th>
                            <th class="pb-3">{{ __('campaign.target_recipients') }}</th>
                            <th class="pb-3" style="min-width: 200px;">{{ __('campaign.progress_title') }}</th>
                            <th class="pb-3">{{ __('common.actions') }}</th>
                            <th class="pb-3 text-end">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $camp)
                            <tr style="border-bottom: 1px solid var(--outline-variant, #cac4d0);">
                                <td class="py-3">
                                    <div class="fw-bold" style="color: var(--on-surface);">{{ $camp->title }}</div>
                                    <div class="small" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                                        <i class="far fa-calendar-alt me-1"></i>{{ $camp->created_at->format('d M Y, H:i') }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge rounded-pill px-2.5 py-1.5" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); font-weight: 500;">
                                        <i class="fas fa-users me-1"></i>{{ number_format($camp->total_contacts) }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <div class="progress flex-grow-1" style="height: 8px; border-radius: 9999px; background: var(--surface-container, #ece6f0);">
                                            <div class="progress-bar rounded-pill" role="progressbar" style="width: {{ $camp->progress_percent }}%; background: var(--primary, #65558f);" aria-valuenow="{{ $camp->progress_percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="small fw-bold" style="color: var(--primary); font-size: 0.75rem;">{{ $camp->progress_percent }}%</span>
                                    </div>
                                    <div class="small" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                                        <span>{{ $camp->sent_count }} {{ __('campaign.messages_sent') }}</span>
                                        @if($camp->failed_count > 0)
                                            · <span class="text-danger fw-semibold">{{ $camp->failed_count }} {{ __('campaign.messages_failed') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    @if($camp->status === 'draft')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">{{ __('campaign.status_draft') }}</span>
                                    @elseif($camp->status === 'processing')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--primary-container, #eaddff); color: var(--primary, #65558f);">
                                            <i class="fas fa-rotate fa-spin me-1"></i>{{ __('campaign.status_processing') }}
                                        </span>
                                    @elseif($camp->status === 'paused')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;">
                                            <i class="fas fa-pause me-1"></i>{{ __('campaign.status_paused') }}
                                        </span>
                                    @elseif($camp->status === 'completed')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">
                                            <i class="fas fa-check me-1"></i>{{ __('campaign.status_completed') }}
                                        </span>
                                    @elseif(in_array($camp->status, ['cancelled', 'canceled']))
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">
                                            <i class="fas fa-times me-1"></i>{{ __('campaign.status_cancelled') }}
                                        </span>
                                    @else
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container-high); color: var(--on-surface);">{{ ucfirst($camp->status) }}</span>
                                    @endif
                                </td>
                                <td class="py-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1.5">
                                        <a href="{{ route('campaigns.show', $camp->id) }}" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--surface-container, #ece6f0); color: var(--on-surface);" title="{{ __('campaign.campaign_detail') }}">
                                            <i class="fas fa-eye" style="font-size: 0.75rem;"></i>
                                        </a>

                                        @if($camp->isDraft())
                                            <form method="POST" action="{{ route('campaigns.start', $camp->id) }}" class="d-inline m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--primary, #65558f); color: #fff;" title="{{ __('campaign.start_broadcast') }}">
                                                    <i class="fas fa-play" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </form>
                                        @elseif($camp->isProcessing())
                                            <form method="POST" action="{{ route('campaigns.pause', $camp->id) }}" class="d-inline m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #fff8e1; color: #f57f17; border: 1px solid #ffecb3;" title="{{ __('campaign.pause_broadcast') }}">
                                                    <i class="fas fa-pause" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </form>
                                        @elseif($camp->isPaused())
                                            <form method="POST" action="{{ route('campaigns.resume', $camp->id) }}" class="d-inline m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--primary, #65558f); color: #fff;" title="{{ __('campaign.resume_broadcast') }}">
                                                    <i class="fas fa-play" style="font-size: 0.75rem;"></i>
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

            <div class="mt-4">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
