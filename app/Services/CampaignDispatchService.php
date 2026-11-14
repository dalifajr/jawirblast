<?php

namespace App\Services;

use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Support\Collection;

class CampaignDispatchService
{
    public function __construct(
        protected SpintaxService $spintaxService
    ) {}

    /**
     * Menghitung jeda antrean terstagger secara akumulatif.
     * Dilengkapi proteksi auto-swap min/max dan penanganan zero delay.
     */
    public function calculateStaggeredDelays(int $contactCount, int $minDelay, int $maxDelay): array
    {
        // Proteksi: jika min_delay > max_delay, tukar posisinya agar mt_rand tidak melempar error
        if ($minDelay > $maxDelay) {
            $temp = $minDelay;
            $minDelay = $maxDelay;
            $maxDelay = $temp;
        }

        $delays = [];
        $accumulated = 0;

        for ($i = 0; $i < $contactCount; $i++) {
            if ($i === 0) {
                $delays[] = 0; // Pesan perdana dikirim segera tanpa jeda
            } else {
                $step = ($minDelay === $maxDelay) ? $minDelay : mt_rand($minDelay, $maxDelay);
                $accumulated += $step;
                $delays[] = $accumulated;
            }
        }

        return $delays;
    }

    /**
     * Memancarkan antrean pesan kampanye ke worker native Laravel queue.
     * Menerapkan staggered delay tanpa sleep().
     */
    public function dispatch(Campaign|int $campaign, ?Collection $contacts = null): Campaign
    {
        $campaignModel = is_object($campaign) ? $campaign : Campaign::findOrFail($campaign);

        // Jika daftar kontak diberikan dan pesan belum dibuat, buat baris pesan pending
        if ($contacts !== null && $contacts->isNotEmpty()) {
            foreach ($contacts as $contact) {
                $resolvedMessage = $this->spintaxService->parse(
                    $campaignModel->message_template,
                    $contact
                );

                $phone = $contact->phone_number ?? $contact->raw_phone ?? '';
                $name = $contact->name ?? '';

                CampaignMessage::create([
                    'campaign_id' => $campaignModel->id,
                    'contact_id' => $contact->id ?? null,
                    'phone_number' => $phone,
                    'recipient_name' => $name,
                    'message_text' => $resolvedMessage,
                    'status' => 'pending',
                ]);
            }

            $campaignModel->update([
                'total_contacts' => $campaignModel->messages()->count(),
            ]);
        }

        // Ambil semua pesan berstatus pending yang perlu dikirim
        $pendingMessages = $campaignModel->messages()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        if ($pendingMessages->isEmpty()) {
            return $campaignModel;
        }

        // Hitung interval staggered delay untuk seluruh pesan pending
        $delays = $this->calculateStaggeredDelays(
            $pendingMessages->count(),
            (int) $campaignModel->min_delay_seconds,
            (int) $campaignModel->max_delay_seconds
        );

        // Jadwalkan setiap pesan ke antrean dengan native delayed dispatch
        foreach ($pendingMessages as $idx => $message) {
            $delaySeconds = $delays[$idx] ?? 0;
            SendCampaignMessageJob::dispatch($message->id)
                ->delay(now()->addSeconds($delaySeconds))
                ->onQueue('broadcasts');
        }

        // Perbarui status kampanye ke processing
        $campaignModel->update([
            'status' => 'processing',
            'started_at' => $campaignModel->started_at ?? now(),
            'updated_at' => now(),
        ]);

        return $campaignModel;
    }

    /**
     * Menjeda (pause) kampanye yang sedang berjalan.
     */
    public function pauseCampaign(Campaign|int $campaign): bool
    {
        $campaignModel = is_object($campaign) ? $campaign : Campaign::findOrFail($campaign);

        if ($campaignModel->isProcessing()) {
            $campaignModel->update([
                'status' => 'paused',
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Melanjutkan kembali (resume) kampanye yang terjeda.
     */
    public function resumeCampaign(Campaign|int $campaign): bool
    {
        $campaignModel = is_object($campaign) ? $campaign : Campaign::findOrFail($campaign);

        if ($campaignModel->isPaused()) {
            $campaignModel->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Membatalkan kampanye dan menandai pesan tersisa sebagai failed.
     */
    public function cancelCampaign(Campaign|int $campaign): bool
    {
        $campaignModel = is_object($campaign) ? $campaign : Campaign::findOrFail($campaign);

        if ($campaignModel->canBeCancelled()) {
            $campaignModel->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            // Batalkan semua pesan pending yang tersisa
            $cancelledCount = CampaignMessage::where('campaign_id', $campaignModel->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Campaign was cancelled by user',
                    'updated_at' => now(),
                ]);

            if ($cancelledCount > 0) {
                $campaignModel->increment('failed_count', $cancelledCount);
            }

            return true;
        }

        return false;
    }
}
