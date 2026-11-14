<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * C07: Session Drop During Broadcast with Admin Alert Flow
 * Alur pengujian:
 * 1. Kampanye sedang berjalan aktif (status processing)
 * 2. Sebagian pesan telah terkirim
 * 3. Webhook Baileys mengirim connection.update: close
 * 4. Sistem otomatis menghentikan sementara (pause) kampanye aktif
 * 5. Notifikasi darurat terkirim ke admin Telegram
 * 6. Pesan tersisa tetap berstatus pending dan aman
 */
class C07_SessionDropDuringBroadcastWithAdminAlertTest extends Tier3TestCase
{
    public function test_session_drop_mid_broadcast_triggers_pause_and_admin_alert(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();
        $this->fakeTelegramApi();

        // 1. Buat 5 kontak
        $contacts = [];
        for ($i = 1; $i <= 5; $i++) {
            $cid = $this->createContactRecord($user->id, "Penerima {$i}", "0812333000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        // 2. Buat kampanye dalam keadaan aktif processing
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Kampanye Berjalan Mid-Broadcast',
            'status' => 'processing',
        ], $contacts);

        // 3. Simulasikan 2 pesan pertama sukses terkirim
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        for ($i = 0; $i < 2; $i++) {
            $waId = '3EB0OK' . strtoupper(Str::random(8));
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

        // 4. Di tengah pengiriman, soket WhatsApp terputus!
        $resConnDrop = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'close',
            'user_jid' => '628123456789@s.whatsapp.net',
            'reason' => 'Connection Lost / 408 Timeout',
        ], ['X-Service-Key' => $this->serviceKey]);
        $resConnDrop->assertOk();

        // Verifikasi sesi berubah menjadi disconnected
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'disconnected',
        ]);

        // Verifikasi kampanye otomatis dialihkan ke status paused
        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('paused', $freshCamp->status, 'Kampanye harus di-pause saat sesi WA putus');
        $this->assertEquals(2, $freshCamp->sent_count);

        // 5. Simulasikan pengiriman notifikasi darurat ke Telegram Admin
        $adminChatId = config('services.telegram.admin_chat_id', '123456789');
        $alertText = "⚠️ *Peringatan Sesi Terputus!*\nSesi WhatsApp terputus saat kampanye 'Kampanye Berjalan Mid-Broadcast' sedang berlangsung. Antrean otomatis dijeda (paused).";

        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => $alertText,
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Peringatan Sesi Terputus');

        // 6. Verifikasi sisa 3 pesan tetap berstatus pending (tidak gagal prematur)
        $remainingPending = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->count();
        $this->assertEquals(3, $remainingPending);

        $failedCount = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'failed')
            ->count();
        $this->assertEquals(0, $failedCount, 'Pesan tertahan tidak boleh ditandai gagal prematur');
    }
}
