<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\WhatsAppSession;
use App\Services\CampaignDispatchService;
use App\Services\SpintaxService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    /**
     * Menampilkan daftar kampanye milik pengguna aktif beserta ringkasan metrik.
     */
    public function index(Request $request): View
    {
        $userId = auth()->id();

        $query = Campaign::forUser($userId)->with('whatsAppSession');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $campaigns = $query->latest()->paginate(10)->withQueryString();

        // Kartu metrik agregat milik pengguna
        $userCampaigns = Campaign::forUser($userId);
        $stats = [
            'total_campaigns' => (clone $userCampaigns)->count(),
            'sent_messages' => (clone $userCampaigns)->sum('sent_count'),
            'read_messages' => (clone $userCampaigns)->sum('read_count'),
            'failed_messages' => (clone $userCampaigns)->sum('failed_count'),
        ];

        return view('campaigns.index', compact('campaigns', 'stats'));
    }

    /**
     * Form pembuatan kampanye baru.
     */
    public function create(): View
    {
        $userId = auth()->id();

        $session = WhatsAppSession::where('status', 'connected')->first();
        $groups = ContactGroup::where('user_id', $userId)->withCount('contacts')->get();
        $activeContactsCount = Contact::where('user_id', $userId)->active()->count();
        $sampleContacts = Contact::where('user_id', $userId)->active()->take(50)->get();

        return view('campaigns.create', compact('session', 'groups', 'activeContactsCount', 'sampleContacts'));
    }

    /**
     * Menyimpan dan (opsional) langsung memancarkan kampanye siaran.
     */
    public function store(
        Request $request,
        CampaignDispatchService $dispatchService,
        SpintaxService $spintaxService
    ): RedirectResponse {
        $userId = auth()->id();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message_template' => 'required|string',
            'min_delay_seconds' => 'nullable|integer|min:0',
            'max_delay_seconds' => 'nullable|integer|min:0',
            'target_type' => 'required|in:all,group,selected',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer',
            'action' => 'nullable|in:start_now,save_draft',
            'scheduled_at' => 'nullable|date',
        ]);

        $minDelay = (int) ($validated['min_delay_seconds'] ?? 2);
        $maxDelay = (int) ($validated['max_delay_seconds'] ?? 5);

        // Resolusi target kontak berdasarkan pilihan user
        $targetType = $validated['target_type'];
        $contacts = collect();

        if ($targetType === 'all') {
            $contacts = Contact::where('user_id', $userId)->active()->get();
        } elseif ($targetType === 'group' && !empty($validated['group_ids'])) {
            $groupIds = $validated['group_ids'];
            $contacts = Contact::where('user_id', $userId)
                ->active()
                ->whereHas('groups', fn ($q) => $q->whereIn('contact_groups.id', $groupIds))
                ->get();
        } elseif ($targetType === 'selected' && !empty($validated['contact_ids'])) {
            $contacts = Contact::where('user_id', $userId)
                ->active()
                ->whereIn('id', $validated['contact_ids'])
                ->get();
        }

        if ($contacts->isEmpty()) {
            return back()->withInput()->with('error', 'Pilih setidaknya 1 kontak target yang berstatus aktif.');
        }

        $session = WhatsAppSession::where('status', 'connected')->first();

        // Buat record kampanye
        $campaign = Campaign::create([
            'user_id' => $userId,
            'whats_app_session_id' => $session?->id,
            'title' => $validated['title'],
            'message_template' => $validated['message_template'],
            'status' => 'draft',
            'min_delay_seconds' => $minDelay,
            'max_delay_seconds' => $maxDelay,
            'total_contacts' => $contacts->count(),
            'scheduled_at' => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
        ]);

        // Buat record campaign_messages dengan status pending
        foreach ($contacts as $contact) {
            $resolvedMessage = $spintaxService->parse($campaign->message_template, $contact);

            CampaignMessage::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'phone_number' => $contact->phone_number ?? $contact->raw_phone,
                'recipient_name' => $contact->name,
                'message_text' => $resolvedMessage,
                'status' => 'pending',
            ]);
        }

        // Jika tombol aksi "Kirim Sekarang" ditekan
        if (($request->input('action') ?? 'start_now') === 'start_now') {
            $dispatchService->dispatch($campaign);
            return redirect()->route('campaigns.show', $campaign->id)
                ->with('success', 'Kampanye berhasil dibuat dan siaran sedang diproses di antrean!');
        }

        return redirect()->route('campaigns.show', $campaign->id)
            ->with('success', 'Draf kampanye berhasil disimpan!');
    }

    /**
     * Menampilkan detail progres kampanye, kartu metrik, dan log pesan terkirim.
     */
    public function show(Campaign $campaign, Request $request): View
    {
        if ($campaign->user_id !== auth()->id()) {
            abort(403, 'Akses ditolak.');
        }

        $messagesQuery = $campaign->messages();

        if ($request->filled('status') && $request->status !== 'all') {
            $messagesQuery->where('status', $request->status);
        }

        $messages = $messagesQuery->latest('id')->paginate(15)->withQueryString();

        return view('campaigns.show', compact('campaign', 'messages'));
    }

    /**
     * Memulai pengiriman kampanye berstatus draft/scheduled.
     */
    public function start(Campaign $campaign, CampaignDispatchService $dispatchService): RedirectResponse
    {
        if ($campaign->user_id !== auth()->id()) {
            abort(403);
        }

        if ($campaign->canBeDispatched()) {
            $dispatchService->dispatch($campaign);
            return back()->with('success', 'Siaran kampanye berhasil dimulai!');
        }

        return back()->with('error', 'Kampanye tidak dapat dimulai dari status saat ini.');
    }

    /**
     * Menjeda (pause) kampanye yang sedang processing.
     */
    public function pause(Campaign $campaign, CampaignDispatchService $dispatchService): RedirectResponse
    {
        if ($campaign->user_id !== auth()->id()) {
            abort(403);
        }

        if ($dispatchService->pauseCampaign($campaign)) {
            return back()->with('success', 'Siaran kampanye berhasil dijeda.');
        }

        return back()->with('error', 'Kampanye tidak dalam status berjalan.');
    }

    /**
     * Melanjutkan kembali (resume) kampanye yang terjeda.
     */
    public function resume(Campaign $campaign, CampaignDispatchService $dispatchService): RedirectResponse
    {
        if ($campaign->user_id !== auth()->id()) {
            abort(403);
        }

        if ($dispatchService->resumeCampaign($campaign)) {
            return back()->with('success', 'Siaran kampanye berhasil dilanjutkan.');
        }

        return back()->with('error', 'Kampanye tidak dalam status terjeda.');
    }

    /**
     * Membatalkan kampanye.
     */
    public function cancel(Campaign $campaign, CampaignDispatchService $dispatchService): RedirectResponse
    {
        if ($campaign->user_id !== auth()->id()) {
            abort(403);
        }

        if ($dispatchService->cancelCampaign($campaign)) {
            return back()->with('success', 'Kampanye berhasil dibatalkan.');
        }

        return back()->with('error', 'Kampanye tidak dapat dibatalkan.');
    }

    /**
     * Endpoint AJAX: Kalkulator & simulator live preview spintax.
     */
    public function previewSpintax(Request $request, SpintaxService $spintaxService): JsonResponse
    {
        $template = (string) $request->input('template', '');

        if (trim($template) === '') {
            return response()->json([
                'success' => true,
                'count' => 0,
                'samples' => [],
            ]);
        }

        $dummyVariables = [
            'name' => 'Budi Pratama',
            'nama' => 'Budi Pratama',
            'phone' => '6281234567890',
            'nomor' => '6281234567890',
        ];

        $count = $spintaxService->countVariations($template);
        $samples = $spintaxService->previewSamples($template, $dummyVariables, 5);

        return response()->json([
            'success' => true,
            'count' => $count,
            'samples' => $samples,
        ]);
    }

    /**
     * Endpoint AJAX: Polling status real-time untuk halaman detail.
     */
    public function status(Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'id' => $campaign->id,
            'status' => $campaign->status,
            'total_contacts' => $campaign->total_contacts,
            'sent_count' => $campaign->sent_count,
            'delivered_count' => $campaign->delivered_count,
            'read_count' => $campaign->read_count,
            'failed_count' => $campaign->failed_count,
            'progress_percent' => $campaign->progress_percent,
            'is_completed' => $campaign->isCompleted(),
            'is_paused' => $campaign->isPaused(),
            'is_cancelled' => $campaign->isCancelled(),
        ]);
    }
}
