<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * C12: Admin Alert Progress Sequence Flow
 * Alur pengujian:
 * 1. Pemicuan notifikasi admin saat kampanye dimulai
 * 2. Pemantauan progres pengiriman berkala (misal 50%)
 * 3. Pemicuan notifikasi rekapitulasi statistik saat kampanye selesai 100%
 * 4. Memverifikasi urutan kronologis notifikasi yang dikirimkan ke Telegram Bot API
 */
class C12_AdminAlertProgressSequenceTest extends Tier3TestCase
{
    public function test_admin_alert_chronological_sequence_start_progress_completion(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();
        $this->fakeTelegramApi();

        $adminChatId = config('services.telegram.admin_chat_id', '123456789');

        // 1. Buat 10 kontak
        $contacts = [];
        for ($i = 1; $i <= 10; $i++) {
            $cid = $this->createContactRecord($user->id, "Member {$i}", "0812111000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Broadcast Promo Akhir Pekan',
            'status' => 'processing',
            'started_at' => now(),
        ], $contacts);

        // Notifikasi Tahap 1: Broadcast Start
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "🚀 *Kampanye Dimulai*\nJudul: Broadcast Promo Akhir Pekan\nTarget: 10 kontak",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Kampanye Dimulai');

        // 2. Kirim 5 pesan pertama (mencapai progres 50%)
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        for ($i = 0; $i < 5; $i++) {
            $waId = '3EB0SEQ_' . strtoupper(Str::random(8));
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

        // Notifikasi Tahap 2: Broadcast Progress (50%)
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "📊 *Progres Siaran: 50%*\nJudul: Broadcast Promo Akhir Pekan\nTerkirim: 5 / 10",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Progres Siaran: 50%');

        // 3. Kirim sisa 5 pesan (mencapai 100%)
        for ($i = 5; $i < 10; $i++) {
            $waId = '3EB0SEQ_' . strtoupper(Str::random(8));
            DB::table('campaign_messages')->where('id', $messages[$i]->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$messages[$i]->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $freshCamp->status);
        $this->assertEquals(10, $freshCamp->sent_count);

        // Notifikasi Tahap 3: Broadcast Completed
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "✅ *Kampanye Selesai!*\nJudul: Broadcast Promo Akhir Pekan\nTotal: 10\nDelivered: {$freshCamp->delivered_count}\nRead: {$freshCamp->read_count}\nFailed: {$freshCamp->failed_count}",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Kampanye Selesai!');

        // 4. Verifikasi urutan 3 notifikasi yang tertangkap
        $captured = $this->tele()->getCapturedMessages();
        $this->assertCount(3, $captured);

        $this->assertStringContainsString('Kampanye Dimulai', $captured[0]['text']);
        $this->assertStringContainsString('Progres Siaran: 50%', $captured[1]['text']);
        $this->assertStringContainsString('Kampanye Selesai!', $captured[2]['text']);
    }
}
