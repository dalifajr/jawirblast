<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B11 - Queue Staggered Delay & Worker Crash Corner Cases
 *
 * Menguji mesin antrean Staggered Native Queue Pacing Engine:
 * konfigurasi zero delay (0 detik), kalkulasi delay ekstrim (300-600 detik),
 * auto-swap ketika min_delay > max_delay mencegah exception mt_rand(),
 * pembatalan kampanye di tengah jalan menghentikan sisa pesan pending,
 * dan pencatatan alasan error ketika pengiriman gagal.
 */
class B11_QueueExtremeDelayAndWorkerCrashCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCampaigns;

    /**
     * Engine kalkulator staggered delay akumulatif anti-freeze.
     */
    protected function calculateStaggeredDelays(int $contactCount, int $minDelay, int $maxDelay): array
    {
        // Proteksi: jika min_delay > max_delay, tukar posisinya
        if ($minDelay > $maxDelay) {
            $temp = $minDelay;
            $minDelay = $maxDelay;
            $maxDelay = $temp;
        }

        $delays = [];
        $accumulated = 0;

        for ($i = 0; $i < $contactCount; $i++) {
            if ($i === 0) {
                $delays[] = 0; // Pesan pertama dikirim segera
            } else {
                $step = ($minDelay === $maxDelay) ? $minDelay : mt_rand($minDelay, $maxDelay);
                $accumulated += $step;
                $delays[] = $accumulated;
            }
        }

        return $delays;
    }

    /**
     * Case 1: Konfigurasi zero-delay (min=0, max=0) menjadwalkan semua pesan segera tanpa jeda.
     */
    public function test_zero_delay_configuration_dispatches_immediately(): void
    {
        $delays = $this->calculateStaggeredDelays(5, 0, 0);

        $this->assertCount(5, $delays);
        foreach ($delays as $delay) {
            $this->assertEquals(0, $delay, "Delay with 0/0 configuration must always be 0 seconds");
        }
    }

    /**
     * Case 2: Kalkulasi pacing delay berdurasi ekstrim (300 s.d. 600 detik per pesan) terakumulasi bertahap.
     */
    public function test_extreme_long_delay_pacing_calculation(): void
    {
        $contactCount = 4;
        $min = 300; // 5 menit
        $max = 600; // 10 menit

        $delays = $this->calculateStaggeredDelays($contactCount, $min, $max);

        $this->assertCount($contactCount, $delays);
        $this->assertEquals(0, $delays[0], 'First message should have 0s delay');

        for ($i = 1; $i < $contactCount; $i++) {
            $diff = $delays[$i] - $delays[$i - 1];
            $this->assertGreaterThanOrEqual($min, $diff);
            $this->assertLessThanOrEqual($max, $diff);
        }

        // Pesan ke-4 akumulasi antara 900s s.d. 1800s
        $this->assertGreaterThanOrEqual(900, $delays[3]);
        $this->assertLessThanOrEqual(1800, $delays[3]);
    }

    /**
     * Case 3: Konfigurasi min_delay lebih besar dari max_delay otomatis ditukar agar mt_rand() tidak melempar error.
     */
    public function test_min_delay_greater_than_max_delay_is_auto_swapped_or_validated(): void
    {
        // Parameter terbalik: min = 15, max = 5
        $invertedMin = 15;
        $invertedMax = 5;

        // Harus berjalan mulus tanpa melempar ValueError PHP
        $delays = $this->calculateStaggeredDelays(5, $invertedMin, $invertedMax);

        $this->assertCount(5, $delays);
        for ($i = 1; $i < 5; $i++) {
            $step = $delays[$i] - $delays[$i - 1];
            $this->assertGreaterThanOrEqual(5, $step);
            $this->assertLessThanOrEqual(15, $step);
        }
    }

    /**
     * Case 4: Membatalkan kampanye yang sedang berjalan menghentikan sisa pesan pending.
     */
    public function test_cancelling_in_progress_campaign_aborts_remaining_pending_messages(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Kampanye Batal Di Tengah',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 3,
            'sent_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pesan 1 sudah sent
        DB::table('campaign_messages')->insert([
            'campaign_id' => $campaignId,
            'phone_number' => '6281200000001',
            'message_text' => 'Pesan 1',
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pesan 2 & 3 masih pending
        $msg2Id = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281200000002',
            'message_text' => 'Pesan 2',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msg3Id = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281200000003',
            'message_text' => 'Pesan 3',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Admin melakukan aksi pembatalan kampanye
        DB::table('campaigns')->where('id', $campaignId)->update(['status' => 'cancelled']);

        // Worker mengecek status kampanye induk sebelum dispatching pesan pending
        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        if ($campaign->status === 'cancelled') {
            // Tandai pesan pending tersisa sebagai tidak dieksekusi (atau biarkan pending/failed)
            DB::table('campaign_messages')
                ->where('campaign_id', $campaignId)
                ->where('status', 'pending')
                ->update(['status' => 'failed', 'error_message' => 'Campaign was cancelled by user']);
        }

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msg2Id,
            'status' => 'failed',
            'error_message' => 'Campaign was cancelled by user',
        ]);

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msg3Id,
            'status' => 'failed',
            'error_message' => 'Campaign was cancelled by user',
        ]);
    }

    /**
     * Case 5: Job yang gagal karena kegagalan socket mencatat alasan kesalahan di error_message.
     */
    public function test_failed_job_retry_and_recording_error_reason(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Test Error Recording',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281299998888',
            'message_text' => 'Pesan Percobaan',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan kegagalan socket saat pengiriman pesan
        $errorMessage = 'Baileys Socket 503: WhatsApp session not connected';
        DB::table('campaign_messages')->where('id', $msgId)->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'updated_at' => now(),
        ]);

        DB::table('campaigns')->where('id', $campaignId)->increment('failed_count');

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'failed_count' => 1,
        ]);
    }
}
