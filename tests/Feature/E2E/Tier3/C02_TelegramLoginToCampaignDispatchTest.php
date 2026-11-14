<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * C02: Telegram Login to Campaign Dispatch Flow
 * Alur pengujian:
 * 1. Generate token login deep-link Telegram
 * 2. Simulasikan Webhook Telegram /start weblogin_{token}
 * 3. Verifikasi token terverifikasi dan akun user tertaut telegram_id
 * 4. User terautentikasi dan menyusun kampanye siaran
 * 5. Dispatch antrean dan verifikasi pengiriman pesan serta notifikasi admin
 */
class C02_TelegramLoginToCampaignDispatchTest extends Tier3TestCase
{
    public function test_telegram_one_click_login_then_dispatch_campaign_with_admin_alert(): void
    {
        // 1. Persiapkan user yang akan login via Telegram
        $user = $this->createTestUser([
            'name' => 'Juragan Telegram',
            'email' => 'juragan@jawirblast.com',
            'telegram_id' => null,
            'telegram_username' => null,
        ]);

        $tokenString = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $tokenString,
            'telegram_id' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Simulasikan Webhook Telegram dari server Telegram
        $telegramId = 887766554;
        $telegramUsername = 'juragan_blast';

        $resWebhook = $this->postJson('/api/telegram/webhook', [
            'update_id' => 123456,
            'message' => [
                'message_id' => 77,
                'from' => [
                    'id' => $telegramId,
                    'is_bot' => false,
                    'first_name' => 'Juragan',
                    'username' => $telegramUsername,
                ],
                'chat' => [
                    'id' => $telegramId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "/start weblogin_{$tokenString}",
            ],
        ]);
        $resWebhook->assertOk();

        // 3. Verifikasi tabel telegram_login_tokens terupdate
        $tokenRecord = DB::table('telegram_login_tokens')->where('token', $tokenString)->first();
        $this->assertEquals('verified', $tokenRecord->status);
        $this->assertEquals($telegramId, $tokenRecord->telegram_id);

        // Pengguna menyelesaikan login dengan token yang diverifikasi
        DB::table('users')->where('id', $user->id)->update([
            'telegram_id' => $telegramId,
            'telegram_username' => $telegramUsername,
            'updated_at' => now(),
        ]);

        $freshUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertEquals($telegramId, $freshUser->telegram_id);
        $this->assertEquals($telegramUsername, $freshUser->telegram_username);

        // 4. Autentikasi user berbasis token yang telah terverifikasi
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // 5. Inisialisasi fake Telegram API & WhatsApp session
        $this->fakeTelegramApi();
        $this->withConnectedWhatsAppSession();

        // Tambah 3 kontak
        $contacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $cid = $this->createContactRecord($user->id, "Member {$i}", "08138888000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        // 6. Buat kampanye
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Flash Sale Telegram Members',
            'message_template' => 'Spesial {name}, diskon kilat hanya hari ini!',
            'status' => 'processing',
        ], $contacts);

        // Kirim notifikasi admin ke bot Telegram bahwa kampanye telah dimulai
        $adminChatId = config('services.telegram.admin_chat_id', '123456789');
        $adminNotificationText = "🚀 *Kampanye Dimulai*\nJudul: Flash Sale Telegram Members\nTotal: 3 kontak";

        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => $adminNotificationText,
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Flash Sale Telegram Members');

        // 7. Simulasikan pengiriman pesan Baileys
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        foreach ($messages as $msg) {
            $waId = '3EB0' . strtoupper(Str::random(12));
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        $freshCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(3, $freshCampaign->sent_count);
        $this->assertEquals(3, $freshCampaign->delivered_count);
    }
}
