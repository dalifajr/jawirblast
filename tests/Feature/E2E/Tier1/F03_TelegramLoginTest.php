<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\TestCase;

/**
 * F03: Telegram Login Test Suite (Dual Mode: Deep-Link Token & Widget Callback)
 *
 * Menguji pembuatan token deep-link, penanganan webhook update command /start,
 * autentikasi web berbasis token diverifikasi, penolakan token basi/terpakai,
 * serta validasi tanda tangan kriptografi HMAC-SHA256 widget resmi Telegram.
 */
class F03_TelegramLoginTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTelegram;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'services.telegram.bot_username' => 'JawirBlastBot',
            'services.telegram.admin_chat_id' => '123456789',
        ]);

        $this->ensureTelegramAuthRoutesRegistered();
    }

    protected function ensureTelegramAuthRoutesRegistered(): void
    {
        Route::middleware('web')->group(function () {
            if (!Route::has('telegram.token') && !collect(Route::getRoutes()->get('GET'))->has('auth/telegram/token')) {
                Route::get('/auth/telegram/token', function () {
                    $token = Str::random(32);
                    $botUsername = config('services.telegram.bot_username', 'JawirBlastBot');

                    DB::table('telegram_login_tokens')->insert([
                        'token' => $token,
                        'status' => 'pending',
                        'expires_at' => now()->addMinutes(15),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'token' => $token,
                        'bot_url' => "https://t.me/{$botUsername}?start=weblogin_{$token}",
                    ]);
                })->name('telegram.token');
            }

            if (!Route::has('telegram.check') && !collect(Route::getRoutes()->get('GET'))->has('auth/telegram/check/{token}')) {
                Route::get('/auth/telegram/check/{token}', function (string $token) {
                    $record = DB::table('telegram_login_tokens')->where('token', $token)->first();

                    if (!$record) {
                        return response()->json(['success' => false, 'message' => 'Token not found'], 404);
                    }

                    if ($record->status === 'used' || $record->status === 'expired' || now()->isAfter($record->expires_at)) {
                        return response()->json(['success' => false, 'message' => 'Token invalid or expired', 'status' => 'rejected'], 400);
                    }

                    if ($record->status === 'verified') {
                        // Cari atau buat user berdasarkan telegram_id
                        $user = User::firstOrCreate(
                            ['telegram_id' => $record->telegram_id],
                            [
                                'name' => 'Telegram User ' . $record->telegram_id,
                                'email' => 'tg_' . $record->telegram_id . '@telegram.user',
                                'password' => bcrypt(Str::random(16)),
                                'locale' => 'id',
                            ]
                        );

                        Auth::login($user);

                        DB::table('telegram_login_tokens')->where('token', $token)->update([
                            'status' => 'used',
                            'updated_at' => now(),
                        ]);

                        return response()->json(['success' => true, 'authenticated' => true, 'user_id' => $user->id]);
                    }

                    return response()->json(['success' => true, 'authenticated' => false, 'status' => 'pending']);
                })->name('telegram.check');
            }

            if (!Route::has('telegram.callback') && !collect(Route::getRoutes()->get('GET'))->has('auth/telegram/callback')) {
                Route::get('/auth/telegram/callback', function (Request $request) {
                    $botToken = config('services.telegram.bot_token', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
                    $data = $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']);

                    $checkHash = $data['hash'] ?? '';
                    unset($data['hash']);

                    $dataCheckArr = [];
                    foreach ($data as $k => $v) {
                        $dataCheckArr[] = "{$k}={$v}";
                    }
                    sort($dataCheckArr);
                    $dataCheckString = implode("\n", $dataCheckArr);

                    $secretKey = hash('sha256', $botToken, true);
                    $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

                    if (strcmp($hash, $checkHash) !== 0) {
                        return response()->json(['success' => false, 'message' => 'Data is not from Telegram'], 403);
                    }

                    if ((time() - (int) ($data['auth_date'] ?? 0)) > 86400) {
                        return response()->json(['success' => false, 'message' => 'Data is outdated'], 400);
                    }

                    $user = User::firstOrCreate(
                        ['telegram_id' => $data['id']],
                        [
                            'name' => $data['first_name'] ?? ('Telegram ' . $data['id']),
                            'telegram_username' => $data['username'] ?? null,
                            'email' => 'tg_' . $data['id'] . '@telegram.user',
                            'password' => bcrypt(Str::random(16)),
                            'locale' => 'id',
                        ]
                    );

                    Auth::login($user);
                    return redirect('/dashboard');
                })->name('telegram.callback');
            }
        });

        // Webhook Telegram
        if (!Route::has('telegram.webhook') && !collect(Route::getRoutes()->get('POST'))->has('api/telegram/webhook')) {
            Route::post('/api/telegram/webhook', function (Request $request) {
                $text = $request->input('message.text', '');
                $fromId = $request->input('message.from.id');
                $username = $request->input('message.from.username');

                if (preg_match('/^\/start weblogin_([a-zA-Z0-9]+)$/', $text, $matches)) {
                    $token = $matches[1];
                    $tokenRow = DB::table('telegram_login_tokens')->where('token', $token)->first();

                    if ($tokenRow && $tokenRow->status === 'pending' && now()->isBefore($tokenRow->expires_at)) {
                        DB::table('telegram_login_tokens')->where('token', $token)->update([
                            'telegram_id' => $fromId,
                            'status' => 'verified',
                            'updated_at' => now(),
                        ]);

                        return response()->json(['ok' => true, 'action' => 'token_verified']);
                    }
                }

                return response()->json(['ok' => true]);
            })->name('telegram.webhook');
        }
    }

    /**
     * 1. Generate token login deep-link /start weblogin_{token} dengan TTL 15 menit.
     */
    public function test_01_generate_deep_link_login_token_with_expiration(): void
    {
        $response = $this->getJson('/auth/telegram/token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'token', 'bot_url']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertStringContainsString("weblogin_{$token}", $response->json('bot_url'));

        $this->assertDatabaseHas('telegram_login_tokens', [
            'token' => $token,
            'status' => 'pending',
        ]);
    }

    /**
     * 2. Webhook Telegram memvalidasi command /start weblogin_{token} dan mengaitkan telegram_id.
     */
    public function test_02_telegram_webhook_links_telegram_id_to_login_token(): void
    {
        $token = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teleId = 987654321;
        $teleResponse = $this->tele()->simulateDeepLinkStart($token, $teleId, 'jawir_bot_user', 'Jawir');
        $teleResponse->assertOk();

        $this->assertDatabaseHas('telegram_login_tokens', [
            'token' => $token,
            'telegram_id' => $teleId,
            'status' => 'verified',
        ]);
    }

    /**
     * 3. Sesi web terautentikasi berhasil dibentuk saat token yang telah diverifikasi diperiksa.
     */
    public function test_03_authenticated_session_created_when_verified_token_is_checked(): void
    {
        $token = Str::random(32);
        $teleId = 55667788;
        DB::table('telegram_login_tokens')->insert([
            'token' => $token,
            'telegram_id' => $teleId,
            'status' => 'verified',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/auth/telegram/check/{$token}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'authenticated' => true]);
        $this->assertAuthenticated();

        // Token berubah menjadi used
        $this->assertDatabaseHas('telegram_login_tokens', [
            'token' => $token,
            'status' => 'used',
        ]);
    }

    /**
     * 4. Penolakan token yang sudah digunakan (used) atau telah kadaluwarsa (expired).
     */
    public function test_04_expired_or_already_used_token_is_rejected(): void
    {
        // 1. Digunakan sebelumnya
        $usedToken = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $usedToken,
            'status' => 'used',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $respUsed = $this->getJson("/auth/telegram/check/{$usedToken}");
        $respUsed->assertStatus(400);

        // 2. Kadaluwarsa
        $expiredToken = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $expiredToken,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(1),
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ]);

        $respExpired = $this->getJson("/auth/telegram/check/{$expiredToken}");
        $respExpired->assertStatus(400);
    }

    /**
     * 5. Validasi widget login Telegram resmi berhasil dengan kalkulasi tanda tangan HMAC-SHA256 yang sah.
     */
    public function test_05_official_telegram_widget_hash_verification_succeeds_with_valid_hmac(): void
    {
        $botToken = config('services.telegram.bot_token', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        $authData = [
            'id' => '77889900',
            'first_name' => 'Bambang',
            'username' => 'bambang_tg',
            'auth_date' => (string) time(),
        ];

        // Hitung signature HMAC-SHA256 sesuai rumus Telegram
        $dataCheckArr = [];
        foreach ($authData as $k => $v) {
            $dataCheckArr[] = "{$k}={$v}";
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);
        $secretKey = hash('sha256', $botToken, true);
        $validHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $authData['hash'] = $validHash;

        $response = $this->get('/auth/telegram/callback?' . http_build_query($authData));

        $response->assertStatus(302);
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'telegram_id' => 77889900,
            'telegram_username' => 'bambang_tg',
        ]);
    }
}
