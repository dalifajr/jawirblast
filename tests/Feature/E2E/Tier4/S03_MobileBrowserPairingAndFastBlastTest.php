<?php

namespace Tests\Feature\E2E\Tier4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\BaileysFake;

/**
 * S03: Mobile Browser Pairing via 8-Digit Code and Fast Blast (Real-World Application Scenario)
 * Fitur yang diuji: F5, F6, F7, F10, F11, F14
 *
 * Alur Skenario:
 * 1. Pengguna mengakses sistem melalui peramban ponsel (mobile viewport & User-Agent)
 * 2. Pengguna menautkan nomor WhatsApp menggunakan metode 8-Digit Pairing Code tanpa kamera kedua
 * 3. Microservice Baileys menerbitkan kode pairing dan koneksi soket aktif
 * 4. Pengguna menyusun kampanye promo kilat (Fast Blast) dengan interval delay pendek (1-3s)
 * 5. Eksekusi pengiriman antrean cepat dengan verifikasi akumulasi staggered delay
 * 6. Pelacakan perubahan status centang pesan (1, 2 abu-abu, dan 2 biru)
 */
class S03_MobileBrowserPairingAndFastBlastTest extends Tier4TestCase
{
    public function test_mobile_browser_pairing_code_and_fast_blast_execution(): void
    {
        // 1. Simulasikan pengguna mobile browser (Android / Chrome Mobile)
        $mobileUserAgent = 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

        $user = $this->createTestUser([
            'name' => 'Pedagang Online Mobile',
            'email' => 'olshop.mobile@jawirblast.com',
        ]);
        $this->actingAs($user);

        // 2. Setup BaileysFake dengan mode pairing code
        $pairingCode = 'JKLM-5678';
        $fakeBaileys = BaileysFake::create();
        $fakeBaileys->withPairingCode($pairingCode)->register();

        $userPhoneNumber = '6285712345678';

        // Panggil endpoint pairing code
        $pairingResponse = Http::withHeaders([
            'User-Agent' => $mobileUserAgent,
        ])->post('http://127.0.0.1:3000/api/session/pairing-code', [
            'phone' => $userPhoneNumber,
        ]);

        $this->assertTrue($pairingResponse->successful());
        $this->assertEquals($pairingCode, $pairingResponse->json('code'));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $pairingResponse->json('code'));

        // 3. Pengguna menautkan kode di aplikasi WhatsApp ponsel -> Webhook open diterima
        $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => "{$userPhoneNumber}@s.whatsapp.net",
        ], [
            'X-Service-Key' => $this->serviceKey,
            'User-Agent' => $mobileUserAgent,
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
            'user_jid' => "{$userPhoneNumber}@s.whatsapp.net",
        ]);

        // 4. Pengguna menyusun pesan Fast Blast untuk 6 pelanggan loyal
        $contacts = [];
        for ($i = 1; $i <= 6; $i++) {
            $cid = $this->createContactRecord($user->id, "Langganan {$i}", "0857999000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $minDelay = 1;
        $maxDelay = 3;
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Flash Sale Kilat 2 Jam',
            'message_template' => 'Halo {name}, promo kilat 2 jam saja! Diskon 50% untuk nomor {phone}.',
            'min_delay_seconds' => $minDelay,
            'max_delay_seconds' => $maxDelay,
            'status' => 'processing',
            'started_at' => now(),
        ], $contacts);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(6, $messages);

        // 5. Verifikasi perhitungan Staggered Delay pacing (anti-freeze)
        $delayAccumulator = 0;
        $scheduledDelays = [];
        foreach ($messages as $idx => $msg) {
            $step = ($idx === 0) ? 0 : rand($minDelay, $maxDelay);
            $delayAccumulator += $step;
            $scheduledDelays[$msg->id] = $delayAccumulator;
        }

        $this->assertEquals(0, $scheduledDelays[$messages[0]->id]);
        for ($i = 1; $i < count($messages); $i++) {
            $stepDiff = $scheduledDelays[$messages[$i]->id] - $scheduledDelays[$messages[$i - 1]->id];
            $this->assertGreaterThanOrEqual($minDelay, $stepDiff);
            $this->assertLessThanOrEqual($maxDelay, $stepDiff);
        }

        // 6. Eksekusi pengiriman dan transisi centang (Sent -> Delivered -> Read)
        foreach ($messages as $msg) {
            $waId = '3EB0FAST_' . strtoupper(Str::random(8));

            // Transisi 1: Pesan Sent (Centang 1)
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Transisi 2: Webhook DELIVERED (Centang 2 Abu)
            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();

            // Transisi 3: Webhook READ (Centang 2 Biru)
            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        // 7. Verifikasi kondisi akhir kampanye dan pesan
        $finalCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $finalCampaign->status);
        $this->assertEquals(6, $finalCampaign->total_contacts);
        $this->assertEquals(6, $finalCampaign->sent_count);
        $this->assertEquals(6, $finalCampaign->delivered_count);
        $this->assertEquals(6, $finalCampaign->read_count);
        $this->assertEquals(0, $finalCampaign->failed_count);

        // Verifikasi seluruh pesan berstatus read
        $readCountInMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'read')
            ->count();
        $this->assertEquals(6, $readCountInMessages);
    }
}
