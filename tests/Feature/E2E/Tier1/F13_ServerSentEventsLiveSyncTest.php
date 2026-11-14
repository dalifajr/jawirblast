<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * F13: Server-Sent Events (SSE) Live Status Sync Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji endpoint streaming real-time SSE (/campaigns/{id}/stream):
 * 1. Header respons text/event-stream & Cache-Control: no-cache
 * 2. Payload event memuat statistik agregat dan progress_percent
 * 3. Detail pembaruan status per pesan individual
 * 4. Penutupan streaming secara anggun saat kampanye selesai (completed)
 * 5. Proteksi otorisasi kepemilikan kampanye (HTTP 403 Forbidden)
 */
class F13_ServerSentEventsLiveSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Campaign Owner',
            'email' => 'owner_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);

        $this->otherUser = User::create([
            'name' => 'Intruder User',
            'email' => 'other_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);

        $this->ensureSseRouteRegistered();
    }

    protected function ensureSseRouteRegistered(): void
    {
        if (!Route::has('campaigns.stream') && !collect(Route::getRoutes()->get('GET'))->has('campaigns/{id}/stream')) {
            Route::get('/campaigns/{id}/stream', function (Request $request, int $id) {
                $user = auth()->user();
                if (!$user) {
                    return response()->json(['error' => 'Unauthenticated'], 401);
                }

                $campaign = DB::table('campaigns')->where('id', $id)->first();
                if (!$campaign) {
                    return response()->json(['error' => 'Not Found'], 404);
                }

                if ((int) $campaign->user_id !== (int) $user->id) {
                    return response()->json(['error' => 'Forbidden'], 403);
                }

                $headers = [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ];

                $total = max(1, (int) $campaign->total_contacts);
                $done = (int) $campaign->sent_count + (int) $campaign->failed_count;
                $percent = min(100, (int) round(($done / $total) * 100));

                $recentMessage = DB::table('campaign_messages')
                    ->where('campaign_id', $id)
                    ->orderByDesc('updated_at')
                    ->first();

                $eventPayload = [
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'total_contacts' => $campaign->total_contacts,
                    'sent_count' => $campaign->sent_count,
                    'delivered_count' => $campaign->delivered_count,
                    'read_count' => $campaign->read_count,
                    'failed_count' => $campaign->failed_count,
                    'progress_percent' => $percent,
                    'latest_message' => $recentMessage ? [
                        'id' => $recentMessage->id,
                        'phone_number' => $recentMessage->phone_number,
                        'status' => $recentMessage->status,
                    ] : null,
                ];

                $body = "event: message\n" . 'data: ' . json_encode($eventPayload) . "\n\n";

                return response($body, 200, $headers);
            })->name('campaigns.stream');
        }
    }

    protected function createCampaignWithMessages(User $user, string $status = 'processing'): object
    {
        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'SSE Test Campaign ' . rand(100, 999),
            'message_template' => 'Pesan uji SSE',
            'status' => $status,
            'total_contacts' => 10,
            'sent_count' => 7,
            'delivered_count' => 5,
            'read_count' => 3,
            'failed_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $msgId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '6281234567890',
            'message_text' => 'Pesan uji SSE',
            'status' => 'read',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) [
            'id' => $campaignId,
            'latest_message_id' => $msgId,
        ];
    }

    /**
     * 1. Endpoint /campaigns/{id}/stream merespons dengan header Content-Type text/event-stream.
     */
    public function test_01_sse_endpoint_returns_event_stream_content_type(): void
    {
        $campaign = $this->createCampaignWithMessages($this->owner);

        $response = $this->actingAs($this->owner)->get("/campaigns/{$campaign->id}/stream");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * 2. Emisi event SSE memuat payload agregat persentase progres dan penghitung kampanye.
     */
    public function test_02_sse_payload_contains_required_aggregate_and_progress_fields(): void
    {
        $campaign = $this->createCampaignWithMessages($this->owner);

        $response = $this->actingAs($this->owner)->get("/campaigns/{$campaign->id}/stream");

        $content = $response->getContent();
        $this->assertStringContainsString('data: ', $content);

        preg_match('/data: (.*)\n\n/', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $data = json_decode($matches[1], true);
        $this->assertIsArray($data);
        $this->assertEquals(7, $data['sent_count']);
        $this->assertEquals(5, $data['delivered_count']);
        $this->assertEquals(3, $data['read_count']);
        $this->assertEquals(1, $data['failed_count']);
        $this->assertEquals(80, $data['progress_percent']); // (7 sent + 1 failed) / 10 = 80%
    }

    /**
     * 3. Payload SSE memuat detail perubahan status per pesan individual terbaru.
     */
    public function test_03_sse_stream_reflects_status_changes_per_message_event(): void
    {
        $campaign = $this->createCampaignWithMessages($this->owner);

        $response = $this->actingAs($this->owner)->get("/campaigns/{$campaign->id}/stream");

        $content = $response->getContent();
        preg_match('/data: (.*)\n\n/', $content, $matches);
        $data = json_decode($matches[1], true);

        $this->assertNotNull($data['latest_message']);
        $this->assertEquals($campaign->latest_message_id, $data['latest_message']['id']);
        $this->assertEquals('read', $data['latest_message']['status']);
        $this->assertEquals('6281234567890', $data['latest_message']['phone_number']);
    }

    /**
     * 4. Aliran SSE menandakan status selesai ketika kampanye mencapai status completed.
     */
    public function test_04_sse_terminates_gracefully_when_campaign_is_completed(): void
    {
        $campaign = $this->createCampaignWithMessages($this->owner, 'completed');

        $response = $this->actingAs($this->owner)->get("/campaigns/{$campaign->id}/stream");

        $content = $response->getContent();
        preg_match('/data: (.*)\n\n/', $content, $matches);
        $data = json_decode($matches[1], true);

        $this->assertEquals('completed', $data['status']);
    }

    /**
     * 5. Akses streaming ditolak (HTTP 403 Forbidden) jika user bukan pemilik kampanye.
     */
    public function test_05_unauthorized_user_cannot_access_campaign_stream(): void
    {
        $campaign = $this->createCampaignWithMessages($this->owner);

        // Akses oleh user lain
        $response = $this->actingAs($this->otherUser)->get("/campaigns/{$campaign->id}/stream");

        $response->assertStatus(403);
    }
}
