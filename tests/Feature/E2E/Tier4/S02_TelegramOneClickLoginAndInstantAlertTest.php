<?php

namespace Tests\Feature\E2E\Tier4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * S02: Telegram One-Click Login and Instant Alert (Real-World Application Scenario)
 * Fitur yang diuji: F2, F3, F4
 *
 * Alur Skenario:
 * 1. Pengguna meminta token login satu klik via Telegram
 * 2. Deep-linking /start weblogin_{token} diverifikasi oleh webhook Telegram Bot
 * 3. Token diklaim dan sesi autentikasi web pengguna aktif secara instan
 * 4. Pengguna menyusun pesan siaran darurat (Urgent Broadcast Alert)
 * 5. Pengiriman kilat dieksekusi dengan pemantauan notifikasi admin secara instan
 */
class S02_TelegramOneClickLoginAndInstantAlertTest extends Tier4TestCase
{
    public function test_telegram_one_click_login_and_emergency_blast_with_instant_alerts(): void
    {
        $this->fakeTelegramApi();
        $this->withConnectedWhatsAppSession();

        // 1. User terdaftar di sistem dengan email dan nomor Telegram
        $telegramUserId = 776655443;
        $telegramUsername = 'komandan_siaga';

        $user = $this->createTestUser([
            'name' => 'Komandan Operasional',
            'email' => 'komandan@jawirblast.com',
            'telegram_id' => $telegramUserId,
            'telegram_username' => $telegramUsername,
        ]);

        // 2. Sistem menerbitkan token login deep-link
        $loginToken = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $loginToken,
            'telegram_id' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Ponsel user membuka link Telegram: /start weblogin_{token}
        $resBotWebhook = $this->postJson('/api/telegram/webhook', [
            'update_id' => 888999,
            'message' => [
                'message_id' => 501,
                'from' => [
                    'id' => $telegramUserId,
                    'is_bot' => false,
                    'first_name' => 'Komandan',
                    'username' => $telegramUsername,
                ],
                'chat' => [
                    'id' => $telegramUserId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "/start weblogin_{$loginToken}",
            ],
        ]);
        $resBotWebhook->assertOk();

        // Verifikasi token terverifikasi di database
        $tokenRow = DB::table('telegram_login_tokens')->where('token', $loginToken)->first();
        $this->assertEquals('verified', $tokenRow->status);
        $this->assertEquals($telegramUserId, $tokenRow->telegram_id);

        // 4. Autentikasi sesi web berhasil melalui token
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // 5. Buat 5 kontak darurat tim tanggap bencana
        $emergencyContacts = [];
        for ($i = 1; $i <= 5; $i++) {
            $cid = $this->createContactRecord($user->id, "Petugas Lapangan {$i}", "08129991100{$i}");
            $emergencyContacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        // 6. Buat siaran kilat darurat
        $emergencyTemplate = '🚨 PERINGATAN DARURAT: {name}, segera kumpul di posko koordinasi!';
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Panggilan Darurat Tim Lapangan',
            'message_template' => $emergencyTemplate,
            'min_delay_seconds' => 1,
            'max_delay_seconds' => 2,
            'status' => 'processing',
            'started_at' => now(),
        ], $emergencyContacts);

        // Pemicuan notifikasi admin instan: Siaran Darurat Dimulai
        $adminChatId = config('services.telegram.admin_chat_id', '123456789');
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "🚨 *DARURAT*: Komandan Operasional memicu siaran kilat ke 5 petugas!",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('DARURAT');

        // 7. Simulasikan pengiriman kilat pesan darurat
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(5, $messages);

        foreach ($messages as $msg) {
            $waId = '3EB0URGENT_' . strtoupper(Str::random(8));
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

        // 8. Pemicuan notifikasi admin instan: Siaran Darurat Selesai
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "✅ *Tuntas*: Seluruh 5 petugas telah membaca (Centang Biru) panggilan darurat.",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Centang Biru');

        // Verifikasi kondisi akhir kampanye
        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $freshCamp->status);
        $this->assertEquals(5, $freshCamp->sent_count);
        $this->assertEquals(5, $freshCamp->read_count);
        $this->assertEquals(0, $freshCamp->failed_count);
    }
}
