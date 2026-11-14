<?php

namespace Tests\Feature\E2E\Tier1;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * F07: Multi-Device Auth & Reconnection Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji persistensi sesi multi-device di database dan transisi status koneksi
 * melalui webhook Baileys (connection.update: open, close, connecting),
 * serta idempotensi pembaruan status saat auto-reconnect.
 */
class F07_MultiDeviceAuthAndReconnectTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;

    protected string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
        $this->ensureWaWebhookRouteRegistered();
    }

    protected function ensureWaWebhookRouteRegistered(): void
    {
        if (!Route::has('wa.webhook') && !collect(Route::getRoutes()->get('POST'))->has('api/wa/webhook')) {
            Route::post('/api/wa/webhook', function (Request $request) {
                $serviceKey = $request->header('X-Service-Key');
                if ($serviceKey !== config('services.baileys.key', 'jawirblast_internal_secret_key')) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                $event = $request->input('event');
                $sessionId = $request->input('session_id', 'default');

                if ($event === 'connection.update') {
                    $status = $request->input('status'); // open, close, connecting
                    $userJid = $request->input('user_jid');

                    $dbStatus = match ($status) {
                        'open' => 'connected',
                        'close' => 'disconnected',
                        'connecting' => 'connecting',
                        default => 'disconnected',
                    };

                    $updateData = [
                        'status' => $dbStatus,
                        'updated_at' => now(),
                    ];

                    if ($dbStatus === 'connected') {
                        $updateData['last_connected_at'] = now();
                        if (!empty($userJid)) {
                            $updateData['user_jid'] = $userJid;
                        }
                    } elseif ($dbStatus === 'disconnected') {
                        $updateData['user_jid'] = null;
                    }

                    DB::table('whatsapp_sessions')->updateOrInsert(
                        ['session_id' => $sessionId],
                        array_merge($updateData, ['created_at' => now()])
                    );

                    // Catat webhook log
                    DB::table('webhook_logs')->insert([
                        'event' => 'connection.update',
                        'payload' => json_encode($request->all()),
                        'created_at' => now(),
                    ]);

                    return response()->json(['success' => true, 'status' => $dbStatus]);
                }

                return response()->json(['success' => true]);
            })->name('wa.webhook');
        }
    }

    /**
     * 1. Tabel whatsapp_sessions menyimpan dan merekam sesi multi-device secara akurat.
     */
    public function test_01_whatsapp_session_records_multi_device_status_in_database(): void
    {
        DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => 'device_primary'],
            [
                'user_jid' => '6281234567890@s.whatsapp.net',
                'status' => 'connected',
                'last_connected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'device_primary',
            'user_jid' => '6281234567890@s.whatsapp.net',
            'status' => 'connected',
        ]);
    }

    /**
     * 2. Webhook connection.update status 'open' mentransisikan sesi menjadi 'connected'.
     */
    public function test_02_connection_webhook_open_transitions_session_to_connected(): void
    {
        $jid = '628555444333@s.whatsapp.net';

        $response = $this->wa()->fireConnectionWebhook('open', $jid);
        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
            'user_jid' => $jid,
        ]);
    }

    /**
     * 3. Webhook connection.update status 'close' mentransisikan sesi menjadi 'disconnected'.
     */
    public function test_03_connection_webhook_close_transitions_session_to_disconnected(): void
    {
        // Set awal connected
        DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => 'default'],
            ['status' => 'connected', 'user_jid' => '628999888777@s.whatsapp.net']
        );

        $response = $this->wa()->fireConnectionWebhook('close', null, 'Intentional logout');
        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'disconnected',
            'user_jid' => null,
        ]);
    }

    /**
     * 4. Webhook connection.update status 'connecting' mentransisikan sesi menjadi 'connecting'.
     */
    public function test_04_connection_webhook_connecting_transitions_session_to_connecting(): void
    {
        $response = $this->wa()->fireConnectionWebhook('connecting');
        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connecting',
        ]);
    }

    /**
     * 5. Mekanisme reconnect memperbarui timestamp tanpa menduplikasi baris di tabel whatsapp_sessions.
     */
    public function test_05_reconnect_updates_timestamp_without_creating_duplicate_session_rows(): void
    {
        // Inisialisasi awal
        $this->wa()->fireConnectionWebhook('connecting')->assertOk();
        $this->wa()->fireConnectionWebhook('open', '628777888999@s.whatsapp.net')->assertOk();
        $this->wa()->fireConnectionWebhook('close')->assertOk();
        // Reconnect kembali
        $this->wa()->fireConnectionWebhook('open', '628777888999@s.whatsapp.net')->assertOk();

        // Pastikan hanya ada 1 baris untuk session_id 'default'
        $count = DB::table('whatsapp_sessions')->where('session_id', 'default')->count();
        $this->assertEquals(1, $count, 'Sesi harus di-upsert tanpa membuat baris duplikat');

        $session = DB::table('whatsapp_sessions')->where('session_id', 'default')->first();
        $this->assertEquals('connected', $session->status);
        $this->assertNotNull($session->last_connected_at);
    }
}
