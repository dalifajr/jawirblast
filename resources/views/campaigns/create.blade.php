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

    <!-- Header Breadcrumb -->
    <div class="row middle-align margin-bottom">
        <a href="{{ route('campaigns.index') }}" class="button transparent circle margin-right">
            <i>arrow_back</i>
        </a>
        <div>
            <h4 class="bold primary-text no-margin">Buat Kampanye Baru</h4>
            <p class="text-secondary no-margin small">Atur template pesan berspintax, target audiens, dan interval delay siaran.</p>
        </div>
    </div>

    <!-- WhatsApp Session Status Banner -->
    @if(!$session || !$session->isConnected())
        <div class="card round amber-border surface amber-text padding margin-bottom">
            <div class="row middle-align">
                <i class="amber-text" style="font-size: 2rem;">warning</i>
                <div class="max">
                    <div class="bold">Sesi WhatsApp Belum Terhubung</div>
                    <div class="small">Soket Baileys tidak aktif. Anda tetap dapat membuat draf kampanye, namun siaran hanya dapat dikirim setelah sesi terhubung.</div>
                </div>
                <a href="{{ route('whatsapp.index') }}" class="button amber white-text round small">Hubungkan WhatsApp</a>
            </div>
        </div>
    @else
        <div class="card round border green-border surface padding margin-bottom">
            <div class="row middle-align">
                <i class="green-text" style="font-size: 1.8rem;">check_circle</i>
                <div class="max">
                    <span class="bold green-text">WhatsApp Terhubung:</span>
                    <span class="small text-secondary">+{{ $session->phone_number }} (Session: {{ $session->session_id }})</span>
                </div>
            </div>
        </div>
    @endif

    <form id="campaignForm" method="POST" action="{{ route('campaigns.store') }}">
        @csrf
        <div class="grid">
            <!-- Left Column: Form Configuration (8 Cols) -->
            <div class="s12 m12 l8">
                <!-- 1. Informasi Dasar Kampanye -->
                <div class="card round surface border padding margin-bottom">
                    <h6 class="bold primary-text margin-bottom">1. Informasi Kampanye</h6>
                    
                    <div class="field border round label @error('title') invalid @enderror">
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required placeholder=" ">
                        <label for="title">Judul / Nama Kampanye *</label>
                        <i>title</i>
                        @error('title')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- 2. Target Kontak / Penerima -->
                <div class="card round surface border padding margin-bottom">
                    <h6 class="bold primary-text margin-bottom">2. Target Audiens / Kontak</h6>

                    <div class="row gap margin-bottom">
                        <label class="radio">
                            <input type="radio" name="target_type" value="all" {{ old('target_type', 'all') === 'all' ? 'checked' : '' }} onchange="toggleTargetType('all')">
                            <span>Semua Kontak Aktif ({{ number_format($activeContactsCount) }})</span>
                        </label>
                        <label class="radio">
                            <input type="radio" name="target_type" value="group" {{ old('target_type') === 'group' ? 'checked' : '' }} onchange="toggleTargetType('group')">
                            <span>Berdasarkan Grup Kontak</span>
                        </label>
                        <label class="radio">
                            <input type="radio" name="target_type" value="selected" {{ old('target_type') === 'selected' ? 'checked' : '' }} onchange="toggleTargetType('selected')">
                            <span>Pilih Kontak Tertentu</span>
                        </label>
                    </div>

                    <!-- Grup Selector -->
                    <div id="groupSelectorSection" style="display: {{ old('target_type') === 'group' ? 'block' : 'none' }};" class="padding border round surface-container-low margin-bottom">
                        <p class="small bold text-secondary margin-bottom">Centang satu atau lebih grup kontak tujuan:</p>
                        @if($groups->isEmpty())
                            <p class="small text-secondary">Belum ada grup kontak. Silakan buat grup di menu Grup Kontak.</p>
                        @else
                            <div class="grid">
                                @foreach($groups as $grp)
                                    <div class="s12 m6">
                                        <label class="checkbox">
                                            <input type="checkbox" name="group_ids[]" value="{{ $grp->id }}" {{ in_array($grp->id, old('group_ids', [])) ? 'checked' : '' }}>
                                            <span>{{ $grp->name }} ({{ $grp->contacts_count }} kontak)</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Specific Contacts Selector -->
                    <div id="contactSelectorSection" style="display: {{ old('target_type') === 'selected' ? 'block' : 'none' }}; max-height: 250px; overflow-y: auto;" class="padding border round surface-container-low margin-bottom">
                        <p class="small bold text-secondary margin-bottom">Pilih kontak yang akan menerima siaran:</p>
                        <div class="grid">
                            @foreach($sampleContacts as $sc)
                                <div class="s12 m6">
                                    <label class="checkbox">
                                        <input type="checkbox" name="contact_ids[]" value="{{ $sc->id }}" {{ in_array($sc->id, old('contact_ids', [])) ? 'checked' : '' }}>
                                        <span class="small">{{ $sc->name }} ({{ $sc->phone_number }})</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 3. Template Pesan & Variabel Dinamis -->
                <div class="card round surface border padding margin-bottom">
                    <h6 class="bold primary-text margin-bottom">3. Konten Pesan & Spintax</h6>

                    <!-- Variable Chips Helper -->
                    <div class="row middle-align gap margin-bottom">
                        <span class="small text-secondary">Sisipkan Tag:</span>
                        <button type="button" class="chip border small" onclick="insertTag('{name}')">+ {name}</button>
                        <button type="button" class="chip border small" onclick="insertTag('{phone}')">+ {phone}</button>
                        <button type="button" class="chip border small" onclick="insertTag('{nomor}')">+ {nomor}</button>
                        <button type="button" class="chip border small" onclick="insertSpintaxPattern()">+ {Opsi 1|Opsi 2}</button>
                    </div>

                    <div class="field textarea border round @error('message_template') invalid @enderror">
                        <textarea id="messageTemplate" name="message_template" rows="7" required placeholder="Tuliskan pesan Anda... Contoh: {Halo|Hai|Selamat pagi} {name}, ada penawaran menarik untuk nomor {phone}!">{{ old('message_template', '{Halo|Hai} {name}, kami ada informasi spesial untuk Anda!') }}</textarea>
                        @error('message_template')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="surface-container-low padding round small text-secondary margin-top">
                        <i>info</i>
                        <span>Gunakan kurung kurawal berpipa seperti <code>{Halo|Hai|Salam}</code> untuk memvariasikan isi pesan secara acak guna menghindari pemblokiran WhatsApp.</span>
                    </div>
                </div>

                <!-- 4. Parameter Pacing & Delay -->
                <div class="card round surface border padding margin-bottom">
                    <h6 class="bold primary-text margin-bottom">4. Pacing Staggered Delay (Anti-Ban)</h6>

                    <div class="row gap">
                        <div class="col s12 m6">
                            <div class="field border round label">
                                <input type="number" id="minDelay" name="min_delay_seconds" value="{{ old('min_delay_seconds', 2) }}" min="0" max="300" required>
                                <label for="minDelay">Min Delay (detik)</label>
                                <i>timer</i>
                            </div>
                        </div>
                        <div class="col s12 m6">
                            <div class="field border round label">
                                <input type="number" id="maxDelay" name="max_delay_seconds" value="{{ old('max_delay_seconds', 5) }}" min="0" max="600" required>
                                <label for="maxDelay">Max Delay (detik)</label>
                                <i>timelapse</i>
                            </div>
                        </div>
                    </div>
                    <p class="small text-secondary no-margin">
                        Pesan akan dikirim dengan jeda acak bertingkat antara Min dan Max Delay. Jeda bertingkat melindungi akun Anda dari deteksi spam WhatsApp.
                    </p>
                </div>

                <!-- Form Action Buttons -->
                <div class="row gap margin-top">
                    <button type="submit" name="action" value="save_draft" class="button border round">
                        <i>save</i>
                        <span>Simpan Sebagai Draf</span>
                    </button>
                    <button type="submit" name="action" value="start_now" class="button primary round">
                        <i>send</i>
                        <span>Kirim Siaran Sekarang</span>
                    </button>
                </div>
            </div>

            <!-- Right Column: Live Spintax Calculator & Simulator (4 Cols) -->
            <div class="s12 m12 l4">
                <div class="card surface border round padding" style="position: sticky; top: 20px;">
                    <div class="row middle-align margin-bottom">
                        <i class="primary-text">psychology</i>
                        <h6 class="bold no-margin max">Kalkulator Spintax</h6>
                    </div>

                    <div class="center-align padding surface-container-low round margin-bottom">
                        <div class="text-secondary small">Estimasi Variasi Unik</div>
                        <h3 id="variationCountBadge" class="bold primary-text no-margin">1</h3>
                        <span id="variationStatusText" class="chip green white-text small margin-top">Pesan Siap</span>
                    </div>

                    <div class="divider margin-bottom"></div>

                    <div class="row middle-align margin-bottom">
                        <span class="bold small text-secondary max">Pratinjau Hasil Pesan:</span>
                        <button type="button" class="button circle transparent small" onclick="fetchSpintaxPreview()" title="Acak Sampel Baru">
                            <i>refresh</i>
                        </button>
                    </div>

                    <div id="samplePreviewBox" class="surface-container padding round border" style="font-size: 0.85rem; min-height: 120px; white-space: pre-wrap; word-break: break-word;">
                        Memuat pratinjau pesan...
                    </div>

                    <div class="divider margin-vertical"></div>

                    <div class="small text-secondary">
                        <div class="row middle-align margin-bottom">
                            <i class="small">schedule</i>
                            <span class="bold max">Estimasi Durasi Siaran:</span>
                        </div>
                        <p id="durationEstimateText" class="bold primary-text no-margin">
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
                    statusBadge.className = 'chip green white-text small margin-top';
                    statusBadge.innerText = 'Sangat Bervariasi';
                } else if (data.count > 1) {
                    statusBadge.className = 'chip primary white-text small margin-top';
                    statusBadge.innerText = 'Cukup Bervariasi';
                } else {
                    statusBadge.className = 'chip border small margin-top';
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
