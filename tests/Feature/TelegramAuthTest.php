<?php

namespace Tests\Feature;

use App\Models\TelegramLoginToken;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTelegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTelegramApi();

        Config::set('services.telegram.bot_token', '123456:TEST_BOT_TOKEN_JAWIRBLAST');
        Config::set('services.telegram.bot_username', 'JawirBlastBot');
        Config::set('services.telegram.admin_chat_id', '99887766');
    }

    /**
     * Test 1: Mode 1 - Pembuatan token login deep-link (GET /auth/telegram/token).
     */
    public function test_mode1_can_generate_login_token_and_deep_link(): void
    {
        $response = $this->getJson('/auth/telegram/token');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'token',
            'bot_username',
            'bot_url',
            'deep_link',
            'expires_at',
        ]);

        $token = $response->json('token');
        $this->assertDatabaseHas('telegram_login_tokens', [
            'token' => $token,
            'status' => 'pending',
        ]);
        $this->assertStringContainsString("weblogin_{$token}", $response->json('deep_link'));
    }

    /**
     * Test 2: Mode 1 - Polling token berstatus pending belum mengautentikasi (GET /auth/telegram/check/{token}).
     */
    public function test_mode1_polling_pending_token_returns_unauthenticated(): void
    {
        $tokenRecord = TelegramLoginToken::create([
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->getJson('/auth/telegram/check/' . $tokenRecord->token);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'pending',
            'authenticated' => false,
        ]);
        $this->assertGuest();
    }

    /**
     * Test 3: Mode 1 - Webhook Telegram menerima perintah /start weblogin_{token} dan menandai verified.
     */
    public function test_mode1_webhook_verifies_valid_token(): void
    {
        $tokenRecord = TelegramLoginToken::create([
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);

        $payload = [
            'update_id' => 10001,
            'message' => [
                'message_id' => 456,
                'from' => [
                    'id' => 123456789,
                    'is_bot' => false,
                    'first_name' => 'Jawir',
                    'username' => 'jawir_blast',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'text' => '/start weblogin_' . $tokenRecord->token,
            ],
        ];

        $response = $this->postJson('/api/telegram/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'action' => 'token_verified',
        ]);

        $tokenRecord->refresh();
        $this->assertEquals('verified', $tokenRecord->status);
        $this->assertEquals(123456789, $tokenRecord->telegram_id);
    }

    /**
     * Test 4: Mode 1 - Polling token verified melakukan login otomatis dan mengubah status ke used.
     */
    public function test_mode1_polling_verified_token_authenticates_user_and_marks_used(): void
    {
        $tokenRecord = TelegramLoginToken::create([
            'token' => Str::random(32),
            'telegram_id' => 123456789,
            'status' => 'verified',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->getJson('/auth/telegram/check/' . $tokenRecord->token);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'verified',
            'authenticated' => true,
        ]);

        $this->assertAuthenticated();
        $tokenRecord->refresh();
        $this->assertEquals('used', $tokenRecord->status);
    }

    /**
     * Test 5: Mode 1 - Penolakan polling token yang kedaluwarsa (expired).
     */
    public function test_mode1_token_rejected_when_expired(): void
    {
        $expiredToken = TelegramLoginToken::create([
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/auth/telegram/check/' . $expiredToken->token);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'status' => 'expired',
        ]);
    }

    /**
     * Test 6: Mode 1 - Webhook menolak token kedaluwarsa dengan status HTTP 410.
     */
    public function test_mode1_webhook_rejects_expired_token(): void
    {
        $expiredToken = TelegramLoginToken::create([
            'token' => Str::random(32),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $payload = [
            'update_id' => 10002,
            'message' => [
                'message_id' => 457,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Jawir',
                ],
                'chat' => [
                    'id' => 123456789,
                ],
                'text' => '/start weblogin_' . $expiredToken->token,
            ],
        ];

        $response = $this->postJson('/api/telegram/webhook', $payload);

        $response->assertStatus(410);
        $response->assertJson([
            'ok' => false,
            'error' => 'Token has expired',
        ]);
    }

    /**
     * Test 7: Mode 1 - Webhook menolak token fiktif dengan status HTTP 404.
     */
    public function test_mode1_webhook_rejects_unknown_token(): void
    {
        $payload = [
            'update_id' => 10003,
            'message' => [
                'message_id' => 458,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Jawir',
                ],
                'chat' => [
                    'id' => 123456789,
                ],
                'text' => '/start weblogin_token_fiktif_yang_tidak_ada_123',
            ],
        ];

        $response = $this->postJson('/api/telegram/webhook', $payload);

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => 'Token not found',
        ]);
    }

    /**
     * Test 8: Mode 2 - Callback widget resmi Telegram dengan signature HMAC valid berhasil login.
     */
    public function test_mode2_widget_callback_succeeds_with_valid_signature(): void
    {
        $botToken = '123456:TEST_BOT_TOKEN_JAWIRBLAST';
        $authData = [
            'auth_date' => (string) time(),
            'first_name' => 'Agus',
            'id' => '987654321',
            'username' => 'agus_jawir',
        ];

        // Hitung signature resmi Telegram widget
        $dataCheckArr = [];
        foreach ($authData as $k => $v) {
            $dataCheckArr[] = "{$k}={$v}";
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash('sha256', $botToken, true);
        $validHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $queryParams = array_merge($authData, ['hash' => $validHash]);

        $response = $this->get('/auth/telegram/callback?' . http_build_query($queryParams));

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'telegram_id' => 987654321,
            'telegram_username' => 'agus_jawir',
        ]);
    }

    /**
     * Test 9: Mode 2 - Callback widget dengan signature palsu/dimanipulasi ditolak.
     */
    public function test_mode2_widget_callback_fails_with_tampered_hash(): void
    {
        $queryParams = [
            'id' => '987654321',
            'first_name' => 'Hacker',
            'username' => 'hacker_man',
            'auth_date' => (string) time(),
            'hash' => 'palsu_abcdef1234567890abcdef1234567890',
        ];

        $response = $this->get('/auth/telegram/callback?' . http_build_query($queryParams));

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Test 10: Mode 2 - Callback widget dengan auth_date kadaluwarsa (> 24 jam) ditolak.
     */
    public function test_mode2_widget_callback_fails_when_auth_date_expired(): void
    {
        $botToken = '123456:TEST_BOT_TOKEN_JAWIRBLAST';
        $authData = [
            'auth_date' => (string) (time() - 90000), // > 24 jam yang lalu
            'first_name' => 'Kuno',
            'id' => '11223344',
        ];

        $dataCheckArr = [];
        foreach ($authData as $k => $v) {
            $dataCheckArr[] = "{$k}={$v}";
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash('sha256', $botToken, true);
        $validHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $queryParams = array_merge($authData, ['hash' => $validHash]);

        $response = $this->get('/auth/telegram/callback?' . http_build_query($queryParams));

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Test 11: TelegramService mengirim notifikasi admin 4-tahap tanpa exception.
     */
    public function test_telegram_service_admin_notifications_flow(): void
    {
        $service = app(TelegramService::class);

        // Tahap 1: Broadcast Start
        $sent1 = $service->notifyBroadcastStart('Promo Gajian', 150, now());
        $this->assertTrue($sent1);

        // Tahap 2: Progress Update
        $sent2 = $service->notifyBroadcastProgress('Promo Gajian', 75, 150, 70, 50, 20, 5);
        $this->assertTrue($sent2);

        // Tahap 3: Broadcast Completed
        $sent3 = $service->notifyBroadcastCompleted('Promo Gajian', 150, 145, 5, '2m 15s');
        $this->assertTrue($sent3);

        // Tahap 4: Session Dropped Warning
        $sent4 = $service->notifySessionDropped('session_main', 'Koneksi WhatsApp terputus dari perangkat.');
        $this->assertTrue($sent4);

        $this->assertTelegramAdminNotificationSent('Promo Gajian');
        $this->assertTelegramAdminNotificationSent('Koneksi WhatsApp terputus');
    }

    /**
     * Test 12: TelegramService graceful fallback saat kredensial kosong.
     */
    public function test_telegram_service_graceful_fallback_when_credentials_missing(): void
    {
        Config::set('services.telegram.bot_token', null);
        Config::set('services.telegram.admin_chat_id', null);

        $result = TelegramService::sendMessage('12345', 'Pesan Test');
        $this->assertFalse($result);

        $resultStart = TelegramService::notifyBroadcastStart('Test', 10);
        $this->assertFalse($resultStart);
    }

    /**
     * Test 13: Mode 1 - Penolakan konsumsi token ganda (double consumption / race condition protection).
     */
    public function test_mode1_polling_verified_token_rejects_duplicate_consumption(): void
    {
        $tokenRecord = TelegramLoginToken::create([
            'token' => Str::random(32),
            'telegram_id' => 888777666,
            'telegram_username' => 'jawir_concurrency',
            'status' => 'verified',
            'expires_at' => now()->addMinutes(15),
        ]);

        // Panggilan pertama: harus sukses 200 dan mengautentikasi user
        $response1 = $this->getJson('/auth/telegram/check/' . $tokenRecord->token);
        $response1->assertStatus(200);
        $response1->assertJson([
            'success' => true,
            'status' => 'verified',
            'authenticated' => true,
        ]);
        $this->assertAuthenticated();

        // Pastikan status di database sudah berubah jadi 'used'
        $tokenRecord->refresh();
        $this->assertEquals('used', $tokenRecord->status);

        // Logout untuk menguji konsumsi kedua secara independen
        $this->post('/logout');
        $this->assertGuest();

        // Panggilan kedua: harus ditolak HTTP 400 dengan status 'used'
        $response2 = $this->getJson('/auth/telegram/check/' . $tokenRecord->token);
        $response2->assertStatus(400);
        $response2->assertJson([
            'success' => false,
            'status' => 'used',
        ]);
        $this->assertGuest();
    }

    /**
     * Test 14: Mode 1 - Atomic Conditional Update menangani simulasi balapan (race condition).
     */
    public function test_mode1_atomic_conditional_update_prevents_simulated_race_condition(): void
    {
        $tokenRecord = TelegramLoginToken::create([
            'token' => 'RACE_TEST_' . Str::random(20),
            'telegram_id' => 999888777,
            'telegram_username' => 'race_tester',
            'status' => 'verified',
            'expires_at' => now()->addMinutes(15),
        ]);

        // Simulasi balapan konkurensi: proses pesaing telah mengklaim token di basis data menjadi 'used'
        TelegramLoginToken::where('id', $tokenRecord->id)
            ->where('status', 'verified')
            ->update(['status' => 'used', 'updated_at' => now()]);

        // Proses B mencoba memvalidasi token yang baru saja diklaim
        $response = $this->getJson('/auth/telegram/check/' . $tokenRecord->token);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'status' => 'used',
        ]);
        $this->assertGuest();
    }
}
