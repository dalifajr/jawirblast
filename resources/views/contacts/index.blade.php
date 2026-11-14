@extends('layouts.app')

@section('content')
<div class="responsive padding" style="max-width: 1200px; margin: 0 auto; width: 100%;">
    <!-- Header & Action Buttons -->
    <header class="row middle-align margin-bottom">
        <div class="max">
            <h4 class="bold primary-text no-margin">{{ __('contacts.title') }}</h4>
            <p class="text-secondary no-margin small">{{ __('contacts.subtitle') }}</p>
        </div>
        <div class="row no-wrap gap">
            <button class="button primary round" onclick="openModal('modal-add-contact')">
                <i>person_add</i>
                <span>{{ __('contacts.add_contact') }}</span>
            </button>
            <button class="button surface-container-high round" onclick="openModal('modal-import-contact')">
                <i>upload_file</i>
                <span>{{ __('contacts.import_contacts') }}</span>
            </button>
            <a href="{{ route('contacts.export', request()->only(['group_id', 'status'])) }}" class="button border round">
                <i>download</i>
                <span>{{ __('contacts.export_contacts') }}</span>
            </a>
            <a href="{{ route('contacts.groups.index') }}" class="button transparent round">
                <i>folder_shared</i>
                <span>{{ __('contacts.groups_title') }}</span>
            </a>
        </div>
    </header>

    <!-- Stat Summary Cards -->
    <div class="row margin-bottom">
        <div class="col s12 m4">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="primary-text" style="font-size: 2rem;">contacts</i>
                    <div class="max">
                        <div class="text-secondary small">Total Kontak</div>
                        <h5 class="bold no-margin">{{ number_format($stats['total']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col s12 m4">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="green-text" style="font-size: 2rem;">verified</i>
                    <div class="max">
                        <div class="text-secondary small">Kontak Valid E.164 (Aktif)</div>
                        <h5 class="bold no-margin green-text">{{ number_format($stats['active']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col s12 m4">
            <div class="card round surface border padding">
                <div class="row middle-align">
                    <i class="red-text" style="font-size: 2rem;">error</i>
                    <div class="max">
                        <div class="text-secondary small">Nomor Tidak Valid</div>
                        <h5 class="bold no-margin red-text">{{ number_format($stats['invalid']) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card round surface border padding margin-bottom">
        <form method="GET" action="{{ route('contacts.index') }}" class="row middle-align no-margin gap">
            <!-- Search Bar -->
            <div class="field prefix round max no-margin">
                <i>search</i>
                <input type="text" name="search" value="{{ is_string(request('search')) ? request('search') : '' }}" placeholder="Cari nama atau nomor WhatsApp...">
            </div>

            <!-- Filter Grup -->
            <div class="field round no-margin" style="min-width: 180px;">
                <select name="group_id" onchange="this.form.submit()">
                    <option value="">{{ __('contacts.all_groups') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->name }} ({{ $group->contacts_count }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div class="field round no-margin" style="min-width: 150px;">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('contacts.status_active') }}</option>
                    <option value="invalid" {{ request('status') === 'invalid' ? 'selected' : '' }}>{{ __('contacts.status_invalid') }}</option>
                    <option value="unsubscribed" {{ request('status') === 'unsubscribed' ? 'selected' : '' }}>{{ __('contacts.status_unsubscribed') }}</option>
                </select>
            </div>

            <button type="submit" class="button round primary">
                <i>filter_list</i>
                <span>Filter</span>
            </button>

            @if (request()->hasAny(['search', 'group_id', 'status']))
                <a href="{{ route('contacts.index') }}" class="button round border error">
                    <i>clear</i>
                    <span>Reset</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Contacts Table -->
    <div class="card round surface border padding no-padding-horizontal">
        <form id="batch-delete-form" method="POST" action="{{ route('contacts.batch_delete') }}">
            @csrf
            <div class="padding-horizontal margin-bottom row middle-align">
                <button type="submit" id="btn-batch-delete" class="button border error round small" style="display: none;" onclick="return confirm('Apakah Anda yakin ingin menghapus kontak yang dipilih?')">
                    <i>delete_sweep</i>
                    <span>{{ __('contacts.batch_delete') }} (<span id="selected-count">0</span>)</span>
                </button>
            </div>

            <div class="responsive">
                <table class="border no-border-left no-border-right">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <label class="checkbox">
                                    <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                                    <span></span>
                                </label>
                            </th>
                            <th>{{ __('contacts.name') }}</th>
                            <th>{{ __('contacts.phone_number') }}</th>
                            <th>{{ __('contacts.raw_phone') }}</th>
                            <th>{{ __('contacts.group') }}</th>
                            <th>{{ __('contacts.status') }}</th>
                            <th class="right-align">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr>
                                <td>
                                    <label class="checkbox">
                                        <input type="checkbox" name="ids[]" value="{{ $contact->id }}" class="contact-checkbox" onchange="updateBatchDeleteBtn()">
                                        <span></span>
                                    </label>
                                </td>
                                <td class="bold">{{ $contact->name }}</td>
                                <td>
                                    <code>{{ $contact->phone_number }}</code>
                                </td>
                                <td class="text-secondary small">{{ $contact->raw_phone ?? '-' }}</td>
                                <td>
                                    @forelse ($contact->groups as $grp)
                                        <span class="chip small primary-container">{{ $grp->name }}</span>
                                    @empty
                                        <span class="text-secondary small">-</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($contact->status === 'active')
                                        <span class="badge green white-text round small">{{ __('contacts.status_active') }}</span>
                                    @elseif ($contact->status === 'invalid')
                                        <span class="badge red white-text round small">{{ __('contacts.status_invalid') }}</span>
                                    @else
                                        <span class="badge grey white-text round small">{{ __('contacts.status_unsubscribed') }}</span>
                                    @endif
                                </td>
                                <td class="right-align">
                                    <div class="row no-wrap right-align gap">
                                        <button type="button" class="button circle small transparent" onclick="openEditModal({{ json_encode($contact) }})" title="{{ __('contacts.edit_contact') }}">
                                            <i>edit</i>
                                        </button>
                                        <form method="POST" action="{{ route('contacts.destroy', $contact->id) }}" class="no-margin" onsubmit="return confirm('{{ __('contacts.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button circle small transparent error-text" title="{{ __('contacts.delete_contact') }}">
                                                <i>delete</i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="center-align padding text-secondary">
                                    <i style="font-size: 3rem;" class="margin-bottom">contact_page</i>
                                    <p>{{ __('contacts.no_contacts') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="padding-horizontal margin-top">
            {{ $contacts->links() }}
        </div>
    </div>
</div>

<!-- Modal Tambah Kontak -->
<dialog id="modal-add-contact" class="round">
    <h5 class="bold margin-bottom">{{ __('contacts.add_contact') }}</h5>
    <form method="POST" action="{{ route('contacts.store') }}">
        @csrf
        <div class="field label border round margin-bottom">
            <input type="text" name="name" required placeholder=" ">
            <label>{{ __('contacts.name') }}</label>
        </div>

        <div class="field label border round margin-bottom">
            <input type="text" name="phone_number" required placeholder=" ">
            <label>{{ __('contacts.phone_number') }} (contoh: 081234567890)</label>
            <span class="helper">{{ __('contacts.normalization_notice') }}</span>
        </div>

        <div class="field label border round margin-bottom">
            <select name="group_id">
                <option value="">-- {{ __('contacts.select_group') }} --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
            <label>{{ __('contacts.group') }}</label>
        </div>

        <nav class="right-align margin-top">
            <button type="button" class="button border round" onclick="closeModal('modal-add-contact')">Batal</button>
            <button type="submit" class="button primary round">
                <i>save</i>
                <span>Simpan</span>
            </button>
        </nav>
    </form>
</dialog>

<!-- Modal Edit Kontak -->
<dialog id="modal-edit-contact" class="round">
    <h5 class="bold margin-bottom">{{ __('contacts.edit_contact') }}</h5>
    <form id="edit-contact-form" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="field label border round margin-bottom">
            <input type="text" name="name" id="edit-name" required placeholder=" ">
            <label>{{ __('contacts.name') }}</label>
        </div>

        <div class="field label border round margin-bottom">
            <input type="text" name="phone_number" id="edit-phone" placeholder=" ">
            <label>{{ __('contacts.phone_number') }}</label>
            <span class="helper">{{ __('contacts.normalization_notice') }}</span>
        </div>

        <div class="field label border round margin-bottom">
            <select name="status" id="edit-status">
                <option value="active">{{ __('contacts.status_active') }}</option>
                <option value="invalid">{{ __('contacts.status_invalid') }}</option>
                <option value="unsubscribed">{{ __('contacts.status_unsubscribed') }}</option>
            </select>
            <label>{{ __('contacts.status') }}</label>
        </div>

        <div class="field label border round margin-bottom">
            <select name="group_id" id="edit-group">
                <option value="">-- {{ __('contacts.select_group') }} --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
            <label>{{ __('contacts.group') }}</label>
        </div>

        <nav class="right-align margin-top">
            <button type="button" class="button border round" onclick="closeModal('modal-edit-contact')">Batal</button>
            <button type="submit" class="button primary round">
                <i>check</i>
                <span>Perbarui</span>
            </button>
        </nav>
    </form>
</dialog>

<!-- Modal Impor Kontak -->
<dialog id="modal-import-contact" class="round" style="max-width: 520px; width: 100%;">
    <h5 class="bold margin-bottom">{{ __('contacts.import_modal_title') }}</h5>
    <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
        @csrf
        <p class="text-secondary small margin-bottom">{{ __('contacts.import_instructions') }}</p>

        <div class="field border round margin-bottom padding">
            <label class="bold block margin-bottom small">{{ __('contacts.import_file_label') }}</label>
            <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required class="responsive">
        </div>

        <div class="field label border round margin-bottom">
            <select name="group_id">
                <option value="">-- Tidak Dikaitkan ke Grup --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
            <label>Masukkan ke Grup (Opsional)</label>
        </div>

        <div class="margin-bottom">
            <a href="{{ route('contacts.template') }}" class="button transparent small no-padding-left">
                <i>download</i>
                <span>{{ __('contacts.import_template_download') }}</span>
            </a>
        </div>

        <nav class="right-align margin-top">
            <button type="button" class="button border round" onclick="closeModal('modal-import-contact')">Batal</button>
            <button type="submit" class="button primary round">
                <i>cloud_upload</i>
                <span>Mulai Impor</span>
            </button>
        </nav>
    </form>
</dialog>

<script>
    function openModal(id) {
        const dialog = document.getElementById(id);
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else if (dialog) {
            dialog.setAttribute('open', '');
        }
    }

    function closeModal(id) {
        const dialog = document.getElementById(id);
        if (dialog && typeof dialog.close === 'function') {
            dialog.close();
        } else if (dialog) {
            dialog.removeAttribute('open');
        }
    }

    function openEditModal(contact) {
        document.getElementById('edit-name').value = contact.name || '';
        document.getElementById('edit-phone').value = contact.phone_number || '';
        document.getElementById('edit-status').value = contact.status || 'active';
        if (contact.groups && contact.groups.length > 0) {
            document.getElementById('edit-group').value = contact.groups[0].id;
        } else {
            document.getElementById('edit-group').value = '';
        }
        document.getElementById('edit-contact-form').action = '/contacts/' + contact.id;
        openModal('modal-edit-contact');
    }

    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.contact-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        updateBatchDeleteBtn();
    }

    function updateBatchDeleteBtn() {
        const checked = document.querySelectorAll('.contact-checkbox:checked');
        const btn = document.getElementById('btn-batch-delete');
        const countSpan = document.getElementById('selected-count');
        if (checked.length > 0) {
            btn.style.display = 'inline-flex';
            countSpan.textContent = checked.length;
        } else {
            btn.style.display = 'none';
            countSpan.textContent = '0';
        }
    }
</script>
@endsection
