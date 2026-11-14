<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C08: Pause Resume Broadcast Lifecycle Flow
 * Alur pengujian:
 * 1. Memulai kampanye siaran
 * 2. Mengirim sebagian pesan
 * 3. Menjeda kampanye (pause) dan memverifikasi penghentian antrean
 * 4. Melanjutkan kembali kampanye (resume)
 * 5. Menyelesaikan seluruh sisa pesan hingga 100% tuntas
 */
class C08_PauseResumeBroadcastLifecycleTest extends Tier3TestCase
{
    public function test_pause_and_resume_campaign_execution_lifecycle(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        // 1. Buat 6 kontak
        $contacts = [];
        for ($i = 1; $i <= 6; $i++) {
            $cid = $this->createContactRecord($user->id, "Penerima {$i}", "0812777000{$i}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Siklus Jeda dan Lanjut Siaran',
            'status' => 'processing',
            'started_at' => now(),
        ], $contacts);

        // 2. Kirim 2 pesan pertama
        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        for ($i = 0; $i < 2; $i++) {
            $waId = '3EB0BATCH1_' . strtoupper(Str::random(6));
            DB::table('campaign_messages')->where('id', $messages[$i]->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$messages[$i]->phone_number}@s.whatsapp.net",
                'status' => 'DELIVERED',
                'status_code' => 3,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        // 3. User menjeda kampanye (Pause action)
        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'paused',
            'updated_at' => now(),
        ]);

        $pausedCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('paused', $pausedCamp->status);
        $this->assertEquals(2, $pausedCamp->sent_count);

        // Verifikasi bahwa 4 pesan tersisa tetap pending
        $pendingCount = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->count();
        $this->assertEquals(4, $pendingCount);

        // 4. User melanjutkan kampanye (Resume action)
        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);

        $resumedCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('processing', $resumedCamp->status);

        // 5. Eksekusi sisa 4 pesan pending
        $remainingMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->get();

        foreach ($remainingMessages as $msg) {
            $waId = '3EB0BATCH2_' . strtoupper(Str::random(6));
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

        // 6. Verifikasi kondisi akhir kampanye
        $completedCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('completed', $completedCamp->status);
        $this->assertEquals(6, $completedCamp->total_contacts);
        $this->assertEquals(6, $completedCamp->sent_count);
        $this->assertEquals(6, $completedCamp->delivered_count);
        $this->assertEquals(0, $completedCamp->failed_count);
        $this->assertNotNull($completedCamp->completed_at);
    }
}
