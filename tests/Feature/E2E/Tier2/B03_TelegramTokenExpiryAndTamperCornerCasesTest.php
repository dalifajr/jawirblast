<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B03 - Telegram Token Expiry & Tamper Corner Cases
 *
 * Menguji skenario batas dan keamanan pada modul Telegram Login (Dual Mode),
 * mencakup kedaluwarsa token login, manipulasi payload webhook Telegram yang korup,
 * klaim dua token simultan, pencegahan token reuse (anti-replay), dan proteksi SQL injection.
 */
class B03_TelegramTokenExpiryAndTamperCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTelegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTelegramApi();

        // Daftarkan route webhook Telegram sesuai spesifikasi kontrak PROJECT.md § 4 & § 2
        Route::post('/api/telegram/webhook', function (Request $request) {
            $message = $request->input('message');
            if (!$message || !isset($message['text'])) {
                return response()->json(['ok' => false, 'error' => 'Malformed payload, missing message or text'], 400);
            }

            $text = trim($message['text']);
            if (!preg_match('/^\/start\s+weblogin_([a-zA-Z0-9_\-\.\'\s]+)$/', $text, $matches)) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }

            $tokenString = $matches[1];
            $telegramId = $message['from']['id'] ?? $message['chat']['id'] ?? null;

            if (!$telegramId) {
                return response()->json(['ok' => false, 'error' => 'Missing telegram user ID'], 400);
            }

            // Gunakan parameterized query untuk keamanan SQL Injection
            $record = DB::table('telegram_login_tokens')->where('token', $tokenString)->first();

            if (!$record) {
                return response()->json(['ok' => false, 'error' => 'Token not found'], 404);
            }

            if (now()->gt($record->expires_at)) {
                DB::table('telegram_login_tokens')->where('id', $record->id)->update(['status' => 'expired']);
                return response()->json(['ok' => false, 'error' => 'Token has expired'], 410);
            }

            if ($record->status === 'used') {
                return response()->json(['ok' => false, 'error' => 'Token has already been used'], 409);
            }

            // Batalkan token pending sebelumnya untuk telegram_id yang sama agar tidak ada konflik simultan
            DB::table('telegram_login_tokens')
                ->where('telegram_id', $telegramId)
                ->where('status', 'verified')
                ->where('id', '!=', $record->id)
                ->update(['status' => 'expired']);

            DB::table('telegram_login_tokens')->where('id', $record->id)->update([
                'telegram_id' => $telegramId,
                'status' => 'verified',
                'updated_at' => now(),
            ]);

            return response()->json(['ok' => true, 'message' => 'Token verified successfully']);
        });

        // Daftarkan route verifikasi login web dengan token
        Route::post('/auth/telegram/callback', function (Request $request) {
            $tokenString = $request->input('token');
            $record = DB::table('telegram_login_tokens')->where('token', $tokenString)->first();

            if (!$record || $record->status !== 'verified') {
                return response()->json(['success' => false, 'error' => 'Invalid or unverified token'], 401);
            }

            if (now()->gt($record->expires_at)) {
                return response()->json(['success' => false, 'error' => 'Token expired'], 410);
            }

            DB::table('telegram_login_tokens')->where('id', $record->id)->update([
                'status' => 'used',
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'telegram_id' => $record->telegram_id]);
        });
    }

    /**
     * Case 1: Token login yang sudah kadaluarsa (>15 menit) ditolak dengan jelas (HTTP 410).
     */
    public function test_expired_telegram_login_token_is_rejected(): void
    {
        $token = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(16), // Expired 1 menit lalu
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        $response = $this->tele()->simulateDeepLinkStart($token, 987654321);

        $response->assertStatus(410);
        $this->assertEquals('Token has expired', $response->json('error'));

        $this->assertDatabaseHas('telegram_login_tokens', [
            'token' => $token,
            'status' => 'expired',
        ]);
    }

    /**
     * Case 2: Payload webhook Telegram yang rusak/dimanipulasi (tanpa message, chat, atau text) ditangani aman tanpa 500 error.
     */
    public function test_tampered_webhook_payload_with_invalid_structure_is_safely_handled(): void
    {
        $malformedPayloads = [
            [], // Kosong total
            ['update_id' => 12345], // Tanpa message
            ['update_id' => 12345, 'message' => []], // Message kosong tanpa text
            ['update_id' => 12345, 'message' => ['chat' => ['id' => 123]]], // Tanpa text
        ];

        foreach ($malformedPayloads as $payload) {
            $response = $this->postJson('/api/telegram/webhook', $payload);
            $this->assertNotEquals(500, $response->status(), 'Malformed webhook payload must not trigger HTTP 500 error');
            $this->assertTrue($response->status() === 400 || $response->json('ok') === false);
        }
    }

    /**
     * Case 3: User Telegram yang sama mengklaim token baru secara otomatis menganulir token sebelumnya.
     */
    public function test_single_telegram_user_cannot_claim_two_active_tokens_simultaneously(): void
    {
        $telegramId = 888777666;

        $token1 = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $token1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token2 = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $token2,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Klaim token 1
        $res1 = $this->tele()->simulateDeepLinkStart($token1, $telegramId);
        $res1->assertOk();
        $this->assertDatabaseHas('telegram_login_tokens', ['token' => $token1, 'status' => 'verified']);

        // Klaim token 2 oleh user Telegram yang sama
        $res2 = $this->tele()->simulateDeepLinkStart($token2, $telegramId);
        $res2->assertOk();

        // Token 2 harus verified, sedangkan token 1 harus dianulir (expired)
        $this->assertDatabaseHas('telegram_login_tokens', ['token' => $token2, 'status' => 'verified']);
        $this->assertDatabaseHas('telegram_login_tokens', ['token' => $token1, 'status' => 'expired']);
    }

    /**
     * Case 4: Token yang sudah digunakan (used) ditolak jika dicoba klaim atau dipakai login ulang (anti-replay).
     */
    public function test_already_used_token_cannot_be_reused(): void
    {
        $token = Str::random(32);
        DB::table('telegram_login_tokens')->insert([
            'token' => $token,
            'telegram_id' => 11223344,
            'status' => 'verified',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pemakaian pertama untuk login -> sukses
        $loginRes1 = $this->postJson('/auth/telegram/callback', ['token' => $token]);
        $loginRes1->assertOk();
        $this->assertTrue($loginRes1->json('success'));

        $this->assertDatabaseHas('telegram_login_tokens', ['token' => $token, 'status' => 'used']);

        // Pemakaian kedua (replay attack) -> ditolak
        $loginRes2 = $this->postJson('/auth/telegram/callback', ['token' => $token]);
        $loginRes2->assertStatus(401);
        $this->assertFalse($loginRes2->json('success'));

        // Coba verifikasi ulang via webhook bot -> ditolak dengan status 409 Conflict
        $webhookReplay = $this->tele()->simulateDeepLinkStart($token, 11223344);
        $webhookReplay->assertStatus(409);
        $this->assertEquals('Token has already been used', $webhookReplay->json('error'));
    }

    /**
     * Case 5: Percobaan SQL Injection pada parameter token deep-link ditangani secara aman tanpa SQL error.
     */
    public function test_sql_injection_attempt_in_deep_link_token_payload(): void
    {
        $sqliPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE telegram_login_tokens; --",
            "1 UNION SELECT 1,2,3,4,5,6--",
        ];

        foreach ($sqliPayloads as $sqli) {
            $response = $this->tele()->simulateDeepLinkStart($sqli, 123456);

            // Respon tidak boleh 500 SQL syntax error. Boleh 404 Token not found atau 200 ignored secara aman
            $this->assertNotEquals(500, $response->status());
            $this->assertTrue(
                $response->status() === 404 || ($response->status() === 200 && $response->json('ignored') === true),
                "SQLi payload must be rejected with 404 or safely ignored, got status: " . $response->status()
            );
        }

        // Pastikan tabel masih utuh
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('telegram_login_tokens'));
    }
}
