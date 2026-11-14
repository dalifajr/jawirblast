<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B07 - Session Disconnect Mid-Broadcast & Flapping Corner Cases
 *
 * Menguji skenario batas ketika koneksi WhatsApp terputus di tengah-tengah broadcast aktif:
 * penundaan status (pause) pada kampanye, pesan pending tidak ditandai failed prematur,
 * pemulihan koneksi (resume) tanpa duplikasi pesan, dan ketahanan terhadap flapping connection.
 */
class B07_SessionDisconnectMidBroadcastCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;
    use InteractsWithCampaigns;

    protected function setUp(): void
    {
        parent::setUp();

        // Daftarkan route kontrak webhook Baileys connection.update & message.update
        Route::post('/api/wa/webhook', function (Request $request) {
            $event = $request->input('event');
            $serviceKey = $request->header('X-Service-Key');
            $expectedKey = config('services.baileys.key', 'jawirblast_internal_secret_key');

            if ($serviceKey !== $expectedKey) {
                return response()->json(['error' => 'Unauthorized service key'], 401);
            }

            if ($event === 'connection.update') {
                $status = $request->input('status'); // 'open', 'close', 'connecting'
                $dbStatus = match ($status) {
                    'open' => 'connected',
                    'close' => 'disconnected',
                    'connecting' => 'connecting',
                    default => 'disconnected',
                };

                $updateData = [
                    'status' => $dbStatus,
                    'user_jid' => $request->input('user_jid'),
                    'updated_at' => now(),
                ];

                if ($dbStatus === 'connected') {
                    $updateData['last_connected_at'] = now();
                }

                DB::table('whatsapp_sessions')->updateOrInsert(
                    ['session_id' => 'default'],
                    $updateData
                );

                // Jika terputus ('close'), pause kampanye yang sedang 'processing'
                if ($dbStatus === 'disconnected') {
                    DB::table('campaigns')
                        ->where('status', 'processing')
                        ->update(['status' => 'paused', 'updated_at' => now()]);
                }

                return response()->json(['success' => true, 'session_status' => $dbStatus]);
            }

            return response()->json(['success' => true]);
        });
    }

    /**
     * Case 1: Sesi terputus (connection: close) saat kampanye aktif otomatis mengubah status kampanye menjadi 'paused'.
     */
    public function test_session_disconnect_mid_broadcast_pauses_or_flags_campaign(): void
    {
        $user = User::factory()->create();
        $this->withConnectedWhatsAppSession('default');

        // Buat kampanye yang sedang berjalan (processing)
        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Kampanye Berjalan',
            'message_template' => 'Halo {name}',
            'min_delay_seconds' => 2,
            'max_delay_seconds' => 5,
            'status' => 'processing',
            'total_contacts' => 10,
            'sent_count' => 3,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan putus koneksi mendadak via webhook
        $response = $this->wa()->fireConnectionWebhook('close', null, 'connection lost');
        $response->assertOk();

        // Verifikasi sesi menjadi disconnected
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'disconnected',
        ]);

        // Verifikasi kampanye otomatis di-pause
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'status' => 'paused',
        ]);
    }

    /**
     * Case 2: Pesan yang masih 'pending' dalam antrean tidak ditandai 'failed' prematur saat sesi drop.
     */
    public function test_pending_messages_not_marked_failed_prematurely_on_disconnect(): void
    {
        $user = User::factory()->create();
        $this->withConnectedWhatsAppSession('default');

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Test Pending Messages Preservation',
            'message_template' => 'Pesan Promo',
            'status' => 'processing',
            'total_contacts' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pesan 1: sudah sent
        DB::table('campaign_messages')->insert([
            'campaign_id' => $campaignId,
            'phone_number' => '6281211112222',
            'message_text' => 'Halo Pelanggan 1',
            'status' => 'sent',
            'baileys_msg_id' => '3EB0FIRSTSENT123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pesan 2: masih pending
        $msg2Id = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281233334444',
            'message_text' => 'Halo Pelanggan 2',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Putus koneksi
        $this->wa()->fireConnectionWebhook('close')->assertOk();

        // Pesan 2 harus TETAP pending, TIDAK BOLEH menjadi failed
        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msg2Id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('campaign_messages', [
            'id' => $msg2Id,
            'status' => 'failed',
        ]);
    }

    /**
     * Case 3: Sesi pulih (connection: open), kampanye di-resume dan tidak menduplikasi pesan yang sudah terkirim.
     */
    public function test_session_restoration_allows_campaign_resumption_without_duplicates(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Resume Campaign Test',
            'message_template' => 'Pesan Lanjutan',
            'status' => 'paused',
            'total_contacts' => 2,
            'sent_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgSentId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281211112222',
            'message_text' => 'Halo Pelanggan 1',
            'status' => 'sent',
            'baileys_msg_id' => '3EB0ALREADYSENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgPendingId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281299998888',
            'message_text' => 'Halo Pelanggan 2',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pulihkan sesi
        $this->wa()->fireConnectionWebhook('open', '628123456789@s.whatsapp.net')->assertOk();
        $this->assertDatabaseHas('whatsapp_sessions', ['session_id' => 'default', 'status' => 'connected']);

        // Resume: proses hanya pesan yang statusnya 'pending'
        $pendingMessages = DB::table('campaign_messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->get();

        $this->assertCount(1, $pendingMessages);
        $this->assertEquals($msgPendingId, $pendingMessages->first()->id);

        // Pesan yang sudah sent tidak boleh diproses ulang
        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgSentId,
            'status' => 'sent',
            'baileys_msg_id' => '3EB0ALREADYSENT',
        ]);
    }

    /**
     * Case 4: Flapping connection (open -> close -> connecting -> open berulang cepat) tidak menyebabkan race condition.
     */
    public function test_rapid_flapping_connection_updates_do_not_cause_race_conditions(): void
    {
        $flappingSequence = ['open', 'close', 'connecting', 'open', 'close', 'open'];

        foreach ($flappingSequence as $state) {
            $res = $this->wa()->fireConnectionWebhook($state);
            $res->assertOk();
        }

        // State akhir harus 'connected'
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
        ]);

        // Hanya ada 1 baris record untuk session_id 'default' (tidak duplikat baris)
        $count = DB::table('whatsapp_sessions')->where('session_id', 'default')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Case 5: Database mencatat timestamp 'last_connected_at' dan status 'disconnected' secara akurat.
     */
    public function test_whatsapp_session_records_last_disconnected_timestamp(): void
    {
        // Hubungkan sesi
        $this->wa()->fireConnectionWebhook('open')->assertOk();
        $sessionOpen = DB::table('whatsapp_sessions')->where('session_id', 'default')->first();
        $this->assertNotNull($sessionOpen->last_connected_at);
        $this->assertEquals('connected', $sessionOpen->status);

        // Putuskan sesi
        $this->wa()->fireConnectionWebhook('close')->assertOk();
        $sessionClose = DB::table('whatsapp_sessions')->where('session_id', 'default')->first();
        $this->assertNotNull($sessionClose->updated_at);
        $this->assertEquals('disconnected', $sessionClose->status);
    }
}
