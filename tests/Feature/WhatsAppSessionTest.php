<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;
use Tests\TestCase;

class WhatsAppSessionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected BaileysFake $fake;
    protected string $serviceUrl;
    protected string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceUrl = config('services.baileys.url', 'http://127.0.0.1:3000');
        $this->serviceKey = config('services.baileys.key', 'blast_internal_secret_key_2026');

        $this->fake = BaileysFake::create($this->serviceUrl, $this->serviceKey);

        $this->user = User::create([
            'name' => 'Session Tester',
            'email' => 'session_tester_' . uniqid() . '@example.com',
            'password' => Hash::make('secret12345'),
            'locale' => 'id',
        ]);
    }

    /**
     * Skenario 1: Pengguna tamu (guest) tidak dapat mengakses /whatsapp dan di-redirect ke /login.
     */
    public function test_guest_cannot_access_whatsapp_session_page(): void
    {
        $response = $this->get('/whatsapp');
        $response->assertRedirect('/login');
    }

    /**
     * Skenario Keamanan: Pengguna tamu (guest) ditolak saat mengakses rute operasional /wa/*.
     */
    public function test_guest_is_redirected_or_unauthorized_on_operational_wa_routes(): void
    {
        // 1. Permintaan browser web reguler di-redirect ke /login
        $this->get('/wa/status')->assertRedirect('/login');
        $this->get('/wa/qr')->assertRedirect('/login');
        $this->post('/wa/start')->assertRedirect('/login');
        $this->post('/wa/pairing-code', ['phone' => '628123456789'])->assertRedirect('/login');
        $this->post('/wa/logout')->assertRedirect('/login');

        // 2. Permintaan AJAX/JSON mengembalikan HTTP 401 Unauthorized
        $this->getJson('/wa/status')->assertStatus(401);
        $this->getJson('/wa/qr')->assertStatus(401);
        $this->postJson('/wa/start')->assertStatus(401);
        $this->postJson('/wa/pairing-code', ['phone' => '628123456789'])->assertStatus(401);
        $this->postJson('/wa/logout')->assertStatus(401);
    }

    /**
     * Skenario 2: Pengguna terautentikasi dapat membuka /whatsapp dan melihat antarmuka BeerCSS M3.
     */
    public function test_authenticated_user_can_view_whatsapp_session_page(): void
    {
        $this->fake->asDisconnected()->register();

        $response = $this->actingAs($this->user)->get('/whatsapp');

        $response->assertStatus(200);
        $response->assertSee(__('session.title'));
        $response->assertSee(__('session.tab_qr_code'));
        $response->assertSee(__('session.tab_pairing_code'));
    }

    /**
     * Skenario 3: Endpoint /wa/status mengembalikan JSON status sesi dari Baileys & database.
     */
    public function test_status_endpoint_returns_json_session_state(): void
    {
        $this->fake->asConnected('6281234567890@s.whatsapp.net')->register();

        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '6281234567890@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );

        $response = $this->actingAs($this->user)->getJson('/wa/status');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'CONNECTED',
            'userJid' => '6281234567890@s.whatsapp.net',
            'db_status' => 'connected',
        ]);
    }

    /**
     * Skenario 4: Endpoint /wa/qr mengembalikan data URL Base64 gambar QR ketika sesi belum terhubung.
     */
    public function test_qr_endpoint_returns_base64_data_url(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            ['status' => 'disconnected', 'user_jid' => null]
        );

        $sampleQr = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=';
        $this->fake->asQrReady($sampleQr)->register();

        $response = $this->actingAs($this->user)->getJson('/wa/qr');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals($sampleQr, $response->json('qr'));
    }

    /**
     * Skenario 5: Endpoint /wa/qr menolak dengan HTTP 400 jika sesi sudah terhubung di database.
     */
    public function test_qr_endpoint_returns_400_when_already_connected(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '6281234567890@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );

        $response = $this->actingAs($this->user)->getJson('/wa/qr');

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already connected', strtolower($response->json('message')));
    }

    /**
     * Skenario 6: Permintaan pairing code menolak nomor telepon kosong atau format rusak (HTTP 400).
     */
    public function test_pairing_code_validates_phone_number(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            ['status' => 'disconnected']
        );

        $respEmpty = $this->actingAs($this->user)->postJson('/wa/pairing-code', ['phone' => '']);
        $respEmpty->assertStatus(400);
        $this->assertFalse($respEmpty->json('success'));

        $respInvalid = $this->actingAs($this->user)->postJson('/wa/pairing-code', ['phone' => 'abc123xyz']);
        $respInvalid->assertStatus(400);
        $this->assertFalse($respInvalid->json('success'));

        // Pengujian adversarial: input tanda plus berulang tanpa digit atau format rusak
        $respPlusOnly = $this->actingAs($this->user)->postJson('/wa/pairing-code', ['phone' => '++++++++']);
        $respPlusOnly->assertStatus(400);
        $this->assertFalse($respPlusOnly->json('success'));

        $respDoublePlus = $this->actingAs($this->user)->postJson('/wa/pairing-code', ['phone' => '++62812345678']);
        $respDoublePlus->assertStatus(400);
        $this->assertFalse($respDoublePlus->json('success'));
    }

    /**
     * Skenario 7: Permintaan pairing code ditolak (HTTP 400) jika sesi sudah dalam keadaan CONNECTED.
     */
    public function test_pairing_code_rejected_when_already_connected(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '628111222333@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );

        $response = $this->actingAs($this->user)->postJson('/wa/pairing-code', [
            'phone' => '6281234567890',
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already connected', strtolower($response->json('message')));
    }

    /**
     * Skenario 8: Permintaan pairing code valid mengembalikan kode 8 digit alphanumeric.
     */
    public function test_pairing_code_success_returns_8_digit_code(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            ['status' => 'disconnected']
        );

        $this->fake->asDisconnected();
        $this->fake->withPairingCode('WXYZ-9876')->register();

        $response = $this->actingAs($this->user)->postJson('/wa/pairing-code', [
            'phone' => '6281234567890',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $code = $response->json('code');
        $this->assertEquals('WXYZ-9876', $code);
        $cleanCode = str_replace('-', '', $code);
        $this->assertEquals(8, strlen($cleanCode));
    }

    /**
     * Skenario 9: Webhook /api/wa/webhook menolak permintaan tanpa atau dengan X-Service-Key yang salah (HTTP 401).
     */
    public function test_webhook_rejects_unauthorized_service_key(): void
    {
        // 1. Tanpa header X-Service-Key
        $respNoHeader = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
        ]);
        $respNoHeader->assertStatus(401);

        // 2. Dengan X-Service-Key yang salah
        $respInvalidHeader = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
        ], [
            'X-Service-Key' => 'completely_wrong_key_xyz',
        ]);
        $respInvalidHeader->assertStatus(401);
    }

    /**
     * Skenario 10: Webhook connection.update mengelola transisi status (open, close, connecting) dan mencatat ke database.
     */
    public function test_webhook_connection_update_transitions_and_logs(): void
    {
        // 1. Status 'connecting'
        $resp1 = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'connecting',
        ], ['X-Service-Key' => $this->serviceKey]);

        $resp1->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connecting',
        ]);

        // 2. Status 'open'
        $testJid = '628555666777@s.whatsapp.net';
        $resp2 = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'open',
            'user_jid' => $testJid,
        ], ['X-Service-Key' => $this->serviceKey]);

        $resp2->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'connected',
            'user_jid' => $testJid,
        ]);

        // 3. Status 'close'
        $resp3 = $this->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => 'close',
            'reason' => 'Connection dropped',
        ], ['X-Service-Key' => $this->serviceKey]);

        $resp3->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'disconnected',
            'user_jid' => null,
        ]);

        // Memastikan log webhook tercatat di tabel webhook_logs
        $this->assertDatabaseHas('webhook_logs', [
            'event' => 'connection.update',
        ]);
    }

    /**
     * Skenario 11: Webhook message.update memperbarui status tanda terima centang pesan dan agregasi counter kampanye.
     */
    public function test_webhook_message_update_tracks_ticks_and_counters(): void
    {
        // Siapkan dummy kampanye & pesan kampanye
        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $this->user->id,
            'title' => 'Broadcast Webhook Test',
            'message_template' => 'Halo {nama}',
            'status' => 'processing',
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contactId = DB::table('contacts')->insertGetId([
            'user_id' => $this->user->id,
            'phone_number' => '6281234567899',
            'name' => 'Tester Recipient',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $waMsgId = '3EB0TEST' . uniqid();
        $msgId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'phone_number' => '6281234567899',
            'message_text' => 'Halo Tester Recipient',
            'baileys_msg_id' => $waMsgId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Status 2: SENT (Centang 1)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMsgId,
            'status' => 'SENT',
            'status_code' => 2,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'sent',
        ]);
        $this->assertEquals(1, DB::table('campaigns')->where('id', $campaignId)->value('sent_count'));

        // Status 3: DELIVERED (Centang 2 Abu)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMsgId,
            'status' => 'DELIVERED',
            'status_code' => 3,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'delivered',
        ]);
        $this->assertEquals(1, DB::table('campaigns')->where('id', $campaignId)->value('delivered_count'));

        // Status 4: READ (Centang 2 Biru)
        $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => $waMsgId,
            'status' => 'READ',
            'status_code' => 4,
        ], ['X-Service-Key' => $this->serviceKey])->assertOk();

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'read',
        ]);
        $this->assertEquals(1, DB::table('campaigns')->where('id', $campaignId)->value('read_count'));
    }

    /**
     * Skenario 12: Endpoint /wa/logout memutuskan sesi di Baileys dan mereset status di database.
     */
    public function test_logout_disconnects_session_and_resets_database(): void
    {
        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '628999888777@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );

        $this->fake->asConnected('628999888777@s.whatsapp.net')->register();

        $response = $this->actingAs($this->user)->postJson('/wa/logout');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('whatsapp_sessions', [
            'session_id' => 'default',
            'status' => 'disconnected',
            'user_jid' => null,
        ]);
    }
}
