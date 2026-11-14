<?php

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

class TestInfraVerificationTest extends TestCase
{
    use InteractsWithWhatsApp;
    use InteractsWithTelegram;
    use InteractsWithCampaigns;

    protected array $receivedWaWebhooks = [];
    protected array $receivedTelegramWebhooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->receivedWaWebhooks = [];
        $this->receivedTelegramWebhooks = [];

        // Register temporary test webhook routes to verify webhook dispatching
        $this->app['router']->post('/api/wa/webhook', function (Request $request) {
            $this->receivedWaWebhooks[] = $request->all();
            return response()->json(['success' => true, 'event' => $request->input('event')]);
        });

        $this->app['router']->post('/api/telegram/webhook', function (Request $request) {
            $this->receivedTelegramWebhooks[] = $request->all();
            return response()->json(['ok' => true]);
        });
    }

    /**
     * Verifikasi BaileysFake dapat meng-intercept panggilan HTTP port 3000
     * dan menangani endpoint status, start, qr, pairing-code, dan error simulation.
     */
    public function test_baileys_fake_intercepts_status_and_control_endpoints(): void
    {
        $fake = BaileysFake::create('http://127.0.0.1:3000', 'jawirblast_internal_secret_key');

        // 1. Status endpoint - Default CONNECTED
        $res = Http::withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
            ->get('http://127.0.0.1:3000/api/session/status');
        $this->assertTrue($res->successful());
        $this->assertEquals('CONNECTED', $res->json('status'));
        $this->assertEquals('628123456789@s.whatsapp.net', $res->json('userJid'));

        // 2. Start endpoint
        $res = Http::withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
            ->post('http://127.0.0.1:3000/api/session/start');
        $this->assertTrue($res->successful());
        $this->assertEquals('Socket initializing', $res->json('message'));

        // 3. QR endpoint saat CONNECTED -> 400
        $res = Http::get('http://127.0.0.1:3000/api/session/qr');
        $this->assertEquals(400, $res->status());

        // 4. State transition: asQrReady -> 200
        $fake->asQrReady('data:image/png;base64,mockCustomQrCode')->register();
        $res = Http::get('http://127.0.0.1:3000/api/session/qr');
        $this->assertTrue($res->successful());
        $this->assertEquals('data:image/png;base64,mockCustomQrCode', $res->json('qr'));

        // 5. Pairing code endpoint
        $fake->withPairingCode('JAWIR-2026')->register();
        $res = Http::post('http://127.0.0.1:3000/api/session/pairing-code', ['phone' => '628123456789']);
        $this->assertTrue($res->successful());
        $this->assertEquals('JAWIR-2026', $res->json('code'));

        // 6. Logout endpoint
        $res = Http::post('http://127.0.0.1:3000/api/session/logout');
        $this->assertTrue($res->successful());
        $this->assertEquals('DISCONNECTED', $fake->getSessionStatus());

        // 7. Failure simulation
        $fake->willFail('Socket unreachable')->register();
        $res = Http::post('http://127.0.0.1:3000/api/session/pairing-code', ['phone' => '628123456789']);
        $this->assertEquals(500, $res->status());
        $this->assertEquals('Socket unreachable', $res->json('message'));
    }

    /**
     * Verifikasi BaileysFake meng-intercept pengiriman pesan dan melacak payload yang dikirim.
     */
    public function test_baileys_fake_intercepts_message_send_and_tracks_dispatched_messages(): void
    {
        $fake = BaileysFake::create();

        $response = Http::post('http://127.0.0.1:3000/api/message/send', [
            'recipient_id' => 42,
            'phone' => '6281299998888',
            'message' => 'Halo dari E2E Test Runner JAWIRBLAST!',
        ]);

        $this->assertTrue($response->successful());
        $this->assertEquals('SENT', $response->json('status'));
        $this->assertStringStartsWith('3EB0', $response->json('wa_message_id'));

        $fake->assertDispatchedCount(1);
        $messages = $fake->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals(42, $messages[0]['recipient_id']);
        $this->assertEquals('6281299998888', $messages[0]['phone']);
        $this->assertEquals('Halo dari E2E Test Runner JAWIRBLAST!', $messages[0]['message']);
    }

    /**
     * Verifikasi BaileysFake dapat menembakkan webhook status SENT (2), DELIVERED (3), dan READ (4).
     */
    public function test_baileys_fake_webhook_simulation_cycle_sent_delivered_read(): void
    {
        $fake = BaileysFake::create();
        $msgId = '3EB0ABC123DEF456';
        $phone = '6281234567890';

        // Jalankan siklus centang penuh: SENT (2) -> DELIVERED (3) -> READ (4)
        $fake->simulateFullTickCycle($this, $msgId, $phone, true);

        $this->assertCount(3, $this->receivedWaWebhooks);

        // Assert Step 1: Centang 1 (SENT / 2)
        $this->assertEquals('message.update', $this->receivedWaWebhooks[0]['event']);
        $this->assertEquals($msgId, $this->receivedWaWebhooks[0]['wa_message_id']);
        $this->assertEquals('6281234567890@s.whatsapp.net', $this->receivedWaWebhooks[0]['remote_jid']);
        $this->assertEquals('SENT', $this->receivedWaWebhooks[0]['status']);
        $this->assertEquals(2, $this->receivedWaWebhooks[0]['status_code']);

        // Assert Step 2: Centang 2 Abu (DELIVERED / 3)
        $this->assertEquals('DELIVERED', $this->receivedWaWebhooks[1]['status']);
        $this->assertEquals(3, $this->receivedWaWebhooks[1]['status_code']);

        // Assert Step 3: Centang 2 Biru (READ / 4)
        $this->assertEquals('READ', $this->receivedWaWebhooks[2]['status']);
        $this->assertEquals(4, $this->receivedWaWebhooks[2]['status_code']);
    }

    /**
     * Verifikasi WhatsAppTestHelper dan InteractsWithWhatsApp trait.
     */
    public function test_whatsapp_test_helper_and_trait_integration(): void
    {
        $session = $this->withConnectedWhatsAppSession('session_test_1', '628111222333@s.whatsapp.net');
        $this->assertNotNull($session);

        $discSession = $this->withDisconnectedWhatsAppSession('session_test_1');
        $this->assertNotNull($discSession);

        $res = $this->wa()->fireConnectionWebhook('open');
        $this->assertTrue($res->isOk());

        $this->assertGreaterThanOrEqual(1, count($this->receivedWaWebhooks));
        $lastWebhook = end($this->receivedWaWebhooks);
        $this->assertEquals('connection.update', $lastWebhook['event']);
        $this->assertEquals('open', $lastWebhook['status']);
    }

    /**
     * Verifikasi TelegramTestHelper dan InteractsWithTelegram trait.
     */
    public function test_telegram_test_helper_and_trait_integration(): void
    {
        $this->fakeTelegramApi();

        // 1. Kirim notifikasi via Telegram API
        Http::post('https://api.telegram.org/botfake_token/sendMessage', [
            'chat_id' => 998877,
            'text' => 'Alert: Broadcast Campaign Started for 50 contacts',
        ]);

        $this->assertTelegramNotificationCount(1);
        $this->assertTelegramAdminNotificationSent('Broadcast Campaign Started');

        // 2. Generate token login & deep link simulation
        $token = $this->createTelegramLoginToken();
        $this->assertNotNull($token);
        $this->assertNotEmpty($token->token);

        $this->simulateTelegramDeepLink($token->token, 123456, 'tester_bot', 'Tester');
        $this->assertCount(1, $this->receivedTelegramWebhooks);
        $this->assertStringContainsString($token->token, $this->receivedTelegramWebhooks[0]['message']['text']);
    }

    /**
     * Verifikasi CampaignTestHelper dan InteractsWithCampaigns trait.
     */
    public function test_campaign_test_helper_and_trait_integration(): void
    {
        // 1. Seeding valid contacts
        $validContacts = $this->seedValidContacts(1, 4);
        $this->assertCount(4, $validContacts);
        $this->assertStringStartsWith('62812', $validContacts->first()->phone_number);

        // 2. Seeding mixed contacts (edge cases)
        $mixedContacts = $this->seedMixedContacts(1);
        $this->assertCount(4, $mixedContacts);

        // 3. Create campaign
        $campaign = $this->createCampaignWithContacts(1, 3, [
            'title' => 'Flash Sale Akhir Pekan',
        ]);
        $this->assertNotNull($campaign);
        $this->assertEquals('Flash Sale Akhir Pekan', $campaign->title);

        // 4. Process queue simulation
        $this->processCampaignQueue($campaign);
        $this->assertEquals('completed', $campaign->status);
    }

    /**
     * Verifikasi mock-baileys-server.js syntax dan kemampuan eksekusi HTTP-nya.
     */
    public function test_mock_baileys_server_script_and_execution(): void
    {
        $serverScript = base_path('tests/Support/mock-baileys-server.js');
        $this->assertFileExists($serverScript);

        // Temukan binary node
        $nodeBinary = $this->resolveNodeBinary();
        $this->assertNotEmpty($nodeBinary, 'Node.js executable must be available on the test environment.');

        // 1. Verifikasi syntax Node.js: node -c mock-baileys-server.js
        $syntaxCheck = shell_exec(sprintf('"%s" -c "%s" 2>&1', $nodeBinary, $serverScript));
        $this->assertEmpty($syntaxCheck, 'Syntax check of mock-baileys-server.js failed: ' . $syntaxCheck);

        // 2. Jalankan server pada test port ephemeral (port 3099)
        $testPort = 3099;
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        putenv("PORT={$testPort}");
        putenv("INTERNAL_SERVICE_KEY=jawirblast_internal_secret_key");
        putenv("LARAVEL_WEBHOOK_URL=http://127.0.0.1:8000/api/wa/webhook");

        $cmd = sprintf('"%s" "%s"', $nodeBinary, $serverScript);
        $process = proc_open($cmd, $descriptor, $pipes, base_path(), null);

        $this->assertIsResource($process, 'Failed to launch mock-baileys-server.js process');

        try {
            // Beri waktu 500ms server listening
            usleep(500000);

            // Test HTTP request ke mock server control endpoint
            $response = Http::timeout(3)->get("http://127.0.0.1:{$testPort}/mock/status");
            $this->assertTrue($response->successful(), 'Mock server did not respond on /mock/status');
            $this->assertTrue($response->json('success'));
            $this->assertEquals('CONNECTED', $response->json('state.connectionStatus'));

            // Test HTTP request ke mock server API endpoint dengan X-Service-Key
            $apiRes = Http::timeout(3)
                ->withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
                ->get("http://127.0.0.1:{$testPort}/api/session/status");
            $this->assertTrue($apiRes->successful(), 'Mock server did not respond on /api/session/status');
            $this->assertEquals('CONNECTED', $apiRes->json('status'));

            // Test message send endpoint
            $sendRes = Http::timeout(3)
                ->withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
                ->post("http://127.0.0.1:{$testPort}/api/message/send", [
                    'recipient_id' => 1,
                    'phone' => '628123456789',
                    'message' => 'Uji Coba Mock Server',
                ]);
            $this->assertTrue($sendRes->successful(), 'Mock server did not respond on /api/message/send');
            $this->assertEquals('SENT', $sendRes->json('status'));
            $this->assertStringStartsWith('3EB0', $sendRes->json('wa_message_id'));

            // Periksa riwayat pesan pada mock server
            $histRes = Http::timeout(3)->get("http://127.0.0.1:{$testPort}/mock/history");
            $this->assertEquals(1, $histRes->json('count'));
        } finally {
            // Matikan child process mock server
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    protected function resolveNodeBinary(): ?string
    {
        $candidates = [
            'node',
            'C:\\laragon\\bin\\nodejs\\node-v22\\node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
        ];

        foreach ($candidates as $candidate) {
            $check = @shell_exec(sprintf('"%s" -v 2>&1', $candidate));
            if ($check && str_starts_with(trim($check), 'v')) {
                return $candidate;
            }
        }

        return null;
    }
}
