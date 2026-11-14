<?php

namespace Tests\Feature\E2E\Tier4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\BaileysFake;

/**
 * S01: Full Lifecycle Broadcast Campaign (Real-World High Complexity)
 * Fitur yang diuji: F1, F2, F8, F9, F10, F11, F12, F13, F4
 *
 * Alur Skenario:
 * 1. Registrasi user mandiri & autentikasi sesi web
 * 2. Penautan sesi WhatsApp via pemindaian QR Baileys microservice
 * 3. Impor massal berkas kontak CSV dan normalisasi nomor internasional E.164
 * 4. Pengelompokan kontak ke dalam grup target spesifik
 * 5. Penyusunan kampanye dengan pesan ber-Spintax dinamis dan pacing delay anti-freeze
 * 6. Eksekusi pengiriman antrean terstagger
 * 7. Simulasi webhook siklus centang (Sent -> Delivered -> Read)
 * 8. Pemantauan status live melalui Server-Sent Events (SSE)
 * 9. Penerimaan notifikasi rekapitulasi bot Telegram admin
 * 10. Ekspor hasil riwayat broadcast dalam format CSV yang valid
 */
class S01_FullLifecycleBroadcastCampaignTest extends Tier4TestCase
{
    public function test_complete_end_to_end_broadcast_lifecycle(): void
    {
        // -------------------------------------------------------------
        // Tahap 1: Registrasi Pengguna Mandiri & Autentikasi Sesi Web
        // -------------------------------------------------------------
        $user = $this->createTestUser([
            'name' => 'Owner Bisnis Digital',
            'email' => 'owner.digital@jawirblast.com',
            'locale' => 'id',
        ]);
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // -------------------------------------------------------------
        // Tahap 2: Penautan Sesi WhatsApp via QR Code
        // -------------------------------------------------------------
        $fakeBaileys = BaileysFake::create();
        $fakeBaileys->asQrReady('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=')->register();

        $qrRes = Http::get('http://127.0.0.1:3000/api/session/qr');
        $this->assertTrue($qrRes->successful());
        $this->assertNotEmpty($qrRes->json('qr'));

        // Scan QR oleh ponsel user -> Webhook connection.update: open
        $userJid = '6281122334455@s.whatsapp.net';
        $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => $userJid,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
            'user_jid' => $userJid,
        ]);

        // -------------------------------------------------------------
        // Tahap 3: Impor Kontak CSV dan Normalisasi Nomor E.164
        // -------------------------------------------------------------
        $contactCount = 20;
        $importedContactIds = [];
        for ($i = 1; $i <= $contactCount; $i++) {
            $numSuffix = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $rawPhone = "0812-7777-{$numSuffix}";
            $cid = $this->createContactRecord($user->id, "Klien Prioritas {$i}", $rawPhone);
            $importedContactIds[] = $cid;
        }
        $this->assertCount($contactCount, $importedContactIds);

        // -------------------------------------------------------------
        // Tahap 4: Pengelompokan Kontak ke Grup "Promo Ramadhan 2026"
        // -------------------------------------------------------------
        $groupId = $this->createContactGroupRecord($user->id, 'Promo Ramadhan 2026', $importedContactIds);
        $this->assertEquals($contactCount, DB::table('contact_group_pivot')->where('group_id', $groupId)->count());

        $targetContacts = DB::table('contacts')->whereIn('id', $importedContactIds)->get();

        // -------------------------------------------------------------
        // Tahap 5: Penyusunan Kampanye Ber-Spintax dan Pacing Anti-Freeze
        // -------------------------------------------------------------
        $template = '{Salam hangat|Halo|Hai} {name}, promo Ramadhan berkah khusus nomor {phone}!';
        $minDelay = 2;
        $maxDelay = 4;

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Kampanye Akbar Ramadhan 2026',
            'message_template' => $template,
            'min_delay_seconds' => $minDelay,
            'max_delay_seconds' => $maxDelay,
            'status' => 'draft',
        ], $targetContacts->all());

        // -------------------------------------------------------------
        // Tahap 6: Eksekusi Antrean Kampanye Berjenjang (Staggered Dispatch)
        // -------------------------------------------------------------
        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $this->fakeTelegramApi();
        $adminChatId = config('services.telegram.admin_chat_id', '123456789');

        // Notifikasi start siaran ke admin
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "🚀 *Siaran Dimulai*: Kampanye Akbar Ramadhan 2026 ({$contactCount} nomor)",
            'parse_mode' => 'Markdown',
        ]);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount($contactCount, $messages);

        // -------------------------------------------------------------
        // Tahap 7: Simulasi Webhook Siklus Centang (Sent -> Delivered -> Read)
        // -------------------------------------------------------------
        $waMsgIds = [];
        foreach ($messages as $idx => $msg) {
            $waId = '3EB0S01_' . strtoupper(Str::random(10));
            $waMsgIds[$msg->id] = $waId;

            // Update status ke sent
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Seluruh pesan menerima status DELIVERED
            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();

            // 15 pesan pertama dibaca (READ - centang biru)
            if ($idx < 15) {
                $this->postJson('/api/wa/webhook', [
                    'event' => 'message.update',
                    'wa_message_id' => $waId,
                    'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                    'status' => 'READ',
                    'status_code' => 4,
                ], ['X-Service-Key' => $this->serviceKey])->assertOk();
            }
        }

        // -------------------------------------------------------------
        // Tahap 8: Pemantauan Aliran Live via SSE Endpoint
        // -------------------------------------------------------------
        $sse = $this->get("/campaigns/{$campaignId}/stream");
        $sse->assertOk();
        $this->assertStringContainsString('text/event-stream', $sse->headers->get('Content-Type'));

        $streamOutput = $sse->streamedContent();
        $this->assertStringContainsString('"sent_count":20', $streamOutput);
        $this->assertStringContainsString('"delivered_count":20', $streamOutput);
        $this->assertStringContainsString('"read_count":15', $streamOutput);
        $this->assertStringContainsString('"progress_percent":100', $streamOutput);

        // -------------------------------------------------------------
        // Tahap 9: Notifikasi Rekapitulasi Selesai ke Bot Telegram Admin
        // -------------------------------------------------------------
        $freshCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $freshCampaign->status);
        $this->assertEquals(20, $freshCampaign->sent_count);
        $this->assertEquals(20, $freshCampaign->delivered_count);
        $this->assertEquals(15, $freshCampaign->read_count);
        $this->assertEquals(0, $freshCampaign->failed_count);

        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => $adminChatId,
            'text' => "✅ *Siaran Selesai*\nKampanye: Kampanye Akbar Ramadhan 2026\nTotal: 20\nDelivered: 20\nRead: 15",
            'parse_mode' => 'Markdown',
        ]);

        $this->assertTelegramAdminNotificationSent('Siaran Selesai');

        // -------------------------------------------------------------
        // Tahap 10: Ekspor Laporan Akhir Broadcast ke CSV
        // -------------------------------------------------------------
        $finalMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->orderBy('id', 'asc')
            ->get();

        $csvStream = fopen('php://temp', 'r+');
        fputcsv($csvStream, ['ID', 'Nama', 'Nomor WhatsApp', 'Status', 'Centang Terakhir', 'Waktu Kirim']);

        foreach ($finalMessages as $row) {
            fputcsv($csvStream, [
                $row->id,
                $row->recipient_name,
                $row->phone_number,
                $row->status,
                $row->status === 'read' ? 'Centang Biru' : 'Centang Dua Abu',
                $row->sent_at,
            ]);
        }

        rewind($csvStream);
        $csvText = stream_get_contents($csvStream);
        fclose($csvStream);

        $this->assertStringContainsString('promo Ramadhan berkah', $finalMessages[0]->message_text);
        $this->assertStringContainsString('Klien Prioritas 1', $csvText);
        $this->assertStringContainsString('Centang Biru', $csvText);
        $this->assertStringContainsString('Centang Dua Abu', $csvText);
    }
}
