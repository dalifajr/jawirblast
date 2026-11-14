<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Tampilkan daftar kontak pengguna dengan pencarian, filter grup, dan filter status.
     */
    public function index(Request $request): View|JsonResponse
    {
        $userId = auth()->id();
        $query = Contact::with('groups')->where('user_id', $userId);

        if ($request->filled('search') && is_string($request->input('search'))) {
            $query->search(trim($request->input('search')));
        }

        if ($request->filled('group_id')) {
            $groupId = (int) $request->input('group_id');
            $query->whereHas('groups', function ($q) use ($groupId) {
                $q->where('contact_groups.id', $groupId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $contacts = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $groups = ContactGroup::where('user_id', $userId)->withCount('contacts')->orderBy('name')->get();

        $stats = [
            'total' => Contact::where('user_id', $userId)->count(),
            'active' => Contact::where('user_id', $userId)->where('status', 'active')->count(),
            'invalid' => Contact::where('user_id', $userId)->where('status', 'invalid')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json($contacts);
        }

        return view('contacts.index', compact('contacts', 'groups', 'stats'));
    }

    /**
     * Simpan kontak baru ke database dengan normalisasi nomor E.164.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:35'],
            'group_id' => ['nullable', 'integer'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer'],
            'custom_fields' => ['nullable'],
        ]);

        $userId = auth()->id() ?? $request->input('user_id');
        $rawPhone = mb_substr((string) $request->input('phone_number'), 0, 35);
        $parsed = PhoneNumberNormalizer::parse($rawPhone);

        $customFields = $request->input('custom_fields');
        if (is_string($customFields)) {
            $decoded = json_decode($customFields, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $customFields = $decoded;
            }
        }

        if ($parsed['isValid']) {
            $cleanPhone = mb_substr($parsed['formatted'], 0, 25);
            $contact = Contact::updateOrCreate(
                [
                    'user_id' => $userId,
                    'phone_number' => $cleanPhone,
                ],
                [
                    'name' => $request->input('name'),
                    'raw_phone' => $rawPhone,
                    'custom_fields' => $customFields,
                    'status' => 'active',
                ]
            );
        } else {
            $cleanPhone = 'INVALID';
            $contact = Contact::create([
                'user_id' => $userId,
                'name' => $request->input('name'),
                'phone_number' => $cleanPhone,
                'raw_phone' => $rawPhone,
                'custom_fields' => $customFields,
                'status' => 'invalid',
            ]);
        }

        // Hubungkan ke grup jika ada parameter group_id atau group_ids (validasi otorisasi kepemilikan grup)
        $groupIds = [];
        if ($request->filled('group_id')) {
            $groupIds[] = (int) $request->input('group_id');
        }
        if ($request->filled('group_ids') && is_array($request->input('group_ids'))) {
            $groupIds = array_merge($groupIds, array_map('intval', $request->input('group_ids')));
        }
        if (!empty($groupIds)) {
            $validGroupIds = ContactGroup::where('user_id', $userId)
                ->whereIn('id', array_unique($groupIds))
                ->pluck('id')
                ->all();
            if (!empty($validGroupIds)) {
                $contact->groups()->syncWithoutDetaching($validGroupIds);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $contact->id,
                'phone_number' => $contact->phone_number,
                'contact' => $contact,
            ], 201);
        }

        return redirect()->route('contacts.index')->with('success', 'Kontak berhasil disimpan.');
    }

    /**
     * Perbarui data kontak, nomor telepon, dan custom_fields JSON.
     */
    public function update(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:35'],
            'status' => ['sometimes', 'in:active,invalid,unsubscribed'],
            'custom_fields' => ['sometimes', 'nullable'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer'],
        ]);

        $userId = auth()->id() ?? $request->input('user_id');
        $contactQuery = Contact::where('id', $id);
        if ($userId) {
            $contactQuery->where('user_id', $userId);
        }
        $contact = $contactQuery->firstOrFail();

        if ($request->has('name')) {
            $contact->name = (string) $request->input('name');
        }

        if ($request->has('phone_number')) {
            $rawPhone = mb_substr((string) $request->input('phone_number'), 0, 35);
            $parsed = PhoneNumberNormalizer::parse($rawPhone);
            $contact->raw_phone = $rawPhone;
            if ($parsed['isValid']) {
                $contact->phone_number = mb_substr($parsed['formatted'], 0, 25);
                if ($contact->status === 'invalid') {
                    $contact->status = 'active';
                }
            } else {
                $contact->phone_number = 'INVALID';
                $contact->status = 'invalid';
            }
        }

        if ($request->has('status')) {
            $contact->status = $request->input('status');
        }

        if ($request->has('custom_fields')) {
            $cf = $request->input('custom_fields');
            if (is_string($cf)) {
                $decoded = json_decode($cf, true);
                $contact->custom_fields = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $cf;
            } else {
                $contact->custom_fields = $cf;
            }
        }

        $contact->save();

        if ($request->has('group_ids')) {
            $requestedGroupIds = is_array($request->input('group_ids')) ? array_map('intval', $request->input('group_ids')) : [];
            $validGroupIds = !empty($requestedGroupIds)
                ? ContactGroup::where('user_id', $contact->user_id)->whereIn('id', $requestedGroupIds)->pluck('id')->all()
                : [];
            $contact->groups()->sync($validGroupIds);
        } elseif ($request->has('group_id')) {
            $gId = $request->filled('group_id') ? (int) $request->input('group_id') : null;
            $validGroupIds = [];
            if ($gId !== null) {
                $exists = ContactGroup::where('user_id', $contact->user_id)->where('id', $gId)->exists();
                if ($exists) {
                    $validGroupIds = [$gId];
                }
            }
            $contact->groups()->sync($validGroupIds);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'contact' => $contact,
            ], 200);
        }

        return redirect()->back()->with('success', 'Kontak berhasil diperbarui.');
    }

    /**
     * Hapus kontak dari database. Relasi pivot terhapus otomatis via foreign key cascade.
     */
    public function destroy(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $userId = auth()->id();
        $query = Contact::where('id', $id);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $contact = $query->firstOrFail();
        $contact->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true], 200);
        }

        return redirect()->back()->with('success', 'Kontak berhasil dihapus.');
    }

    /**
     * Hapus massal beberapa kontak terpilih.
     */
    public function batchDestroy(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $userId = auth()->id();
        Contact::where('user_id', $userId)
            ->whereIn('id', $request->input('ids'))
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true], 200);
        }

        return redirect()->back()->with('success', 'Kontak terpilih berhasil dihapus.');
    }
}
