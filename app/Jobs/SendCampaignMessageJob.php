<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Services\BaileysClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal sebelum job dicatat gagal di failed_jobs.
     */
    public int $tries = 3;

    /**
     * Timeout eksekusi per job (detik).
     */
    public int $timeout = 60;

    /**
     * ID atau model CampaignMessage yang akan dikirim.
     */
    public int|CampaignMessage $message;

    public function __construct(int|CampaignMessage $message)
    {
        $this->message = $message;
        $this->onQueue('broadcasts');
    }

    /**
     * Eksekusi job pengiriman pesan WhatsApp.
     * DILARANG KERAS MENGGUNAKAN sleep() / usleep() DI SINI.
     */
    public function handle(?BaileysClientService $client = null): void
    {
        $client = $client ?? app(BaileysClientService::class);

        $messageId = is_object($this->message) ? $this->message->id : (int) $this->message;
        $campaignMessage = CampaignMessage::find($messageId);

        // 1. Idempotensi: jika pesan tidak ditemukan atau status bukan pending, lewati
        if (!$campaignMessage || $campaignMessage->status !== 'pending') {
            return;
        }

        // 2. Baca status kampanye induk terkini langsung dari basis data
        $campaign = Campaign::find($campaignMessage->campaign_id);
        if (!$campaign) {
            return;
        }

        // Skenario A: Kampanye sedang di-PAUSE
        if ($campaign->isPaused()) {
            // Tunda job 10 detik tanpa memblokir thread worker PHP (anti-freeze)
            $this->release(10);
            return;
        }

        // Skenario B: Kampanye DIBATALKAN (cancelled/canceled) atau GAGAL (failed)
        if ($campaign->isCancelled() || $campaign->isFailed()) {
            $campaignMessage->update([
                'status' => 'failed',
                'error_message' => 'Campaign was cancelled by user',
                'updated_at' => now(),
            ]);
            $campaign->increment('failed_count');
            return;
        }

        // Skenario C: Kampanye masih berstatus draft atau scheduled, aktifkan ke processing
        if (in_array($campaign->status, ['draft', 'scheduled'])) {
            $campaign->update([
                'status' => 'processing',
                'started_at' => $campaign->started_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Eksekusi pengiriman pesan ke Baileys Microservice
        try {
            $response = $client->sendMessage(
                recipientId: $campaignMessage->contact_id,
                phone: $campaignMessage->phone_number,
                message: $campaignMessage->message_text,
                mediaPath: $campaign->media_path ?? null
            );

            if (!empty($response['success'])) {
                $waMessageId = $response['data']['wa_message_id']
                    ?? $response['data']['key']['id']
                    ?? ('3EB0' . strtoupper(Str::random(12)));

                $campaignMessage->update([
                    'status' => 'sent',
                    'baileys_msg_id' => $waMessageId,
                    'sent_at' => now(),
                    'error_message' => null,
                    'updated_at' => now(),
                ]);

                $campaign->increment('sent_count');
            } else {
                $errorMessage = $response['data']['message']
                    ?? 'Baileys Socket 503: WhatsApp session not connected';

                $campaignMessage->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'updated_at' => now(),
                ]);

                $campaign->increment('failed_count');
            }
        } catch (\Throwable $e) {
            $campaignMessage->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            $campaign->increment('failed_count');
        }

        // 4. Periksa penyelesaian kampanye: jika tidak ada lagi pesan pending, set completed
        $remainingPending = CampaignMessage::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($remainingPending === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
