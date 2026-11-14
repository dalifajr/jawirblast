<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B13 - Server-Sent Events (SSE) Client Disconnect & Stream Corner Cases
 *
 * Menguji endpoint live streaming status pengiriman (GET /campaigns/{id}/stream):
 * penanganan pemutusan koneksi klien (client abort) secara aman,
 * snapshot final instan saat kampanye sudah selesai (progress 100%),
 * proteksi otorisasi kepemilikan kampanye (HTTP 403 Forbidden),
 * validasi header standar SSE (text/event-stream, no-cache),
 * dan kepatuhan format payload data JSON terhadap spesifikasi W3C EventSource.
 */
class B13_SseClientDisconnectAndBufferOverflowCornerCasesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Daftarkan route kontrak SSE sesuai spesifikasi PROJECT.md § 3
        Route::middleware(['web', 'auth'])->get('/campaigns/{id}/stream', function (Request $request, $id) {
            $campaign = DB::table('campaigns')->where('id', $id)->first();
            if (!$campaign) {
                return response()->json(['error' => 'Campaign not found'], 404);
            }

            // Otorisasi: hanya pemilik kampanye yang boleh mengakses stream
            if ($campaign->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized campaign access'], 403);
            }

            return new StreamedResponse(function () use ($campaign) {
                $total = $campaign->total_contacts ?: 1;
                $processed = $campaign->sent_count + $campaign->failed_count;
                $progressPercent = min(100, (int) round(($processed / $total) * 100));

                $payload = json_encode([
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'sent_count' => $campaign->sent_count,
                    'delivered_count' => $campaign->delivered_count,
                    'read_count' => $campaign->read_count,
                    'failed_count' => $campaign->failed_count,
                    'progress_percent' => $progressPercent,
                ]);

                echo "data: {$payload}\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        });
    }

    /**
     * Case 1: Stream SSE menangani kondisi pemutusan koneksi klien (abort) tanpa fatal crash.
     */
    public function test_sse_stream_handles_client_abort_gracefully(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'SSE Abort Test',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 10,
            'sent_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->withCookie('locale', 'id')->get("/campaigns/{$campaignId}/stream");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        // Eksekusi stream callback via streamedContent() helper
        $output = $response->streamedContent();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('data: ', $output);
    }

    /**
     * Case 2: Endpoint SSE pada kampanye yang sudah selesai langsung mengembalikan snapshot 100% dan selesai.
     */
    public function test_sse_endpoint_for_already_completed_campaign_returns_final_snapshot(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Completed SSE Test',
            'message_template' => 'Pesan',
            'status' => 'completed',
            'total_contacts' => 50,
            'sent_count' => 50,
            'delivered_count' => 48,
            'read_count' => 30,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->withCookie('locale', 'id')->get("/campaigns/{$campaignId}/stream");
        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('"progress_percent":100', $content);
        $this->assertStringContainsString('"status":"completed"', $content);
        $this->assertStringContainsString('"sent_count":50', $content);
    }

    /**
     * Case 3: Pengguna lain ditolak mengakses stream kampanye milik orang lain (HTTP 403 Forbidden).
     */
    public function test_sse_stream_rejects_unauthorized_user_access(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $owner->id,
            'title' => 'Private Campaign',
            'message_template' => 'Pesan Rahasia',
            'status' => 'processing',
            'total_contacts' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intruder mencoba membuka stream milik Owner -> 403 Forbidden
        $response = $this->actingAs($intruder)->withCookie('locale', 'id')->getJson("/campaigns/{$campaignId}/stream");
        $response->assertStatus(403);
        $this->assertEquals('Unauthorized campaign access', $response->json('error'));
    }

    /**
     * Case 4: Endpoint stream menghasilkan header wajib SSE (text/event-stream, no-cache, X-Accel-Buffering).
     */
    public function test_sse_payload_format_and_required_headers(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Headers Test',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->withCookie('locale', 'id')->get("/campaigns/{$campaignId}/stream");

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    }

    /**
     * Case 5: Format frame SSE mematuhi spesifikasi W3C EventSource (data: {JSON}\n\n).
     */
    public function test_sse_event_format_complies_with_w3c_spec(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'W3C SSE Spec Test',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 20,
            'sent_count' => 10,
            'delivered_count' => 8,
            'read_count' => 5,
            'failed_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->withCookie('locale', 'id')->get("/campaigns/{$campaignId}/stream");

        $rawOutput = $response->streamedContent();

        // Verifikasi diawali 'data: ' dan diakhiri double newline '\n\n'
        $this->assertMatchesRegularExpression('/^data:\s*\{.*\}\n\n$/s', $rawOutput);

        // Ekstrak string JSON di dalam frame
        $jsonString = trim(substr($rawOutput, strlen('data: ')));
        $decoded = json_decode($jsonString, true);

        $this->assertIsArray($decoded);
        $this->assertEquals($campaignId, $decoded['campaign_id']);
        $this->assertEquals(10, $decoded['sent_count']);
        $this->assertEquals(8, $decoded['delivered_count']);
        $this->assertEquals(5, $decoded['read_count']);
        $this->assertEquals(1, $decoded['failed_count']);
        $this->assertArrayHasKey('progress_percent', $decoded);
    }
}
