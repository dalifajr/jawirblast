<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C01: Register to First Broadcast Flow
 * Alur pengujian:
 * 1. Registrasi user baru di sistem
 * 2. Login sesi web
 * 3. Tautkan sesi WhatsApp (status connected)
 * 4. Tambah kontak database
 * 5. Buat dan kirim kampanye broadcast
 * 6. Tangkap webhook centang pengiriman
 * 7. Verifikasi agregat akhir kampanye completed
 */
class C01_RegisterToFirstBroadcastFlowTest extends Tier3TestCase
{
    public function test_full_flow_from_registration_to_first_broadcast_completion(): void
    {
        // 1. Registrasi pengguna baru
        $user = $this->createTestUser([
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@jawirblast.com',
            'locale' => 'id',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'budi.santoso@jawirblast.com']);

        // 2. Simulasikan autentikasi user
        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // 3. Tautkan sesi WhatsApp (Connected)
        $session = $this->withConnectedWhatsAppSession('default', '628123456789@s.whatsapp.net');
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
        ]);

        // 4. Tambah 5 kontak ke database
        $contacts = [];
        for ($i = 1; $i <= 5; $i++) {
            $cid = $this->createContactRecord($user->id, "Kontak {$i}", "08120000000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }
        $this->assertCount(5, $contacts);
        $this->assertEquals(5, DB::table('contacts')->where('user_id', $user->id)->count());

        // 5. Buat kampanye broadcast
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Promo Perdana Pengguna Baru',
            'message_template' => 'Halo {name}, nikmati diskon khusus pelanggan baru!',
            'status' => 'draft',
        ], $contacts);

        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('draft', $campaign->status);
        $this->assertEquals(5, $campaign->total_contacts);
        $this->assertEquals(0, $campaign->sent_count);

        // 6. Mulai eksekusi antrean kampanye
        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        // Simulasikan Baileys mengirim pesan dan menerima ACK
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(5, $messages);

        foreach ($messages as $msg) {
            $waId = '3EB0' . strtoupper(Str::random(12));
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Tembakkan webhook DELIVERED (centang 2 abu-abu)
            $resDelivered = $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
                'timestamp' => (int) (microtime(true) * 1000),
            ], ['X-Service-Key' => $this->serviceKey]);
            $resDelivered->assertOk();

            // Tembakkan webhook READ (centang 2 biru)
            $resRead = $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
                'timestamp' => (int) (microtime(true) * 1000),
            ], ['X-Service-Key' => $this->serviceKey]);
            $resRead->assertOk();
        }

        // 7. Verifikasi agregat akhir kampanye di database
        $freshCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $freshCampaign->status);
        $this->assertEquals(5, $freshCampaign->sent_count);
        $this->assertEquals(5, $freshCampaign->delivered_count);
        $this->assertEquals(5, $freshCampaign->read_count);
        $this->assertEquals(0, $freshCampaign->failed_count);
        $this->assertNotNull($freshCampaign->completed_at);

        // Verifikasi webhook_logs mencatat 10 event (5 delivered + 5 read)
        $this->assertEquals(10, DB::table('webhook_logs')->where('event', 'message.update')->count());
    }
}
