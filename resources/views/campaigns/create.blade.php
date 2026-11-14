@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1200px; margin: 0 auto;">
    <!-- Header Breadcrumb & Title -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('campaigns.index') }}" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: var(--surface-container, #ece6f0); color: var(--on-surface);" title="{{ __('common.back') }}">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('campaign.create_campaign') }}</h4>
            <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">{{ __('campaign.subtitle') }}</p>
        </div>
    </div>

    <!-- WhatsApp Session Status Banner -->
    @if(!$session || !$session->isConnected())
        <div class="card border-0 shadow-sm p-4 mb-4 rounded-4" style="background: #fff8e1; border: 1px solid #ffecb3 !important;">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: #fef3c7; color: #b45309; flex-shrink: 0;">
                        <i class="fas fa-triangle-exclamation fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="color: #92400e;">{{ __('campaign.wa_not_connected') }}</div>
                        <div class="small" style="color: #b45309;">Soket Baileys tidak aktif. Anda tetap dapat membuat draf kampanye, namun siaran hanya dapat dikirim setelah sesi terhubung.</div>
                    </div>
                </div>
                <a href="{{ route('whatsapp.index') }}" class="btn rounded-pill px-3.5 py-2 fw-semibold text-nowrap" style="background: #f59e0b; color: #fff;">
                    <i class="fas fa-link me-1.5"></i>{{ __('campaign.connect_wa_btn') }}
                </a>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm p-3 mb-4 rounded-4" style="background: #f0fdf4; border: 1px solid #bbf7d0 !important;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #dcfce7; color: #15803d; flex-shrink: 0;">
                    <i class="fas fa-circle-check fs-5"></i>
                </div>
                <div>
                    <span class="fw-bold" style="color: #14532d;">WhatsApp Terhubung:</span>
                    <span class="small" style="color: #166534;">+{{ $session->phone_number }} (Session: {{ $session->session_id }})</span>
                </div>
            </div>
        </div>
    @endif

    <form id="campaignForm" method="POST" action="{{ route('campaigns.store') }}">
        @csrf
        <div class="row g-4">
            <!-- Left Column: Form Configuration (8 Cols) -->
            <div class="col-12 col-lg-8">
                <!-- 1. Informasi Dasar Kampanye -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                    <h6 class="fw-bold mb-3" style="color: var(--primary, #65558f);">1. {{ __('campaign.campaign_detail') }}</h6>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-semibold" style="font-size: 0.8125rem; color: var(--on-surface);">{{ __('campaign.campaign_title') }} *</label>
                        <div class="input-group">
                            <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-heading"></i></span>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" required class="form-control rounded-end-pill py-2.5 @error('title') is-invalid @enderror" placeholder="{{ __('campaign.campaign_title_placeholder') }}" style="border-color: var(--outline-variant, #cac4d0);">
                        </div>
                        @error('title')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- 2. Target Kontak / Penerima -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                    <h6 class="fw-bold mb-3" style="color: var(--primary, #65558f);">2. {{ __('campaign.target_recipients') }}</h6>

                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="target_type" id="target_all" value="all" {{ old('target_type', 'all') === 'all' ? 'checked' : '' }} onchange="toggleTargetType('all')">
                            <label class="form-check-label fw-medium" for="target_all" style="color: var(--on-surface); font-size: 0.875rem;">
                                {{ __('campaign.all_contacts', ['count' => number_format($activeContactsCount)]) }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="target_type" id="target_group" value="group" {{ old('target_type') === 'group' ? 'checked' : '' }} onchange="toggleTargetType('group')">
                            <label class="form-check-label fw-medium" for="target_group" style="color: var(--on-surface); font-size: 0.875rem;">
                                {{ __('campaign.by_group') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="target_type" id="target_selected" value="selected" {{ old('target_type') === 'selected' ? 'checked' : '' }} onchange="toggleTargetType('selected')">
                            <label class="form-check-label fw-medium" for="target_selected" style="color: var(--on-surface); font-size: 0.875rem;">
                                {{ __('campaign.by_contacts') }}
                            </label>
                        </div>
                    </div>

                    <!-- Grup Selector -->
                    <div id="groupSelectorSection" style="display: {{ old('target_type') === 'group' ? 'block' : 'none' }};" class="p-3 rounded-4 mb-3" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                        <p class="small fw-bold mb-2" style="color: var(--on-surface-variant);">Centang satu atau lebih grup kontak tujuan:</p>
                        @if($groups->isEmpty())
                            <p class="small mb-0" style="color: var(--on-surface-variant);">Belum ada grup kontak. Silakan buat grup di menu Grup Kontak.</p>
                        @else
                            <div class="row g-2">
                                @foreach($groups as $grp)
                                    <div class="col-12 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="group_ids[]" value="{{ $grp->id }}" id="grp_{{ $grp->id }}" {{ in_array($grp->id, old('group_ids', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="grp_{{ $grp->id }}" style="color: var(--on-surface);">
                                                {{ $grp->name }} ({{ $grp->contacts_count }} kontak)
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Specific Contacts Selector -->
                    <div id="contactSelectorSection" style="display: {{ old('target_type') === 'selected' ? 'block' : 'none' }}; max-height: 250px; overflow-y: auto;" class="p-3 rounded-4 mb-3" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
                        <p class="small fw-bold mb-2" style="color: var(--on-surface-variant);">Pilih kontak yang akan menerima siaran:</p>
                        <div class="row g-2">
                            @foreach($sampleContacts as $sc)
                                <div class="col-12 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="contact_ids[]" value="{{ $sc->id }}" id="ct_{{ $sc->id }}" {{ in_array($sc->id, old('contact_ids', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="ct_{{ $sc->id }}" style="color: var(--on-surface);">
                                            {{ $sc->name }} ({{ $sc->phone_number }})
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 3. Template Pesan & Variabel Dinamis -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                    <h6 class="fw-bold mb-3" style="color: var(--primary, #65558f);">3. {{ __('campaign.message_template') }}</h6>

                    <!-- Variable Chips Helper -->
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="small fw-medium" style="color: var(--on-surface-variant);">Sisipkan Tag:</span>
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold" onclick="insertTag('{name}')" style="background: var(--surface-container, #ece6f0); color: var(--primary, #65558f); border: 1px solid var(--outline-variant, #cac4d0); font-size: 0.75rem;">+ {name}</button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold" onclick="insertTag('{phone}')" style="background: var(--surface-container, #ece6f0); color: var(--primary, #65558f); border: 1px solid var(--outline-variant, #cac4d0); font-size: 0.75rem;">+ {phone}</button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold" onclick="insertSpintaxPattern()" style="background: var(--secondary-container, #e8def8); color: var(--on-secondary-container, #1d192b); border: 1px solid var(--outline-variant, #cac4d0); font-size: 0.75rem;">+ {Opsi 1|Opsi 2}</button>
                    </div>

                    <div class="mb-3">
                        <textarea id="messageTemplate" name="message_template" rows="7" required class="form-control rounded-4 p-3 @error('message_template') is-invalid @enderror" placeholder="{{ __('campaign.message_placeholder') }}" style="border-color: var(--outline-variant, #cac4d0); font-size: 0.875rem;">{{ old('message_template', '{Halo|Hai} {name}, kami ada informasi spesial untuk Anda!') }}</textarea>
                        @error('message_template')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); font-size: 0.8125rem; color: var(--on-surface-variant);">
                        <i class="fas fa-info-circle me-1.5 text-primary"></i>
                        <span>{{ __('campaign.spintax_hint_long') }}</span>
                    </div>
                </div>

                <!-- 4. Parameter Pacing & Delay -->
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                    <h6 class="fw-bold mb-3" style="color: var(--primary, #65558f);">4. {{ __('campaign.pacing_title') }}</h6>

                    <div class="row g-3 mb-2">
                        <div class="col-12 col-sm-6">
                            <label for="minDelay" class="form-label fw-semibold" style="font-size: 0.8125rem; color: var(--on-surface);">{{ __('campaign.min_delay') }}</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-stopwatch"></i></span>
                                <input type="number" id="minDelay" name="min_delay_seconds" value="{{ old('min_delay_seconds', 2) }}" min="0" max="300" required class="form-control rounded-end-pill py-2" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="maxDelay" class="form-label fw-semibold" style="font-size: 0.8125rem; color: var(--on-surface);">{{ __('campaign.max_delay') }}</label>
                            <div class="input-group">
                                <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-hourglass-half"></i></span>
                                <input type="number" id="maxDelay" name="max_delay_seconds" value="{{ old('max_delay_seconds', 5) }}" min="0" max="600" required class="form-control rounded-end-pill py-2" style="border-color: var(--outline-variant, #cac4d0);">
                            </div>
                        </div>
                    </div>
                    <p class="small mb-0" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                        {{ __('campaign.delay_help') }}
                    </p>
                </div>

                <!-- Form Action Buttons -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="submit" name="action" value="save_draft" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);">
                        <i class="fas fa-floppy-disk"></i>
                        <span>{{ __('campaign.save_draft') }}</span>
                    </button>
                    <button type="submit" name="action" value="start_now" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                        <i class="fas fa-paper-plane"></i>
                        <span>{{ __('campaign.send_now') }}</span>
                    </button>
                </div>
            </div>

            <!-- Right Column: Live Spintax Calculator & Simulator (4 Cols) -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm p-4 sticky-top" style="top: 80px; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
                    <div class="d-flex align-items-center gap-2.5 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                            <i class="fas fa-wand-magic-sparkles"></i>
                        </div>
                        <h6 class="fw-bold mb-0" style="color: var(--on-surface);">{{ __('campaign.spintax_calculator') }}</h6>
                    </div>

                    <div class="text-center p-3 mb-3 rounded-4" style="background: var(--surface-container-low, #f3edf7);">
                        <div class="small fw-medium mb-1" style="color: var(--on-surface-variant);">{{ __('campaign.estimated_variations') }}</div>
                        <h2 id="variationCountBadge" class="fw-bold mb-1" style="color: var(--primary, #65558f);">1</h2>
                        <span id="variationStatusText" class="badge rounded-pill px-3 py-1.5 fw-semibold" style="background: #e8f5e9; color: #1b5e20;">{{ __('campaign.message_ready') }}</span>
                    </div>

                    <hr class="my-3" style="border-color: var(--outline-variant, #cac4d0);">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold" style="color: var(--on-surface-variant);">{{ __('campaign.preview_random_sample') }}:</span>
                        <button type="button" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" onclick="fetchSpintaxPreview()" title="Acak Sampel Baru" style="width: 32px; height: 32px; background: var(--surface-container, #ece6f0); color: var(--on-surface);">
                            <i class="fas fa-rotate"></i>
                        </button>
                    </div>

                    <div id="samplePreviewBox" class="p-3 rounded-4 mb-3" style="background: var(--surface-container, #ece6f0); border: 1px solid var(--outline-variant, #cac4d0); font-size: 0.8125rem; min-height: 120px; white-space: pre-wrap; word-break: break-word; color: var(--on-surface);">
                        Memuat pratinjau pesan...
                    </div>

                    <hr class="my-3" style="border-color: var(--outline-variant, #cac4d0);">

                    <div class="small" style="color: var(--on-surface-variant);">
                        <div class="d-flex align-items-center gap-1.5 mb-1 fw-bold">
                            <i class="fas fa-clock"></i>
                            <span>Estimasi Durasi Siaran:</span>
                        </div>
                        <p id="durationEstimateText" class="fw-bold mb-0" style="color: var(--primary, #65558f); font-size: 0.95rem;">
                            Menghitung...
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function toggleTargetType(type) {
        document.getElementById('groupSelectorSection').style.display = (type === 'group') ? 'block' : 'none';
        document.getElementById('contactSelectorSection').style.display = (type === 'selected') ? 'block' : 'none';
        calculateEstimatedDuration();
    }

    function insertTag(tag) {
        const textarea = document.getElementById('messageTemplate');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + tag + text.substring(end);
        textarea.focus();
        textarea.selectionEnd = start + tag.length;
        fetchSpintaxPreview();
    }

    function insertSpintaxPattern() {
        insertTag('{Pilihan 1|Pilihan 2|Pilihan 3}');
    }

    let previewDebounceTimer = null;
    function fetchSpintaxPreview() {
        const template = document.getElementById('messageTemplate').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch("{{ route('campaigns.preview_spintax') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ template: template })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('variationCountBadge').innerText = data.count.toLocaleString();
                const statusBadge = document.getElementById('variationStatusText');
                if (data.count > 10) {
                    statusBadge.style.background = '#e8f5e9';
                    statusBadge.style.color = '#1b5e20';
                    statusBadge.innerText = 'Sangat Bervariasi';
                } else if (data.count > 1) {
                    statusBadge.style.background = 'var(--secondary-container, #e8def8)';
                    statusBadge.style.color = 'var(--on-secondary-container, #1d192b)';
                    statusBadge.innerText = 'Cukup Bervariasi';
                } else {
                    statusBadge.style.background = 'var(--surface-container, #ece6f0)';
                    statusBadge.style.color = 'var(--on-surface-variant, #49454f)';
                    statusBadge.innerText = 'Pesan Statis';
                }

                if (data.samples && data.samples.length > 0) {
                    document.getElementById('samplePreviewBox').innerText = data.samples[0];
                } else {
                    document.getElementById('samplePreviewBox').innerText = template;
                }
            }
        })
        .catch(err => console.error('Error fetching spintax preview:', err));
    }

    function calculateEstimatedDuration() {
        const minDelay = parseInt(document.getElementById('minDelay').value) || 2;
        const maxDelay = parseInt(document.getElementById('maxDelay').value) || 5;
        const avgDelay = (minDelay + maxDelay) / 2;
        
        let contactsCount = {{ $activeContactsCount }};
        const selectedRadio = document.querySelector('input[name="target_type"]:checked');
        if (selectedRadio && selectedRadio.value === 'selected') {
            const checkedBoxes = document.querySelectorAll('input[name="contact_ids[]"]:checked');
            contactsCount = checkedBoxes.length || 1;
        }

        const totalSeconds = Math.round(contactsCount * avgDelay);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        let formatted = '';
        if (minutes > 0) {
            formatted += `${minutes} menit `;
        }
        formatted += `${seconds} detik`;

        document.getElementById('durationEstimateText').innerText = `~${formatted} (untuk ${contactsCount} kontak)`;
    }

    document.getElementById('messageTemplate').addEventListener('input', function() {
        clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(fetchSpintaxPreview, 350);
    });

    document.getElementById('minDelay').addEventListener('input', calculateEstimatedDuration);
    document.getElementById('maxDelay').addEventListener('input', calculateEstimatedDuration);

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        fetchSpintaxPreview();
        calculateEstimatedDuration();
    });
</script>
@endpush
