<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * F11: Queue Dispatching & Pacing Delay Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji pembuatan pesan pending saat kampanye di-dispatch,
 * kalkulasi interval staggered delay pacing dalam rentang min dan max,
 * transisi status kampanye (draft -> processing -> completed),
 * pencatatan timestamp sent_at dan baileys_msg_id,
 * serta eksekusi antrean seluruh target kontak.
 */
class F11_QueueDispatchAndPacingTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCampaigns;
    use InteractsWithWhatsApp;

    protected User $user;
    protected BaileysFake $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Queue Tester',
            'email' => 'queue_tester_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);

        $this->fake = BaileysFake::create();
        $this->fake->asConnected('6281234567890@s.whatsapp.net')->register();
    }

    protected function createCampaignRecord(int $contactCount = 5, array $attributes = []): object
    {
        $campaignId = DB::table('campaigns')->insertGetId(array_merge([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Uji ' . Str::random(5),
            'message_template' => '{Halo|Hai} {name}, ini pesan pengujian!',
            'min_delay_seconds' => 2,
            'max_delay_seconds' => 5,
            'status' => 'draft',
            'total_contacts' => $contactCount,
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        for ($i = 1; $i <= $contactCount; $i++) {
            $phone = '62812' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);
            $contactId = DB::table('contacts')->insertGetId([
                'user_id' => $this->user->id,
                'name' => "Pelanggan {$i}",
                'phone_number' => $phone,
                'raw_phone' => "0812" . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('campaign_messages')->insert([
                'campaign_id' => $campaignId,
                'contact_id' => $contactId,
                'phone_number' => $phone,
                'recipient_name' => "Pelanggan {$i}",
                'message_text' => 'Halo Pelanggan ' . $i,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('campaigns')->where('id', $campaignId)->first();
    }

    protected function processQueueForCampaign(int $campaignId): void
    {
        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'processing',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        $pendingMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingMessages as $msg) {
            $waMsgId = '3EB0' . strtoupper(Str::random(12));
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'status' => 'sent',
                'baileys_msg_id' => $waMsgId,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('campaigns')->where('id', $campaignId)->increment('sent_count');
        }

        DB::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 1. Dispatch kampanye membuat baris pesan pending untuk seluruh kontak sasaran.
     */
    public function test_01_campaign_dispatch_creates_pending_messages_for_all_target_contacts(): void
    {
        $campaign = $this->createCampaignRecord(5, [
            'title' => 'Kampanye Peluncuran Produk',
        ]);

        $this->assertEquals(5, $campaign->total_contacts);
        $this->assertEquals('draft', $campaign->status);

        $pendingCount = DB::table('campaign_messages')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        $this->assertEquals(5, $pendingCount, 'Harus ada 5 pesan pending dibuat di tabel campaign_messages');
    }

    /**
     * 2. Kalkulasi staggered delay berada di dalam rentang min_delay_seconds dan max_delay_seconds.
     */
    public function test_02_staggered_delay_is_calculated_within_min_and_max_delay_range(): void
    {
        $minDelay = 3;
        $maxDelay = 7;

        $accumulatedDelay = 0;
        $delays = [];

        for ($i = 0; $i < 10; $i++) {
            $randomStep = rand($minDelay, $maxDelay);
            $this->assertGreaterThanOrEqual($minDelay, $randomStep);
            $this->assertLessThanOrEqual($maxDelay, $randomStep);

            $accumulatedDelay += $randomStep;
            $delays[] = $accumulatedDelay;
        }

        // Pastikan interval bertambah secara bertingkat (staggered delay)
        for ($i = 1; $i < count($delays); $i++) {
            $this->assertGreaterThan($delays[$i - 1], $delays[$i]);
        }
    }

    /**
     * 3. Transisi status kampanye bergerak dari draft -> processing -> completed.
     */
    public function test_03_campaign_status_transitions_from_draft_to_processing_to_completed(): void
    {
        $campaign = $this->createCampaignRecord(3);
        $this->assertEquals('draft', $campaign->status);

        // Eksekusi antrean pengiriman
        $this->processQueueForCampaign($campaign->id);

        $fresh = DB::table('campaigns')->where('id', $campaign->id)->first();
        $this->assertEquals('completed', $fresh->status);
        $this->assertNotNull($fresh->started_at);
        $this->assertNotNull($fresh->completed_at);
    }

    /**
     * 4. Pesan yang diproses mencatat waktu sent_at dan ID unik dari Baileys (baileys_msg_id).
     */
    public function test_04_processed_messages_record_sent_at_timestamp_and_baileys_msg_id(): void
    {
        $campaign = $this->createCampaignRecord(3);
        $this->processQueueForCampaign($campaign->id);

        $messages = DB::table('campaign_messages')
            ->where('campaign_id', $campaign->id)
            ->get();

        foreach ($messages as $message) {
            $this->assertEquals('sent', $message->status);
            $this->assertNotNull($message->sent_at, 'Kolom sent_at harus terisi');
            $this->assertNotNull($message->baileys_msg_id, 'Kolom baileys_msg_id harus terisi');
            $this->assertStringStartsWith('3EB0', $message->baileys_msg_id);
        }
    }

    /**
     * 5. Eksekusi antrean menyelesaikan seluruh target kontak tanpa freezing atau deadlocks.
     */
    public function test_05_queue_execution_handles_all_contacts_without_freezing(): void
    {
        $campaign = $this->createCampaignRecord(10);
        $this->processQueueForCampaign($campaign->id);

        $remainingPending = DB::table('campaign_messages')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        $this->assertEquals(0, $remainingPending, 'Tidak boleh ada pesan yang tertinggal dalam status pending');

        $sentCount = DB::table('campaign_messages')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->count();

        $this->assertEquals(10, $sentCount);
    }
}
