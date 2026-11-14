<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C13: Export Broadcast History with Accurate Ticks Flow
 * Alur pengujian:
 * 1. Menyiapkan riwayat kampanye dengan spektrum status centang beragam
 *    (sent, delivered, read, dan failed)
 * 2. Mengambil rekaman data laporan dari tabel kampanye dan pesan
 * 3. Menghasilkan ekspor data berformat CSV
 * 4. Memverifikasi integritas baris data, header kolom, dan keakuratan label status
 */
class C13_ExportBroadcastHistoryWithAccurateTicksTest extends Tier3TestCase
{
    public function test_export_broadcast_history_with_accurate_delivery_ticks(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Buat 4 kontak
        $contacts = [];
        $sampleData = [
            ['name' => 'Doni Sent', 'phone' => '08124440001'],
            ['name' => 'Euis Delivered', 'phone' => '08124440002'],
            ['name' => 'Ferry Read', 'phone' => '08124440003'],
            ['name' => 'Gina Failed', 'phone' => '08124440004'],
        ];

        foreach ($sampleData as $item) {
            $cid = $this->createContactRecord($user->id, $item['name'], $item['phone']);
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Laporan Siaran Multi Status',
            'status' => 'processing',
        ], $contacts);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();

        // Pesan 1: Sent (Centang 1)
        $waId1 = '3EB0EXP1_' . strtoupper(Str::random(6));
        DB::table('campaign_messages')->where('id', $messages[0]->id)->update([
            'baileys_msg_id' => $waId1,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(5),
        ]);

        // Pesan 2: Delivered (Centang 2 Abu)
        $waId2 = '3EB0EXP2_' . strtoupper(Str::random(6));
        DB::table('campaign_messages')->where('id', $messages[1]->id)->update([
            'baileys_msg_id' => $waId2,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(4),
        ]);
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waId2,
            'remote_jid' => "{$messages[1]->phone_number}@s.whatsapp.net",
            'status' => 'DELIVERED',
            'status_code' => 3,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        // Pesan 3: Read (Centang 2 Biru)
        $waId3 = '3EB0EXP3_' . strtoupper(Str::random(6));
        DB::table('campaign_messages')->where('id', $messages[2]->id)->update([
            'baileys_msg_id' => $waId3,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(3),
        ]);
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waId3,
            'remote_jid' => "{$messages[2]->phone_number}@s.whatsapp.net",
            'status' => 'READ',
            'status_code' => 4,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        // Pesan 4: Failed
        $waId4 = '3EB0EXP4_' . strtoupper(Str::random(6));
        DB::table('campaign_messages')->where('id', $messages[3]->id)->update([
            'baileys_msg_id' => $waId4,
        ]);
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waId4,
            'remote_jid' => "{$messages[3]->phone_number}@s.whatsapp.net",
            'status' => 'FAILED',
            'status_code' => 0,
            'error_message' => 'Number not registered on WhatsApp',
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        // 2. Simulasikan pembuatan CSV export stream
        $exportMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->orderBy('id', 'asc')
            ->get();

        $csvHandle = fopen('php://temp', 'r+');
        fputcsv($csvHandle, ['ID', 'Nama Penerima', 'Nomor Telepon', 'Status Centang', 'Waktu Kirim', 'Waktu Diterima', 'Waktu Dibaca', 'Pesan Error']);

        foreach ($exportMessages as $row) {
            fputcsv($csvHandle, [
                $row->id,
                $row->recipient_name,
                $row->phone_number,
                strtoupper($row->status),
                $row->sent_at ?? '-',
                $row->delivered_at ?? '-',
                $row->read_at ?? '-',
                $row->error_message ?? '-',
            ]);
        }

        rewind($csvHandle);
        $csvContent = stream_get_contents($csvHandle);
        fclose($csvHandle);

        // 3. Verifikasi integritas format CSV
        $this->assertStringContainsString('Nama Penerima', $csvContent);
        $this->assertStringContainsString('Status Centang', $csvContent);

        // Verifikasi keberadaan keempat status pada baris CSV
        $this->assertStringContainsString('"Doni Sent",628124440001,SENT', $csvContent);
        $this->assertStringContainsString('"Euis Delivered",628124440002,DELIVERED', $csvContent);
        $this->assertStringContainsString('"Ferry Read",628124440003,READ', $csvContent);
        $this->assertStringContainsString('"Gina Failed",628124440004,FAILED', $csvContent);
        $this->assertStringContainsString('Number not registered on WhatsApp', $csvContent);

        // Verifikasi counter agregat kampanye di database
        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(3, $freshCamp->sent_count);
        $this->assertEquals(2, $freshCamp->delivered_count);
        $this->assertEquals(1, $freshCamp->read_count);
        $this->assertEquals(1, $freshCamp->failed_count);
    }
}
