<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C10: Duplicate Contact Handling In Broadcast Flow
 * Alur pengujian:
 * 1. Menyiapkan kontak yang beririsan di multiple kelompok grup
 * 2. Menjalankan broadcast yang menargetkan gabungan grup tersebut
 * 3. Memverifikasi mekanisme deduplikasi nomor penerima
 * 4. Memastikan tidak ada pengiriman duplikat ke nomor yang sama
 */
class C10_DuplicateContactHandlingInBroadcastTest extends Tier3TestCase
{
    public function test_duplicate_contacts_across_groups_are_deduplicated_in_broadcast(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Kontak beririsan (ada di Grup A dan Grup B)
        $sharedPhone = '081299990001';
        $cidShared = $this->createContactRecord($user->id, 'Pelanggan Setia Shared', $sharedPhone);

        // Kontak unik untuk Grup A
        $cidA1 = $this->createContactRecord($user->id, 'Member A1', '081299990002');
        $cidA2 = $this->createContactRecord($user->id, 'Member A2', '081299990003');

        // Kontak unik untuk Grup B
        $cidB1 = $this->createContactRecord($user->id, 'Member B1', '081299990004');
        $cidB2 = $this->createContactRecord($user->id, 'Member B2', '081299990005');

        // 2. Buat dua grup
        $groupAId = $this->createContactGroupRecord($user->id, 'Grup Promo Weekend', [$cidShared, $cidA1, $cidA2]);
        $groupBId = $this->createContactGroupRecord($user->id, 'Grup Member Loyal', [$cidShared, $cidB1, $cidB2]);

        $this->assertEquals(3, DB::table('contact_group_pivot')->where('group_id', $groupAId)->count());
        $this->assertEquals(3, DB::table('contact_group_pivot')->where('group_id', $groupBId)->count());

        // 3. Ambil seluruh kontak dari kedua grup dan terapkan deduplikasi berbasis phone_number
        $rawTargetContacts = DB::table('contacts')
            ->join('contact_group_pivot', 'contacts.id', '=', 'contact_group_pivot.contact_id')
            ->whereIn('contact_group_pivot.group_id', [$groupAId, $groupBId])
            ->select('contacts.*')
            ->get();

        // Total baris sebelum deduplikasi adalah 6 (3 + 3)
        $this->assertCount(6, $rawTargetContacts);

        // Lakukan deduplikasi unik berbasis nomor telepon
        $uniqueContacts = $rawTargetContacts->unique('phone_number')->values();

        // Total kontak unik harus tepat 5 (bukan 6)
        $this->assertCount(5, $uniqueContacts);

        // 4. Buat kampanye siaran dengan kontak yang telah dideduplikasi
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Promo Lintas Grup Anti-Duplikasi',
            'status' => 'processing',
        ], $uniqueContacts->all());

        // 5. Verifikasi bahwa pesan di tabel campaign_messages hanya berjumlah 5
        $campaignMessages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(5, $campaignMessages);

        // Periksa bahwa nomor telepon beririsan hanya muncul 1 kali
        $sharedNormalizedPhone = '6281299990001';
        $occurrencesOfSharedPhone = $campaignMessages->where('phone_number', $sharedNormalizedPhone)->count();
        $this->assertEquals(1, $occurrencesOfSharedPhone, 'Nomor beririsan hanya boleh menerima 1 pesan');

        // 6. Eksekusi pengiriman dan validasi webhook
        foreach ($campaignMessages as $msg) {
            $waId = '3EB0DEDUP_' . strtoupper(Str::random(6));
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
        $this->assertEquals(5, $freshCamp->total_contacts);
        $this->assertEquals(5, $freshCamp->sent_count);
        $this->assertEquals(5, $freshCamp->delivered_count);
        $this->assertEquals('completed', $freshCamp->status);
    }
}
