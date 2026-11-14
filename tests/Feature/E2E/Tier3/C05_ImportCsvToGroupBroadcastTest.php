<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C05: Import CSV to Group Broadcast Flow
 * Alur pengujian:
 * 1. Impor baris data CSV dan normalisasi nomor telepon
 * 2. Mengelompokkan kontak hasil impor ke grup spesifik
 * 3. Menyiapkan kontak lain di luar grup
 * 4. Menjalankan broadcast yang hanya menyasar grup tersebut
 * 5. Memverifikasi isolasi target kampanye hanya ke anggota grup
 */
class C05_ImportCsvToGroupBroadcastTest extends Tier3TestCase
{
    public function test_csv_import_grouped_contacts_isolated_broadcast(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Simulasikan pembacaan konten CSV
        $csvRows = [
            ['name' => 'Andi Pratama', 'phone' => '08123450001'],
            ['name' => 'Bambang Wijaya', 'phone' => '+62 812-3450-002'],
            ['name' => 'Citra Lestari', 'phone' => '628123450003'],
            ['name' => 'Dedi Kurniawan', 'phone' => '0812-3450-004'],
            ['name' => 'Eka Saputri', 'phone' => '08123450005'],
        ];

        // 2. Impor kontak dan normalisasi
        $importedContactIds = [];
        foreach ($csvRows as $row) {
            $cid = $this->createContactRecord($user->id, $row['name'], $row['phone']);
            $importedContactIds[] = $cid;
        }
        $this->assertCount(5, $importedContactIds);

        // 3. Masukkan ke grup "Pelanggan Prioritas"
        $vipGroupId = $this->createContactGroupRecord($user->id, 'Pelanggan Prioritas', $importedContactIds);
        $this->assertEquals(5, DB::table('contact_group_pivot')->where('group_id', $vipGroupId)->count());

        // 4. Buat kontak reguler di luar grup
        $regularContactId1 = $this->createContactRecord($user->id, 'Kontak Umum 1', '08991112223');
        $regularContactId2 = $this->createContactRecord($user->id, 'Kontak Umum 2', '08991112224');

        // Pastikan total kontak user = 7
        $this->assertEquals(7, DB::table('contacts')->where('user_id', $user->id)->count());

        // 5. Buat kampanye dengan target grup Pelanggan Prioritas saja
        $targetContacts = DB::table('contacts')
            ->join('contact_group_pivot', 'contacts.id', '=', 'contact_group_pivot.contact_id')
            ->where('contact_group_pivot.group_id', $vipGroupId)
            ->select('contacts.*')
            ->get();

        $this->assertCount(5, $targetContacts);

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Eksklusif Pelanggan Prioritas',
            'status' => 'processing',
        ], $targetContacts->all());

        // 6. Verifikasi isolasi pesan kampanye
        $campaignMessages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(5, $campaignMessages);

        $targetPhoneNumbers = $targetContacts->pluck('phone_number')->all();
        foreach ($campaignMessages as $msg) {
            $this->assertContains($msg->phone_number, $targetPhoneNumbers);
            $this->assertNotEquals('628991112223', $msg->phone_number, 'Kontak umum tidak boleh menerima pesan VIP');
            $this->assertNotEquals('628991112224', $msg->phone_number, 'Kontak umum tidak boleh menerima pesan VIP');
        }

        // 7. Eksekusi pengiriman dan webhook centang
        foreach ($campaignMessages as $msg) {
            $waId = '3EB0VIP' . strtoupper(Str::random(8));
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

        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(5, $freshCamp->total_contacts);
        $this->assertEquals(5, $freshCamp->sent_count);
        $this->assertEquals(5, $freshCamp->read_count);
        $this->assertEquals('completed', $freshCamp->status);
    }
}
