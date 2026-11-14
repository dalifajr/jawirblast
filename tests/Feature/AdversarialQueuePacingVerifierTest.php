<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\BaileysClientService;
use App\Services\CampaignDispatchService;
use App\Services\SpintaxService;
use Illuminate\Contracts\Queue\Job as QueueContractJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BaileysFake;
use Tests\TestCase;

/**
 * Adversarial Queue & Pacing Engine Verifier Test Suite
 *
 * Menguji secara empiris dan menyeluruh seluruh aspek:
 * 1. Larangan keras sleep() / usleep()
 * 2. Staggered Native Queue Pacing Engine & kalkulasi akumulatif skala masal
 * 3. Mesin Status Reaktif (Pause / Resume / Cancel / Failed / Completed)
 * 4. Boundary cases, corner cases, dan race conditions
 */
class AdversarialQueuePacingVerifierTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected WhatsAppSession $session;
    protected BaileysFake $fake;
    protected CampaignDispatchService $dispatchService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrCreate(
            ['email' => 'challenger2_queue_tester@example.com'],
            [
                'name' => 'Queue Tester Challenger 2',
                'password' => bcrypt('secret123'),
                'locale' => 'id',
            ]
        );

        $this->session = WhatsAppSession::firstOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '6281234567890:1@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );

        $this->fake = BaileysFake::create();
        $this->fake->asConnected();

        $this->dispatchService = app(CampaignDispatchService::class);
    }

    // =========================================================================
    // SEKSI 1: VERIFIKASI EMPIRIS LARANGAN KERAS SLEEP() / USLEEP()
    // =========================================================================

    /**
     * 1.1 Verifikasi AST / Kode Sumber: Pastikan TIDAK ADA sleep(), usleep(),
     * time_nanosleep(), time_sleep_until(), atau penundaan thread pemblokir
     * di SendCampaignMessageJob.php dan CampaignDispatchService.php.
     */
    public function test_1_1_ast_verification_strict_no_sleep_calls_in_source_code(): void
    {
        $jobPath = app_path('Jobs/SendCampaignMessageJob.php');
        $servicePath = app_path('Services/CampaignDispatchService.php');

        $this->assertFileExists($jobPath);
        $this->assertFileExists($servicePath);

        $files = [
            'SendCampaignMessageJob' => file_get_contents($jobPath),
            'CampaignDispatchService' => file_get_contents($servicePath),
        ];

        $forbidden = ['sleep', 'usleep', 'time_nanosleep', 'time_sleep_until', 'pcntl_fork'];

        foreach ($files as $name => $code) {
            $codeWithoutComments = preg_replace('!/\*.*?\*/!s', '', $code);
            $codeWithoutComments = preg_replace('!//.*?$!m', '', $codeWithoutComments);

            foreach ($forbidden as $func) {
                $pattern = '/\b' . preg_quote($func, '/') . '\s*\(/i';
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $codeWithoutComments,
                    "PELANGGARAN MANDAT ANTI-FREEZE: {$name} memanggil fungsi pemblokir {$func}()!"
                );
            }
        }
    }

    /**
     * 1.2 Verifikasi Kecepatan Kalkulasi Delay Akumulatif untuk 10.000 Penerima.
     * Harus selesai dalam waktu < 100 milidetik tanpa lagging.
     */
    public function test_1_2_cumulative_staggered_delay_calculation_for_10000_recipients(): void
    {
        $contactCount = 10000;
        $minDelay = 3;
        $maxDelay = 7;

        $startTime = microtime(true);
        $delays = $this->dispatchService->calculateStaggeredDelays($contactCount, $minDelay, $maxDelay);
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertCount($contactCount, $delays);
        $this->assertLessThan(100.0, $elapsedMs, "Kalkulasi 10.000 delay harus < 100ms, faktual: {$elapsedMs}ms");

        // Pesan perdana wajib 0 detik
        $this->assertSame(0, $delays[0]);

        // Verifikasi sifat monotonik naik strictly: D[i] > D[i-1]
        // Dan interval setiap step wajib di dalam rentang [minDelay, maxDelay]
        $sampleIndices = [1, 2, 50, 100, 500, 1000, 5000, 9999];
        foreach ($sampleIndices as $idx) {
            $step = $delays[$idx] - $delays[$idx - 1];
            $this->assertGreaterThanOrEqual($minDelay, $step, "Step pada indeks {$idx} harus >= minDelay");
            $this->assertLessThanOrEqual($maxDelay, $step, "Step pada indeks {$idx} harus <= maxDelay");
            $this->assertGreaterThan($delays[$idx - 1], $delays[$idx], "Delay harus strictly monotonically increasing");
        }

        // Delay terakhir untuk 10.000 penerima harus di antara (9999 * 3) s.d. (9999 * 7) detik
        $expectedMinTotal = (10000 - 1) * $minDelay;
        $expectedMaxTotal = (10000 - 1) * $maxDelay;
        $this->assertGreaterThanOrEqual($expectedMinTotal, $delays[9999]);
        $this->assertLessThanOrEqual($expectedMaxTotal, $delays[9999]);
    }

    /**
     * 1.3 Verifikasi Skala Ekstrem 100.000 Penerima Bebas Integer Overflow.
     */
    public function test_1_3_extreme_scale_100000_recipients_no_integer_overflow(): void
    {
        $contactCount = 100000;
        $minDelay = 10;
        $maxDelay = 20;

        $delays = $this->dispatchService->calculateStaggeredDelays($contactCount, $minDelay, $maxDelay);

        $this->assertCount($contactCount, $delays);
        $this->assertSame(0, $delays[0]);

        $lastDelay = end($delays);
        $this->assertGreaterThan(0, $lastDelay);
        $this->assertIsInt($lastDelay);
        $this->assertLessThan(PHP_INT_MAX, $lastDelay);
    }

    /**
     * 1.4 Verifikasi determinisme kalkulasi saat minDelay == maxDelay (misal delay konstan 5s).
     */
    public function test_1_4_constant_delay_when_min_equals_max(): void
    {
        $delays = $this->dispatchService->calculateStaggeredDelays(5, 5, 5);

        $this->assertSame([0, 5, 10, 15, 20], $delays);
    }

    // =========================================================================
    // SEKSI 2: VERIFIKASI MESIN STATUS REAKTIF (PAUSE / RESUME / CANCEL / COMPLETED)
    // =========================================================================

    /**
     * 2.1 Skenario PAUSED: Job memanggil release(10) dan TIDAK menghubungi socket Baileys.
     */
    public function test_2_1_reactive_job_behavior_when_campaign_paused_calls_release_10(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Uji Pause ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'paused',
            'total_contacts' => 1,
        ]);

        $message = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990001',
            'message_text' => 'Halo tester',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($message->id);

        // Pasang mock queue job untuk menguji apakah release(10) dipanggil
        $mockQueueJob = $this->createMock(QueueContractJob::class);
        $mockQueueJob->expects($this->once())
            ->method('release')
            ->with(10);

        $job->setJob($mockQueueJob);

        // Eksekusi job
        $job->handle();

        // Pesan harus TETAP berstatus pending
        $message->refresh();
        $this->assertSame('pending', $message->status);
        $this->assertNull($message->sent_at);
        $this->assertNull($message->baileys_msg_id);

        // Tidak ada pesan yang dikirim ke Baileys socket
        $this->fake->assertDispatchedCount(0);
    }

    /**
     * 2.2 Skenario CANCELLED / CANCELED: Job membatalkan pengiriman,
     * menandai pesan failed dengan pesan error spesifik, dan TIDAK menghubungi Baileys.
     */
    public function test_2_2_reactive_job_behavior_when_campaign_cancelled(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Dibatalkan ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'cancelled',
            'total_contacts' => 1,
            'failed_count' => 0,
        ]);

        $message = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990002',
            'message_text' => 'Halo tester',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($message->id);
        $job->handle();

        $message->refresh();
        $campaign->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertSame('Campaign was cancelled by user', $message->error_message);
        $this->assertSame(1, $campaign->failed_count);

        // Soket Baileys tidak boleh dihubungi
        $this->fake->assertDispatchedCount(0);
    }

    /**
     * 2.3 Skenario FAILED: Job membatalkan pengiriman saat kampanye status failed.
     */
    public function test_2_3_reactive_job_behavior_when_campaign_failed(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Gagal Global ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'failed',
            'total_contacts' => 1,
            'failed_count' => 0,
        ]);

        $message = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990003',
            'message_text' => 'Halo tester',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($message->id);
        $job->handle();

        $message->refresh();
        $campaign->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertSame('Campaign was cancelled by user', $message->error_message);
        $this->assertSame(1, $campaign->failed_count);
        $this->fake->assertDispatchedCount(0);
    }

    /**
     * 2.4 Skenario DRAFT / SCHEDULED: Job yang berjalan mengaktifkan status kampanye ke processing.
     */
    public function test_2_4_reactive_job_activates_draft_campaign_to_processing(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Masih Draft ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'draft',
            'total_contacts' => 2,
        ]);

        $msg1 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990004',
            'message_text' => 'Pesan 1',
            'status' => 'pending',
        ]);

        $msg2 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990005',
            'message_text' => 'Pesan 2',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($msg1->id);
        $job->handle();

        $campaign->refresh();
        $msg1->refresh();

        $this->assertSame('sent', $msg1->status);
        $this->assertSame('processing', $campaign->status, 'Kampanye harus otomatis berpindah ke processing');
        $this->assertNotNull($campaign->started_at);
        $this->assertSame(1, $campaign->sent_count);
    }

    /**
     * 2.5 Skenario PROCESSING: Pengiriman normal berhasil, counter sent_count bertambah atomik,
     * dan saat pesan pending habis, status kampanye tuntas menjadi completed.
     */
    public function test_2_5_successful_dispatch_and_automatic_completion(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Sukses Lengkap ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 2,
            'sent_count' => 0,
        ]);

        $msg1 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990006',
            'message_text' => 'Pesan 1',
            'status' => 'pending',
        ]);

        $msg2 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990007',
            'message_text' => 'Pesan 2',
            'status' => 'pending',
        ]);

        // Jalankan job 1
        $job1 = new SendCampaignMessageJob($msg1->id);
        $job1->handle();

        $campaign->refresh();
        $msg1->refresh();

        $this->assertSame('sent', $msg1->status);
        $this->assertNotNull($msg1->baileys_msg_id);
        $this->assertNotNull($msg1->sent_at);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame('processing', $campaign->status, 'Kampanye masih processing karena msg2 masih pending');
        $this->assertNull($campaign->completed_at);

        // Jalankan job 2
        $job2 = new SendCampaignMessageJob($msg2->id);
        $job2->handle();

        $campaign->refresh();
        $msg2->refresh();

        $this->assertSame('sent', $msg2->status);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame('completed', $campaign->status, 'Kampanye harus completed saat semua pending pesan tuntas');
        $this->assertNotNull($campaign->completed_at);
    }

    /**
     * 2.6 Skenario Baileys Error (Socket 503): Job menandai failed dan failed_count bertambah atomik.
     */
    public function test_2_6_baileys_error_records_failed_message_and_counter(): void
    {
        $this->fake->willFail('Baileys Socket 503: WhatsApp session not connected');

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Error Socket ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'failed_count' => 0,
        ]);

        $msg = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990008',
            'message_text' => 'Pesan Uji',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($msg->id);
        $job->handle();

        $msg->refresh();
        $campaign->refresh();

        $this->assertSame('failed', $msg->status);
        $this->assertStringContainsString('Socket 503', $msg->error_message);
        $this->assertSame(1, $campaign->failed_count);
        // Karena msg adalah pesan terakhir pending, kampanye tetap menjadi completed
        $this->assertSame('completed', $campaign->status);
    }

    /**
     * 2.7 Skenario Baileys Exception (Throwable): Job menangkap exception dan mencatat error dengan aman.
     */
    public function test_2_7_baileys_throwable_exception_is_handled_gracefully(): void
    {
        $mockClient = $this->createMock(BaileysClientService::class);
        $mockClient->method('sendMessage')
            ->willThrowException(new \RuntimeException('Network timeout connecting to Baileys microservice'));

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Timeout ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'failed_count' => 0,
        ]);

        $msg = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990009',
            'message_text' => 'Pesan Uji',
            'status' => 'pending',
        ]);

        $job = new SendCampaignMessageJob($msg->id);
        $job->handle($mockClient);

        $msg->refresh();
        $campaign->refresh();

        $this->assertSame('failed', $msg->status);
        $this->assertStringContainsString('Network timeout', $msg->error_message);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame('completed', $campaign->status);
    }

    // =========================================================================
    // SEKSI 3: SKENARIO BATAS & KONDISI BALAPAN (RACE CONDITIONS & BOUNDARY CASES)
    // =========================================================================

    /**
     * 3.1 Boundary: min_delay > max_delay otomatis di-swap dan tidak melempar error mt_rand.
     */
    public function test_3_1_boundary_min_greater_than_max_is_auto_swapped(): void
    {
        $delays = $this->dispatchService->calculateStaggeredDelays(5, 20, 5);

        $this->assertCount(5, $delays);
        $this->assertSame(0, $delays[0]);

        for ($i = 1; $i < 5; $i++) {
            $step = $delays[$i] - $delays[$i - 1];
            $this->assertGreaterThanOrEqual(5, $step);
            $this->assertLessThanOrEqual(20, $step);
        }
    }

    /**
     * 3.2 Boundary: Zero delay (0 detik) menghasilkan delay 0 untuk seluruh penerima.
     */
    public function test_3_2_boundary_zero_delay_configuration(): void
    {
        $delays = $this->dispatchService->calculateStaggeredDelays(5, 0, 0);

        $this->assertSame([0, 0, 0, 0, 0], $delays);
    }

    /**
     * 3.3 Boundary: Delay sangat besar (misal 86400 detik / 1 hari).
     */
    public function test_3_3_boundary_extreme_large_delay(): void
    {
        $delays = $this->dispatchService->calculateStaggeredDelays(3, 86400, 172800);

        $this->assertCount(3, $delays);
        $this->assertSame(0, $delays[0]);
        $this->assertGreaterThanOrEqual(86400, $delays[1]);
        $this->assertGreaterThanOrEqual(172800, $delays[2]);
    }

    /**
     * 3.4 Boundary: Kampanye tanpa penerima (0 kontak) tidak error.
     */
    public function test_3_4_boundary_zero_contacts_handled_gracefully(): void
    {
        Queue::fake();

        $delays = $this->dispatchService->calculateStaggeredDelays(0, 5, 10);
        $this->assertSame([], $delays);

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Kosong ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'draft',
            'total_contacts' => 0,
        ]);

        $res = $this->dispatchService->dispatch($campaign);

        $this->assertSame($campaign->id, $res->id);
        Queue::assertNothingPushed();
    }

    /**
     * 3.5 Idempotency: Jika job dijalankan pada pesan yang statusnya BUKAN pending, job keluar tanpa efek samping.
     */
    public function test_3_5_idempotency_ignores_already_processed_messages(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Idempotensi ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'sent_count' => 1,
        ]);

        $message = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990010',
            'message_text' => 'Pesan Sudah Terkirim',
            'status' => 'sent',
            'baileys_msg_id' => '3EB0EXISTING',
            'sent_at' => now(),
        ]);

        $job = new SendCampaignMessageJob($message->id);
        $job->handle();

        $campaign->refresh();
        $message->refresh();

        // Tidak boleh mengirim ulang atau menambah sent_count
        $this->fake->assertDispatchedCount(0);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame('3EB0EXISTING', $message->baileys_msg_id);
    }

    /**
     * 3.6 Edge Case: Job dijalankan dengan ID pesan atau kampanye yang tidak ditemukan (sudah dihapus).
     */
    public function test_3_6_missing_record_handling(): void
    {
        $jobWithInvalidMessage = new SendCampaignMessageJob(99999999);
        $jobWithInvalidMessage->handle(); // Tidak boleh melempar unhandled exception

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Akan Dihapus ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'processing',
        ]);

        $msg = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990011',
            'message_text' => 'Pesan',
            'status' => 'pending',
        ]);

        // Hapus kampanye induk
        $campaign->delete();

        $jobWithDeletedCampaign = new SendCampaignMessageJob($msg->id);
        $jobWithDeletedCampaign->handle(); // Tidak boleh melempar exception

        $this->fake->assertDispatchedCount(0);
    }

    /**
     * 3.7 Race Condition: Lifecycle Service (Pause / Resume / Cancel) State Machine Guarantees.
     */
    public function test_3_7_lifecycle_service_state_machine_guarantees(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'State Machine Test ' . uniqid(),
            'message_template' => 'Pesan',
            'status' => 'draft',
            'total_contacts' => 2,
        ]);

        $m1 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990012',
            'message_text' => 'M1',
            'status' => 'pending',
        ]);
        $m2 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281299990013',
            'message_text' => 'M2',
            'status' => 'pending',
        ]);

        // Draft tidak bisa di-pause
        $this->assertFalse($this->dispatchService->pauseCampaign($campaign));

        // Ubah ke processing
        $campaign->update(['status' => 'processing']);

        // Sekarang bisa di-pause
        $this->assertTrue($this->dispatchService->pauseCampaign($campaign));
        $campaign->refresh();
        $this->assertSame('paused', $campaign->status);

        // Tidak bisa di-pause lagi saat sedang paused
        $this->assertFalse($this->dispatchService->pauseCampaign($campaign));

        // Resume kampanye
        $this->assertTrue($this->dispatchService->resumeCampaign($campaign));
        $campaign->refresh();
        $this->assertSame('processing', $campaign->status);

        // Cancel kampanye
        $this->assertTrue($this->dispatchService->cancelCampaign($campaign));
        $campaign->refresh();
        $this->assertSame('cancelled', $campaign->status);
        $this->assertSame(2, $campaign->failed_count);

        $m1->refresh();
        $m2->refresh();
        $this->assertSame('failed', $m1->status);
        $this->assertSame('failed', $m2->status);

        // Tidak bisa di-cancel lagi jika sudah cancelled
        $this->assertFalse($this->dispatchService->cancelCampaign($campaign));
    }

    /**
     * 3.8 Stress Test: Dispatching 1.000 kontak simultan melalui CampaignDispatchService::dispatch().
     * Verifikasi bahwa setiap job dipancarkan ke antrean broadcasts dengan delay bertingkat yang valid.
     */
    public function test_3_8_dispatch_1000_contacts_simultaneous_pacing_verification(): void
    {
        Queue::fake();

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Akbar 1000 Kontak ' . uniqid(),
            'message_template' => 'Halo {name}, salam hangat!',
            'status' => 'draft',
            'min_delay_seconds' => 2,
            'max_delay_seconds' => 5,
            'total_contacts' => 1000,
        ]);

        // Siapkan 1000 pesan pending secara massal
        $records = [];
        $now = now();
        for ($i = 1; $i <= 1000; $i++) {
            $records[] = [
                'campaign_id' => $campaign->id,
                'phone_number' => '62812' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'recipient_name' => "User {$i}",
                'message_text' => "Halo User {$i}, salam hangat!",
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($records, 250) as $chunk) {
            CampaignMessage::insert($chunk);
        }

        $startTime = microtime(true);
        $this->dispatchService->dispatch($campaign);
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $campaign->refresh();
        $this->assertSame('processing', $campaign->status);

        // Pastikan proses dispatch 1000 kontak ke queue berjalan cepat (< 3 detik di lingkungan pengujian)
        $this->assertLessThan(3000.0, $elapsedMs, "Dispatch 1000 kontak harus < 3 detik, faktual: {$elapsedMs}ms");

        Queue::assertPushed(SendCampaignMessageJob::class, 1000);
        Queue::assertPushedOn('broadcasts', SendCampaignMessageJob::class);
    }

    /**
     * 3.9 Skenario Semua Kontak Gagal: Seluruh pesan gagal dikirim (misal nomor invalid / socket putus).
     * Verifikasi bahwa kampanye tetap berakhir dengan completed, total failed_count sama dengan total kontak,
     * dan tidak ada pesan yang tertinggal dalam status pending.
     */
    public function test_3_9_all_contacts_failed_still_completes_campaign(): void
    {
        $this->fake->willFail('Invalid WhatsApp Number / JID not registered');

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Semua Gagal ' . uniqid(),
            'message_template' => 'Pesan untuk kontak invalid',
            'status' => 'processing',
            'total_contacts' => 3,
            'failed_count' => 0,
        ]);

        $m1 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '000000000001',
            'message_text' => 'Pesan 1',
            'status' => 'pending',
        ]);
        $m2 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '000000000002',
            'message_text' => 'Pesan 2',
            'status' => 'pending',
        ]);
        $m3 = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '000000000003',
            'message_text' => 'Pesan 3',
            'status' => 'pending',
        ]);

        (new SendCampaignMessageJob($m1->id))->handle();
        (new SendCampaignMessageJob($m2->id))->handle();
        (new SendCampaignMessageJob($m3->id))->handle();

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(3, $campaign->failed_count);
        $this->assertSame(0, $campaign->sent_count);

        $pendingRemaining = CampaignMessage::where('campaign_id', $campaign->id)->pending()->count();
        $this->assertSame(0, $pendingRemaining);
    }

    /**
     * 3.10 Skenario Analisis Risiko: Efek $tries = 3 pada durasi pause > 30 detik.
     * Menguji bahwa job SendCampaignMessageJob mengonfigurasi tries = 3.
     */
    public function test_3_10_job_tries_and_pause_duration_risk_analysis(): void
    {
        $job = new SendCampaignMessageJob(1);
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
    }
}

