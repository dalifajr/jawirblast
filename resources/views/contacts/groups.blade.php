@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4" style="max-width: 1000px; margin: 0 auto;">
    <!-- Header & Back Button -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--on-surface);">{{ __('contacts.groups_title') }}</h4>
            <p class="mb-0" style="color: var(--on-surface-variant); font-size: 0.875rem;">Kelola segmentasi pelanggan untuk penargetan kampanye broadcast yang presisi.</p>
        </div>
        <div>
            <a href="{{ route('contacts.index') }}" class="btn rounded-pill px-3.5 py-2 fw-semibold d-inline-flex align-items-center gap-2" style="background: var(--surface-container-high, #e6e0e9); color: var(--on-surface, #1d1b20); border: 1px solid var(--outline-variant, #cac4d0);">
                <i class="fas fa-arrow-left"></i>
                <span>{{ __('contacts.back_to_contacts') }}</span>
            </a>
        </div>
    </div>

    <!-- Form Buat Grup Baru -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <h6 class="fw-bold mb-3" style="color: var(--primary, #65558f);">{{ __('contacts.create_group') }}</h6>
        <form method="POST" action="{{ route('contacts.groups.store') }}" class="row g-2 align-items-center">
            @csrf
            <div class="col-12 col-md">
                <div class="input-group">
                    <span class="input-group-text rounded-start-pill ps-3 pe-2 bg-transparent" style="border-color: var(--outline-variant, #cac4d0); color: var(--on-surface-variant);"><i class="fas fa-tag"></i></span>
                    <input type="text" name="name" required class="form-control rounded-end-pill py-2.5" placeholder="{{ __('contacts.group_name_placeholder') }}" style="border-color: var(--outline-variant, #cac4d0);">
                </div>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn rounded-pill px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" style="background: var(--primary, #65558f); color: #fff;">
                    <i class="fas fa-plus"></i>
                    <span>{{ __('contacts.save_group') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Table Daftar Grup -->
    <div class="card border-0 shadow-sm p-4" style="background: var(--surface-container-lowest, #fff); border: 1px solid var(--outline-variant, #cac4d0) !important; border-radius: 28px;">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr style="border-bottom: 2px solid var(--outline-variant, #cac4d0); color: var(--on-surface-variant); font-size: 0.8125rem;">
                        <th class="pb-3">{{ __('contacts.group_name') }}</th>
                        <th class="pb-3">{{ __('contacts.group_slug') }}</th>
                        <th class="pb-3">{{ __('contacts.total_members') }}</th>
                        <th class="pb-3">{{ __('common.created_at') }}</th>
                        <th class="pb-3 text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr style="border-bottom: 1px solid var(--outline-variant, #cac4d0);">
                            <td class="py-3 fw-bold" style="color: var(--on-surface);">{{ $group->name }}</td>
                            <td class="py-3">
                                <code class="px-2 py-1 rounded" style="background: var(--surface-container, #ece6f0); color: var(--primary, #65558f);">{{ $group->slug }}</code>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background: var(--secondary-container, #e8def8); color: var(--on-secondary-container, #1d192b);">
                                    <i class="fas fa-user-group me-1"></i>{{ __('contacts.members_count', ['count' => $group->contacts_count]) }}
                                </span>
                            </td>
                            <td class="py-3 small" style="color: var(--on-surface-variant); font-size: 0.8125rem;">
                                {{ $group->created_at?->format('d M Y, H:i') ?? '-' }}
                            </td>
                            <td class="py-3 text-end">
                                <form method="POST" action="{{ route('contacts.groups.destroy', $group->id) }}" class="d-inline m-0" onsubmit="return confirm('{{ __('contacts.confirm_delete_group') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #fde8e8; color: #b91c1c;" title="{{ __('common.delete') }}">
                                        <i class="fas fa-trash-can" style="font-size: 0.75rem;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5" style="color: var(--on-surface-variant);">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: var(--surface-container, #ece6f0); color: var(--on-surface-variant);">
                                    <i class="fas fa-folder-open fs-3"></i>
                                </div>
                                <p class="mb-0">{{ __('contacts.no_groups') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $groups->links() }}
        </div>
    </div>
</div>
@endsection
