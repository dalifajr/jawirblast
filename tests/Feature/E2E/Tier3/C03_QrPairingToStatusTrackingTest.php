<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;

/**
 * C03: QR Pairing to Status Tracking Flow
 * Alur pengujian:
 * 1. Microservice Baileys dalam status QR_READY
 * 2. Mengambil QR code base64
 * 3. Menerima webhook connection.update (open)
 * 4. Sesi berubah menjadi connected
 * 5. Mengirim pesan dan menelusuri siklus status (sent -> delivered -> read)
 */
class C03_QrPairingToStatusTrackingTest extends Tier3TestCase
{
    public function test_qr_pairing_transition_to_full_message_tick_tracking(): void
    {
        $fakeBaileys = BaileysFake::create();
        $fakeBaileys->asQrReady('data:image/png;base64,mockCustomQrDataUrl12345')->register();

        // 1. Ambil QR Code dari port 3000
        $qrResponse = Http::get('http://127.0.0.1:3000/api/session/qr');
        $this->assertTrue($qrResponse->successful());
        $this->assertEquals('data:image/png;base64,mockCustomQrDataUrl12345', $qrResponse->json('qr'));

        // Status sesi awal di database belum connected
        $this->withDisconnectedWhatsAppSession();
        $this->assertDatabaseHas('whatsapp_sessions', ['status' => 'disconnected']);

        // 2. Simulasikan pengguna memindai QR -> Webhook connection.update: open
        $resConn = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => '628999888777@s.whatsapp.net',
        ], ['X-Service-Key' => $this->serviceKey]);
        $resConn->assertOk();

        // Verifikasi database whatsapp_sessions berubah menjadi connected
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
            'user_jid' => '628999888777@s.whatsapp.net',
        ]);

        // 3. Buat kampanye dan 1 pesan
        $user = $this->createTestUser();
        $cid = $this->createContactRecord($user->id, 'Target Pelanggan', '081234567890');
        $contact = DB::table('contacts')->where('id', $cid)->first();

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Tes Siklus Centang Lengkap',
            'status' => 'processing',
        ], [$contact]);

        $message = DB::table('campaign_messages')->where('campaign_id', $campaignId)->first();
        $waMessageId = '3EB0TESTQR' . rand(1000, 9999);

        DB::table('campaign_messages')->where('id', $message->id)->update([
            'baileys_msg_id' => $waMessageId,
        ]);

        // 4. Webhook 1: SENT (Centang 1)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMessageId,
            'remote_jid' => "{$contact->phone_number}@s.whatsapp.net",
            'status' => 'SENT',
            'status_code' => 2,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $freshMsg1 = DB::table('campaign_messages')->where('id', $message->id)->first();
        $this->assertEquals('sent', $freshMsg1->status);
        $this->assertNotNull($freshMsg1->sent_at);
        $this->assertNull($freshMsg1->delivered_at);

        // 5. Webhook 2: DELIVERED (Centang 2 Abu)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMessageId,
            'remote_jid' => "{$contact->phone_number}@s.whatsapp.net",
            'status' => 'DELIVERED',
            'status_code' => 3,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $freshMsg2 = DB::table('campaign_messages')->where('id', $message->id)->first();
        $this->assertEquals('delivered', $freshMsg2->status);
        $this->assertNotNull($freshMsg2->delivered_at);
        $this->assertNull($freshMsg2->read_at);

        // 6. Webhook 3: READ (Centang 2 Biru)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMessageId,
            'remote_jid' => "{$contact->phone_number}@s.whatsapp.net",
            'status' => 'READ',
            'status_code' => 4,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $freshMsg3 = DB::table('campaign_messages')->where('id', $message->id)->first();
        $this->assertEquals('read', $freshMsg3->status);
        $this->assertNotNull($freshMsg3->read_at);

        // 7. Verifikasi agregat kampanye akhir
        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(1, $freshCamp->sent_count);
        $this->assertEquals(1, $freshCamp->delivered_count);
        $this->assertEquals(1, $freshCamp->read_count);
        $this->assertEquals('completed', $freshCamp->status);
    }
}
