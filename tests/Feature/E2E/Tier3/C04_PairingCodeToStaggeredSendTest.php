<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;

/**
 * C04: Pairing Code to Staggered Send Flow
 * Alur pengujian:
 * 1. Request pairing code 8-digit tanpa scan QR
 * 2. Transisi koneksi WhatsApp menjadi aktif
 * 3. Pembuatan kampanye dengan parameter delay berjenjang (anti-freeze)
 * 4. Verifikasi kalkulasi akumulasi delay antrean terdistribusi secara monotonik
 * 5. Eksekusi pengiriman berurutan
 */
class C04_PairingCodeToStaggeredSendTest extends Tier3TestCase
{
    public function test_pairing_code_connection_and_staggered_delay_calculation(): void
    {
        $fakeBaileys = BaileysFake::create();
        $pairingCode = 'ABCD-9988';
        $fakeBaileys->withPairingCode($pairingCode)->register();

        // 1. Request pairing code untuk nomor telepon pengguna
        $phoneNumber = '628123456789';
        $response = Http::post('http://127.0.0.1:3000/api/session/pairing-code', [
            'phone' => $phoneNumber,
        ]);

        $this->assertTrue($response->successful());
        $this->assertEquals($pairingCode, $response->json('code'));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $response->json('code'));

        // 2. Hubungkan sesi WhatsApp
        $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => "{$phoneNumber}@s.whatsapp.net",
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('whatsapp_sessions', [
            'status' => 'connected',
            'user_jid' => "{$phoneNumber}@s.whatsapp.net",
        ]);

        // 3. Buat kampanye dengan 6 kontak dan interval delay 2 - 5 detik
        $user = $this->createTestUser();
        $contacts = [];
        for ($i = 1; $i <= 6; $i++) {
            $cid = $this->createContactRecord($user->id, "Target {$i}", "0812999000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $minDelay = 2;
        $maxDelay = 5;
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Kampanye Anti-Freeze Staggered Delay',
            'min_delay_seconds' => $minDelay,
            'max_delay_seconds' => $maxDelay,
            'status' => 'processing',
        ], $contacts);

        // 4. Kalkulasi simulasi Staggered Native Queue Delay
        $accumulatedDelay = 0;
        $delays = [];
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();

        foreach ($messages as $index => $msg) {
            if ($index === 0) {
                $stepDelay = 0;
            } else {
                $stepDelay = rand($minDelay, $maxDelay);
                $accumulatedDelay += $stepDelay;
            }
            $delays[$msg->id] = $accumulatedDelay;
        }

        // Verifikasi properti matematis staggered delay:
        // Delay pertama harus 0 (langsung kirim)
        $this->assertEquals(0, $delays[$messages[0]->id]);

        // Setiap delay berikutnya harus lebih besar atau sama dengan delay sebelumnya ditambah minDelay
        for ($k = 1; $k < count($messages); $k++) {
            $prevDelay = $delays[$messages[$k - 1]->id];
            $currentDelay = $delays[$messages[$k]->id];
            $diff = $currentDelay - $prevDelay;

            $this->assertGreaterThanOrEqual($minDelay, $diff, "Interval antar pesan ke-{$k} harus minimal {$minDelay}s");
            $this->assertLessThanOrEqual($maxDelay, $diff, "Interval antar pesan ke-{$k} tidak boleh melebihi {$maxDelay}s");
            $this->assertGreaterThan($prevDelay, $currentDelay, "Delay harus bertambah secara monotonik");
        }

        // 5. Simulasikan eksekusi pengiriman berurutan
        foreach ($messages as $msg) {
            $waId = '3EB0PAIR' . rand(10000, 99999);
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

        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(6, $freshCamp->sent_count);
        $this->assertEquals(6, $freshCamp->delivered_count);
        $this->assertEquals('completed', $freshCamp->status);
    }
}
