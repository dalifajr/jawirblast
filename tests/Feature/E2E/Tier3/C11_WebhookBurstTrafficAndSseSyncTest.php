<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C11: Webhook Burst Traffic and SSE Sync Flow
 * Alur pengujian:
 * 1. Menjalankan pengiriman 20 pesan siaran
 * 2. Menerima lonjakan (burst) webhook update status secara simultan
 * 3. Memverifikasi integritas konkurensi counter agregasi di database
 * 4. Memverifikasi endpoint Server-Sent Events (SSE) menyalurkan payload sinkron
 */
class C11_WebhookBurstTrafficAndSseSyncTest extends Tier3TestCase
{
    public function test_webhook_burst_traffic_and_sse_live_synchronization(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Buat 20 kontak
        $contacts = [];
        for ($i = 1; $i <= 20; $i++) {
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $cid = $this->createContactRecord($user->id, "Burst Target {$i}", "081299988{$num}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Uji Lonjakan Webhook dan Sinkronisasi SSE',
            'status' => 'processing',
            'started_at' => now(),
        ], $contacts);

        // 2. Set seluruh 20 pesan menjadi sent dengan ID pesan unik
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(20, $messages);

        $waIds = [];
        foreach ($messages as $msg) {
            $waId = '3EB0BURST_' . strtoupper(Str::random(10));
            $waIds[$msg->id] = $waId;

            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        // 3. Simulasi Burst Traffic Webhook: 20 request delivered masuk berurutan cepat
        foreach ($messages as $msg) {
            $waId = $waIds[$msg->id];
            $resWebhook = $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
                'timestamp' => (int) (microtime(true) * 1000),
            ], ['X-Service-Key' => $this->serviceKey]);

            $resWebhook->assertOk();
        }

        // 4. Periksa integritas counter agregat di database
        $campAfterDelivered = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(20, $campAfterDelivered->total_contacts);
        $this->assertEquals(20, $campAfterDelivered->sent_count);
        $this->assertEquals(20, $campAfterDelivered->delivered_count);

        // 5. Konsumsi endpoint SSE Live Stream
        $sseResponse = $this->get("/campaigns/{$campaignId}/stream");
        $sseResponse->assertOk();
        $this->assertStringContainsString('text/event-stream', $sseResponse->headers->get('Content-Type'));

        $content = $sseResponse->streamedContent();
        $this->assertStringContainsString('data:', $content);
        $this->assertStringContainsString('"delivered_count":20', $content);
        $this->assertStringContainsString('"progress_percent":100', $content);

        // 6. Tembakkan lagi 10 webhook READ (centang biru parsial)
        for ($i = 0; $i < 10; $i++) {
            $msg = $messages[$i];
            $waId = $waIds[$msg->id];

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        $campAfterRead = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(20, $campAfterRead->delivered_count);
        $this->assertEquals(10, $campAfterRead->read_count);
        $this->assertEquals('completed', $campAfterRead->status);
    }
}
