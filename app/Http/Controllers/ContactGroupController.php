<?php

namespace App\Http\Controllers;

use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactGroupController extends Controller
{
    /**
     * Tampilkan daftar grup kontak milik pengguna beserta jumlah anggotanya.
     */
    public function index(Request $request): View|JsonResponse
    {
        $userId = auth()->id();
        $groups = ContactGroup::where('user_id', $userId)
            ->withCount('contacts')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($groups);
        }

        return view('contacts.groups', compact('groups'));
    }

    /**
     * Simpan grup kontak baru.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = auth()->id() ?? $request->input('user_id');

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        // Pastikan slug unik per pengguna jika perlu
        $originalSlug = $slug;
        $counter = 1;
        while (ContactGroup::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $group = ContactGroup::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'group' => $group,
            ], 201);
        }

        return redirect()->route('contacts.groups.index')->with('success', 'Grup kontak berhasil dibuat.');
    }

    /**
     * Hapus grup kontak (kontak di dalamnya tidak dihapus).
     */
    public function destroy(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $userId = auth()->id();
        $query = ContactGroup::where('id', $id);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $group = $query->firstOrFail();

        $group->contacts()->detach();
        $group->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true], 200);
        }

        return redirect()->route('contacts.groups.index')->with('success', 'Grup kontak berhasil dihapus.');
    }
}
