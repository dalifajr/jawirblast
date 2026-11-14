<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C14: Auto Reconnect Resume Interrupted Queue Flow
 * Alur pengujian:
 * 1. Kampanye terinterupsi saat pengiriman sebagian pesan karena putus koneksi
 * 2. Status kampanye otomatis terhenti sementara (paused)
 * 3. Microservice Baileys melakukan auto-reconnect dan memancarkan connection.update: open
 * 4. Sistem melanjutkan antrean kampanye yang tertunda
 * 5. Memverifikasi pesan yang sudah terkirim tidak dikirim ulang (anti-duplikasi pesan terkirim)
 * 6. Menyelesaikan seluruh sisa pesan hingga tuntas 100%
 */
class C14_AutoReconnectResumeInterruptedQueueTest extends Tier3TestCase
{
    public function test_auto_reconnect_resumes_interrupted_broadcast_queue_without_duplicates(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Buat 8 kontak
        $contacts = [];
        for ($i = 1; $i <= 8; $i++) {
            $cid = $this->createContactRecord($user->id, "Penerima {$i}", "0812999000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Kampanye Uji Auto Reconnect Resume',
            'status' => 'processing',
            'started_at' => now(),
        ], $contacts);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(8, $messages);

        // 2. Simulasikan 3 pesan pertama sukses dikirim
        $dispatchedFirstBatchWaIds = [];
        for ($i = 0; $i < 3; $i++) {
            $waId = '3EB0REC_BATCH1_' . strtoupper(Str::random(6));
            $dispatchedFirstBatchWaIds[] = $waId;

            DB::table('campaign_messages')->where('id', $messages[$i]->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$messages[$i]->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        $this->assertEquals(3, DB::table('campaigns')->where('id', $campaignId)->value('sent_count'));

        // 3. Jaringan terputus (Disconnect Event)
        $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'close',
            'user_jid' => '628123456789@s.whatsapp.net',
            'reason' => 'Network Flap',
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        // Verifikasi kampanye paused dan sesi disconnected
        $this->assertDatabaseHas('whatsapp_sessions', ['status' => 'disconnected']);
        $this->assertDatabaseHas('campaigns', ['id' => $campaignId, 'status' => 'paused']);

        // 4. Microservice Baileys berhasil Reconnect (Open Event)
        $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => '628123456789@s.whatsapp.net',
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('whatsapp_sessions', ['status' => 'connected']);

        // Sistem / worker mendeteksi sesi pulih dan melanjutkan kampanye yang paused
        DB::table('campaigns')->where('id', $campaignId)->where('status', 'paused')->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);

        $resumedCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('processing', $resumedCamp->status);

        // 5. Verifikasi bahwa 3 pesan pertama TIDAK disentuh ulang (tidak ada duplikasi)
        foreach ($dispatchedFirstBatchWaIds as $waId) {
            $msgCheck = DB::table('campaign_messages')->where('baileys_msg_id', $waId)->first();
            $this->assertEquals('delivered', $msgCheck->status);
        }

        // 6. Eksekusi sisa 5 pesan pending
        $remainingPending = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->get();
        $this->assertCount(5, $remainingPending);

        foreach ($remainingPending as $msg) {
            $waId = '3EB0REC_BATCH2_' . strtoupper(Str::random(6));
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        // 7. Verifikasi akhir: semua 8 pesan tuntas, status kampanye completed
        $finalCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $finalCamp->status);
        $this->assertEquals(8, $finalCamp->total_contacts);
        $this->assertEquals(8, $finalCamp->sent_count);
        $this->assertEquals(0, $finalCamp->failed_count);
        $this->assertEquals(5, $finalCamp->read_count);
        $this->assertNotNull($finalCamp->completed_at);
    }
}
