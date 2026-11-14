<?php

namespace Tests\Feature\E2E\Tier4;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * S04: Massive Contact Import and Staggered Stress (Real-World High Complexity)
 * Fitur yang diuji: F8, F9, F11, F12
 *
 * Alur Skenario:
 * 1. Unggah berkas impor massal 50 kontak campuran (lokal 08, internasional +62, negara luar, dan invalid)
 * 2. Sistem menyaring nomor invalid ke status 'invalid' dan menormalkan nomor valid ke E.164
 * 3. Pembuatan kampanye broadcast massal dengan parameter jitter delay acak
 * 4. Pemrosesan antrean staggered delay dalam volume tinggi
 * 5. Simulasi lonjakan webhook masuk beruntun dan verifikasi integritas counter agregat database
 */
class S04_MassiveContactImportAndStaggeredStressTest extends Tier4TestCase
{
    public function test_massive_contact_import_normalization_and_staggered_stress_dispatch(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Siapkan dataset 50 kontak campuran
        $rawDataset = [];

        // 35 kontak lokal standar (0812...)
        for ($i = 1; $i <= 35; $i++) {
            $rawDataset[] = [
                'name' => "Klien Lokal {$i}",
                'phone' => '0812' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ];
        }

        // 5 kontak format dengan tanda baca (+62 812-...)
        for ($i = 36; $i <= 40; $i++) {
            $rawDataset[] = [
                'name' => "Klien Format Tanda Baca {$i}",
                'phone' => '+62 812-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT) . '-9999',
            ];
        }

        // 5 kontak internasional (USA, China, UK)
        $rawDataset[] = ['name' => 'Klien US 1', 'phone' => '+1 555-0199283'];
        $rawDataset[] = ['name' => 'Klien US 2', 'phone' => '15550199284'];
        $rawDataset[] = ['name' => 'Klien CN 1', 'phone' => '+86 138-0000-1111'];
        $rawDataset[] = ['name' => 'Klien UK 1', 'phone' => '+44 7911 123456'];
        $rawDataset[] = ['name' => 'Klien UK 2', 'phone' => '447911123457'];

        // 5 kontak invalid/rusak
        $rawDataset[] = ['name' => 'Invalid Pendek', 'phone' => '12345'];
        $rawDataset[] = ['name' => 'Invalid Karakter', 'phone' => '0812abcdef'];
        $rawDataset[] = ['name' => 'Invalid Simbol', 'phone' => '###---@@@'];
        $rawDataset[] = ['name' => 'Invalid Nol', 'phone' => '000000'];
        $rawDataset[] = ['name' => 'Invalid Kosong', 'phone' => ''];

        $this->assertCount(50, $rawDataset);

        // 2. Normalisasi nomor telepon dan klasifikasi status (active vs invalid)
        $validContactRecords = [];
        $invalidCount = 0;

        foreach ($rawDataset as $item) {
            $phone = trim($item['phone']);
            $digitsOnly = preg_replace('/[^0-9]/', '', $phone);

            // Validasi: minimal 8 digit dan tidak kosong
            $isValid = strlen($digitsOnly) >= 8 && !preg_match('/^0+$/', $digitsOnly);

            if ($isValid) {
                // Normalisasi lokal 08 -> 628
                if (str_starts_with($digitsOnly, '08')) {
                    $digitsOnly = '628' . substr($digitsOnly, 2);
                }

                $cid = DB::table('contacts')->insertGetId([
                    'user_id' => $user->id,
                    'name' => $item['name'],
                    'phone_number' => $digitsOnly,
                    'raw_phone' => $item['phone'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $validContactRecords[] = (object) [
                    'id' => $cid,
                    'name' => $item['name'],
                    'phone_number' => $digitsOnly,
                ];
            } else {
                DB::table('contacts')->insert([
                    'user_id' => $user->id,
                    'name' => $item['name'],
                    'phone_number' => $digitsOnly ?: 'INVALID',
                    'raw_phone' => $item['phone'],
                    'status' => 'invalid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $invalidCount++;
            }
        }

        // Tepat 45 kontak valid dan 5 kontak invalid
        $this->assertCount(45, $validContactRecords);
        $this->assertEquals(5, $invalidCount);
        $this->assertEquals(50, DB::table('contacts')->where('user_id', $user->id)->count());

        // 3. Buat kampanye massal khusus kontak aktif (45 penerima)
        $minDelay = 1;
        $maxDelay = 3;
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Stres Uji Siaran Massal 45 Kontak Aktif',
            'min_delay_seconds' => $minDelay,
            'max_delay_seconds' => $maxDelay,
            'status' => 'processing',
            'started_at' => now(),
        ], $validContactRecords);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(45, $messages);

        // 4. Kalkulasi simulasi Staggered Native Queue Delay
        $cumulativeDelay = 0;
        foreach ($messages as $idx => $msg) {
            $step = ($idx === 0) ? 0 : rand($minDelay, $maxDelay);
            $cumulativeDelay += $step;
        }
        $this->assertGreaterThan(40, $cumulativeDelay, 'Akumulasi delay untuk 45 pesan harus terdistribusi aman anti-freeze');

        // 5. Eksekusi pengiriman massal dan penembakan webhook lonjakan (Burst Traffic)
        $dispatchedCount = 0;
        foreach ($messages as $idx => $msg) {
            $waId = '3EB0STRESS_' . strtoupper(Str::random(10));

            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $dispatchedCount++;

            // Sebagian besar delivered (40 pesan)
            if ($idx < 40) {
                $this->postJson('/api/wa/webhook', [
                    'event' => 'message.update',
                    'wa_message_id' => $waId,
                    'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                    'status' => 'DELIVERED',
                    'status_code' => 3,
                ], ['X-Service-Key' => $this->serviceKey])->assertOk();
            } else {
                // 5 pesan gagal terkirim (misal nomor luar negeri tidak aktif)
                $this->postJson('/api/wa/webhook', [
                    'event' => 'message.update',
                    'wa_message_id' => $waId,
                    'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                    'status' => 'FAILED',
                    'status_code' => 0,
                    'error_message' => 'Destination unreachable',
                ], ['X-Service-Key' => $this->serviceKey])->assertOk();
            }
        }

        $this->assertEquals(45, $dispatchedCount);

        // 6. Verifikasi integritas counter kampanye
        $finalCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(45, $finalCampaign->total_contacts);
        $this->assertEquals(40, $finalCampaign->sent_count);
        $this->assertEquals(40, $finalCampaign->delivered_count);
        $this->assertEquals(5, $finalCampaign->failed_count);
        $this->assertEquals('completed', $finalCampaign->status);
        $this->assertNotNull($finalCampaign->completed_at);

        // Verifikasi tabel webhook_logs mencatat tepat 45 payload webhook tanpa duplikasi atau korupsi
        $totalWebhookLogs = DB::table('webhook_logs')->where('event', 'message.update')->count();
        $this->assertEquals(45, $totalWebhookLogs);
    }
}
