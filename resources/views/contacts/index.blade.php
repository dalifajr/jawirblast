@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1200px; margin: 0 auto;">
    <!-- Header & Action Buttons -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('contacts.title') }}</h4>
            <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">{{ __('contacts.subtitle') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;" onclick="openModal('modal-add-contact')">
                <i class="fas fa-user-plus"></i>
                <span>{{ __('contacts.add_contact') }}</span>
            </button>
            <button class="btn rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);" onclick="openModal('modal-import-contact')">
                <i class="fas fa-file-import"></i>
                <span>{{ __('contacts.import_contacts') }}</span>
            </button>
            <a href="{{ route('contacts.export', request()->only(['group_id', 'status'])) }}" class="btn rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2" style="background: transparent; color: var(--primary, #65558f); border: 1px solid var(--outline-variant, #cac4d0);">
                <i class="fas fa-file-export"></i>
                <span>{{ __('contacts.export_contacts') }}</span>
            </a>
            <a href="{{ route('contacts.groups.index') }}" class="btn rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2" style="background: var(--secondary-container, #e8def8); color: var(--on-secondary-container, #1d192b); border: 1px solid var(--outline-variant, #cac4d0);">
                <i class="fas fa-folder"></i>
                <span>{{ __('contacts.groups_title') }}</span>
            </a>
        </div>
    </div>

    <!-- Stat Summary Cards (M3 Concentric Cards) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--primary-container, #eaddff); color: var(--primary, #65558f); flex-shrink: 0;">
                        <i class="fas fa-users fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('contacts.stat_total') }}</div>
                        <h4 class="fw-bold mb-0" style="color: var(--on-surface);">{{ number_format($stats['total']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #e8f5e9; color: #1b5e20; flex-shrink: 0;">
                        <i class="fas fa-circle-check fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('contacts.stat_valid') }}</div>
                        <h4 class="fw-bold mb-0" style="color: #1b5e20;">{{ number_format($stats['active']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: #fde8e8; color: #b91c1c; flex-shrink: 0;">
                        <i class="fas fa-triangle-exclamation fs-5"></i>
                    </div>
                    <div>
                        <div class="small fw-medium" style="color: var(--on-surface-variant);">{{ __('contacts.stat_invalid') }}</div>
                        <h4 class="fw-bold mb-0" style="color: #b91c1c;">{{ number_format($stats['invalid']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 24px;">
        <form method="GET" action="{{ route('contacts.index') }}" class="row g-2 align-items-center">
            <!-- Search Bar -->
            <div class="col-12 col-md">
                <div class="input-group">
                    <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control rounded-end-pill py-2" value="{{ is_string(request('search')) ? request('search') : '' }}" placeholder="{{ __('contacts.search_placeholder') }}" style="border-color: var(--outline-variant, #cac4d0);">
                </div>
            </div>

            <!-- Filter Grup -->
            <div class="col-12 col-md-auto" style="min-width: 180px;">
                <select name="group_id" class="form-select rounded-pill py-2" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0);">
                    <option value="">{{ __('contacts.all_groups') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->name }} ({{ $group->contacts_count }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div class="col-12 col-md-auto" style="min-width: 150px;">
                <select name="status" class="form-select rounded-pill py-2" onchange="this.form.submit()" style="border-color: var(--outline-variant, #cac4d0);">
                    <option value="">{{ __('contacts.all_status') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('contacts.status_active') }}</option>
                    <option value="invalid" {{ request('status') === 'invalid' ? 'selected' : '' }}>{{ __('contacts.status_invalid') }}</option>
                    <option value="unsubscribed" {{ request('status') === 'unsubscribed' ? 'selected' : '' }}>{{ __('contacts.status_unsubscribed') }}</option>
                </select>
            </div>

            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn rounded-pill px-3.5 py-2 fw-semibold" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);">
                    <i class="fas fa-filter me-1.5"></i>{{ __('common.filter') }}
                </button>
                @if (request()->hasAny(['search', 'group_id', 'status']))
                    <a href="{{ route('contacts.index') }}" class="btn rounded-pill px-3 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);">
                        <i class="fas fa-times me-1"></i>{{ __('common.reset') }}
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Contacts Table Card -->
    <div class="card border-0 shadow-sm p-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <form id="batch-delete-form" method="POST" action="{{ route('contacts.batch_delete') }}">
            @csrf
            <div class="mb-3 d-flex align-items-center">
                <button type="submit" id="btn-batch-delete" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="display: none; background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;" onclick="return confirm('{{ __('contacts.confirm_batch_delete') }}')">
                    <i class="fas fa-trash-can"></i>
                    <span>{{ __('contacts.batch_delete') }} (<span id="selected-count">0</span>)</span>
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--outline-variant, #cac4d0); color: var(--on-surface-variant); font-size: 0.8125rem;">
                            <th style="width: 40px;" class="pb-3">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                                </div>
                            </th>
                            <th class="pb-3">{{ __('contacts.name') }}</th>
                            <th class="pb-3">{{ __('contacts.phone_number') }}</th>
                            <th class="pb-3">{{ __('contacts.raw_phone') }}</th>
                            <th class="pb-3">{{ __('contacts.group') }}</th>
                            <th class="pb-3">{{ __('contacts.status') }}</th>
                            <th class="pb-3 text-end">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr style="border-bottom: 1px solid var(--outline-variant, #cac4d0);">
                                <td class="py-3">
                                    <div class="form-check m-0">
                                        <input class="form-check-input contact-checkbox" type="checkbox" name="ids[]" value="{{ $contact->id }}" onchange="updateBatchDeleteBtn()">
                                    </div>
                                </td>
                                <td class="py-3 fw-bold" style="color: var(--on-surface);">{{ $contact->name }}</td>
                                <td class="py-3">
                                    <code class="px-2 py-1 rounded" style="background: var(--surface-container, #ece6f0); color: var(--primary, #65558f);">{{ $contact->phone_number }}</code>
                                </td>
                                <td class="py-3 small" style="color: var(--on-surface-variant); font-size: 0.8125rem;">{{ $contact->raw_phone ?? '-' }}</td>
                                <td class="py-3">
                                    @forelse ($contact->groups as $grp)
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold me-1" style="background: var(--secondary-container, #e8def8); color: var(--on-secondary-container, #1d192b);">{{ $grp->name }}</span>
                                    @empty
                                        <span class="small" style="color: var(--on-surface-variant);">-</span>
                                    @endforelse
                                </td>
                                <td class="py-3">
                                    @if ($contact->status === 'active')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9;">{{ __('contacts.status_active') }}</span>
                                    @elseif ($contact->status === 'invalid')
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5;">{{ __('contacts.status_invalid') }}</span>
                                    @else
                                        <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--surface-container-high); color: var(--on-surface-variant);">{{ __('contacts.status_unsubscribed') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 text-end">
                                    <div class="d-inline-flex align-items-center gap-1.5">
                                        <button type="button" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--surface-container, #ece6f0); color: var(--on-surface);" onclick="openEditModal({{ json_encode($contact) }})" title="{{ __('contacts.edit_contact') }}">
                                            <i class="fas fa-pen" style="font-size: 0.75rem;"></i>
                                        </button>
                                        <form method="POST" action="{{ route('contacts.destroy', $contact->id) }}" class="d-inline m-0" onsubmit="return confirm('{{ __('contacts.confirm_delete') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #fde8e8; color: #b91c1c;" title="{{ __('contacts.delete_contact') }}">
                                                <i class="fas fa-trash-can" style="font-size: 0.75rem;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5" style="color: var(--on-surface-variant);">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">
                                        <i class="fas fa-address-book fs-3"></i>
                                    </div>
                                    <p class="mb-0">{{ __('contacts.no_contacts') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="mt-4">
            {{ $contacts->links() }}
        </div>
    </div>
</div>

<!-- Modal Tambah Kontak -->
<dialog id="modal-add-contact" class="border-0 shadow-lg p-4 rounded-4" style="max-width: 500px; width: 100%; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
    <h5 class="fw-bold mb-3" style="color: var(--on-surface);">{{ __('contacts.add_contact') }}</h5>
    <form method="POST" action="{{ route('contacts.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.name') }} *</label>
            <input type="text" name="name" required class="form-control rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.phone_number') }} *</label>
            <input type="text" name="phone_number" required class="form-control rounded-pill py-2 px-3" placeholder="081234567890" style="border-color: var(--outline-variant, #cac4d0);">
            <div class="form-text small mt-1" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('contacts.normalization_notice') }}</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.group') }}</label>
            <select name="group_id" class="form-select rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
                <option value="">-- {{ __('contacts.select_group') }} --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn rounded-pill px-3.5 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);" onclick="closeModal('modal-add-contact')">{{ __('common.cancel') }}</button>
            <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                <i class="fas fa-floppy-disk me-1.5"></i>{{ __('common.save') }}
            </button>
        </div>
    </form>
</dialog>

<!-- Modal Edit Kontak -->
<dialog id="modal-edit-contact" class="border-0 shadow-lg p-4 rounded-4" style="max-width: 500px; width: 100%; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
    <h5 class="fw-bold mb-3" style="color: var(--on-surface);">{{ __('contacts.edit_contact') }}</h5>
    <form id="edit-contact-form" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.name') }} *</label>
            <input type="text" name="name" id="edit-name" required class="form-control rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.phone_number') }}</label>
            <input type="text" name="phone_number" id="edit-phone" class="form-control rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
            <div class="form-text small mt-1" style="color: var(--on-surface-variant); font-size: 0.75rem;">{{ __('contacts.normalization_notice') }}</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.status') }}</label>
            <select name="status" id="edit-status" class="form-select rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
                <option value="active">{{ __('contacts.status_active') }}</option>
                <option value="invalid">{{ __('contacts.status_invalid') }}</option>
                <option value="unsubscribed">{{ __('contacts.status_unsubscribed') }}</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.group') }}</label>
            <select name="group_id" id="edit-group" class="form-select rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
                <option value="">-- {{ __('contacts.select_group') }} --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn rounded-pill px-3.5 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);" onclick="closeModal('modal-edit-contact')">{{ __('common.cancel') }}</button>
            <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                <i class="fas fa-check me-1.5"></i>{{ __('contacts.update_btn') }}
            </button>
        </div>
    </form>
</dialog>

<!-- Modal Impor Kontak -->
<dialog id="modal-import-contact" class="border-0 shadow-lg p-4 rounded-4" style="max-width: 520px; width: 100%; background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important;">
    <h5 class="fw-bold mb-3" style="color: var(--on-surface);">{{ __('contacts.import_modal_title') }}</h5>
    <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
        @csrf
        <p class="small mb-3" style="color: var(--on-surface-variant);">{{ __('contacts.import_instructions') }}</p>

        <div class="mb-3 p-3 rounded-4" style="background: var(--surface-container-low, #f3edf7); border: 1px solid var(--outline-variant, #cac4d0);">
            <label class="form-label fw-semibold small mb-2" style="color: var(--on-surface);">{{ __('contacts.import_file_label') }}</label>
            <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required class="form-control rounded-pill">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold small" style="color: var(--on-surface);">{{ __('contacts.assign_to_group') }}</label>
            <select name="group_id" class="form-select rounded-pill py-2 px-3" style="border-color: var(--outline-variant, #cac4d0);">
                <option value="">-- {{ __('contacts.no_group_assigned') }} --</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <a href="{{ route('contacts.template') }}" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="background: var(--surface-container, #ece6f0); color: var(--primary, #65558f);">
                <i class="fas fa-file-arrow-down"></i>
                <span>{{ __('contacts.import_template_download') }}</span>
            </a>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn rounded-pill px-3.5 py-2 fw-semibold" style="background: transparent; color: var(--on-surface-variant); border: 1px solid var(--outline-variant, #cac4d0);" onclick="closeModal('modal-import-contact')">{{ __('common.cancel') }}</button>
            <button type="submit" class="btn rounded-pill px-4 py-2 fw-semibold shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                <i class="fas fa-cloud-arrow-up me-1.5"></i>{{ __('contacts.start_import') }}
            </button>
        </div>
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
        document.getElementById('edit-contact-form').action = '{{ url("contacts") }}/' + contact.id;
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
