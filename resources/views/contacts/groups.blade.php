@extends('layouts.app')

@section('content')
<div class="responsive padding" style="max-width: 1000px; margin: 0 auto; width: 100%;">
    <!-- Header -->
    <header class="row middle-align margin-bottom">
        <div class="max">
            <h4 class="bold primary-text no-margin">{{ __('contacts.groups_title') }}</h4>
            <p class="text-secondary no-margin small">Kelola segmentasi pelanggan untuk penargetan kampanye broadcast yang presisi.</p>
        </div>
        <div>
            <a href="{{ route('contacts.index') }}" class="button border round">
                <i>arrow_back</i>
                <span>Kembali ke Kontak</span>
            </a>
        </div>
    </header>

    <!-- Form Buat Grup Baru -->
    <div class="card round surface border padding margin-bottom">
        <h6 class="bold margin-bottom">{{ __('contacts.create_group') }}</h6>
        <form method="POST" action="{{ route('contacts.groups.store') }}" class="row middle-align gap no-margin">
            @csrf
            <div class="field label border round max no-margin">
                <input type="text" name="name" required placeholder=" ">
                <label>{{ __('contacts.group_name') }} (contoh: Pelanggan VIP, Leads Webinar)</label>
            </div>
            <button type="submit" class="button primary round">
                <i>add</i>
                <span>Simpan Grup</span>
            </button>
        </form>
    </div>

    <!-- Table Daftar Grup -->
    <div class="card round surface border padding no-padding-horizontal">
        <div class="responsive">
            <table class="border no-border-left no-border-right">
                <thead>
                    <tr>
                        <th>{{ __('contacts.group_name') }}</th>
                        <th>{{ __('contacts.group_slug') }}</th>
                        <th>{{ __('contacts.total_members') }}</th>
                        <th>Dibuat Pada</th>
                        <th class="right-align">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td class="bold">{{ $group->name }}</td>
                            <td><code>{{ $group->slug }}</code></td>
                            <td>
                                <span class="badge primary-container round bold">
                                    {{ $group->contacts_count }} Anggota
                                </span>
                            </td>
                            <td class="text-secondary small">{{ $group->created_at?->format('d M Y, H:i') ?? '-' }}</td>
                            <td class="right-align">
                                <form method="POST" action="{{ route('contacts.groups.destroy', $group->id) }}" class="no-margin" onsubmit="return confirm('Apakah Anda yakin ingin menghapus grup ini? Kontak di dalamnya tidak akan terhapus.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button circle small transparent error-text" title="Hapus Grup">
                                        <i>delete</i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="center-align padding text-secondary">
                                <i style="font-size: 3rem;" class="margin-bottom">folder_off</i>
                                <p>Belum ada grup kontak yang dibuat. Silakan buat grup baru di atas.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="padding-horizontal margin-top">
            {{ $groups->links() }}
        </div>
    </div>
</div>
@endsection
