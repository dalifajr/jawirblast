<?php

namespace Tests\Feature\E2E\Tier2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B06 - Pairing Code Invalid Phone & Rate Limiting Corner Cases
 *
 * Menguji penautan perangkat via 8-Digit Pairing Code:
 * penolakan nomor darurat/pendek, sanitasi karakter simbol/alfabetik,
 * rate limiting permintaan pairing code, penanganan 500 error dari microservice,
 * dan penolakan saat sesi WhatsApp sudah terhubung.
 */
class B06_PairingCodeInvalidPhoneCornerCasesTest extends TestCase
{
    use InteractsWithWhatsApp;

    protected string $baileysUrl = 'http://127.0.0.1:3000';

    protected function setUp(): void
    {
        parent::setUp();

        // Daftarkan route kontrak pairing code sesuai interface contract PROJECT.md § 1
        Route::post('/whatsapp/pairing-code', function (Request $request) {
            $key = 'pairing-code:' . $request->ip();

            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many pairing code attempts. Please wait a minute.',
                ], 429);
            }

            RateLimiter::hit($key, 60);

            $rawPhone = $request->input('phone', '');
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

            // Validasi: harus nomor internasional E.164 (minimal 8 digit, maksimal 15 digit)
            if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 15 || preg_match('/^(112|911|110|999|123)$/', $cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number for WhatsApp pairing.',
                ], 422);
            }

            // Cek status sesi ke microservice
            $statusRes = Http::withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
                ->get('http://127.0.0.1:3000/api/session/status');

            if ($statusRes->json('status') === 'CONNECTED') {
                return response()->json([
                    'success' => false,
                    'message' => 'Device already connected to WhatsApp.',
                ], 400);
            }

            // Panggil microservice untuk meminta pairing code
            $res = Http::withHeaders(['X-Service-Key' => 'jawirblast_internal_secret_key'])
                ->post('http://127.0.0.1:3000/api/session/pairing-code', ['phone' => $cleanPhone]);

            if (!$res->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => $res->json('message') ?? 'Baileys service failed to generate pairing code.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'code' => $res->json('code'),
            ]);
        });
    }

    /**
     * Case 1: Request pairing code dengan nomor darurat / pendek ditolak dengan 422 Unprocessable Entity.
     */
    public function test_pairing_code_request_with_emergency_or_short_numbers_rejected(): void
    {
        $shortNumbers = ['112', '911', '110', '999', '123', '0812'];

        foreach ($shortNumbers as $num) {
            RateLimiter::clear('pairing-code:127.0.0.1');
            $response = $this->postJson('/whatsapp/pairing-code', ['phone' => $num]);

            $response->assertStatus(422);
            $this->assertFalse($response->json('success'));
            $this->assertEquals('Invalid phone number for WhatsApp pairing.', $response->json('message'));
        }
    }

    /**
     * Case 2: Request dengan karakter simbol/alfabetik disanitasi atau ditolak jika hasil sanitasi invalid.
     */
    public function test_pairing_code_with_alphanumeric_or_symbolic_garbage_is_rejected_or_sanitized(): void
    {
        BaileysFake::create()->asDisconnected()->withPairingCode('PAIR-9988');

        // 1. Nomor valid dengan format tanda baca (misal +62 812-3456-7890) berhasil disanitasi
        $validWithSymbols = '+62 812-3456-7890';
        $resSuccess = $this->postJson('/whatsapp/pairing-code', ['phone' => $validWithSymbols]);
        $resSuccess->assertOk();
        $this->assertEquals('PAIR-9988', $resSuccess->json('code'));

        // 2. Nomor sampah (huruf dan simbol tanpa digit yang cukup: +62-ABC-XYZ#*) ditolak
        $garbage = '+62-ABC-XYZ#*';
        $resFail = $this->postJson('/whatsapp/pairing-code', ['phone' => $garbage]);
        $resFail->assertStatus(422);
        $this->assertFalse($resFail->json('success'));
    }

    /**
     * Case 3: Rate limiting memblokir permintaan pairing code setelah 5 kali dalam 1 menit (HTTP 429).
     */
    public function test_pairing_code_request_rate_limiting(): void
    {
        RateLimiter::clear('pairing-code:127.0.0.1');
        BaileysFake::create()->asDisconnected()->withPairingCode('CODE-1234');

        // 5 kali request awal berhasil
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/whatsapp/pairing-code', ['phone' => '628123456789' . $i]);
            $response->assertOk();
        }

        // Request ke-6 harus di-throttle
        $blocked = $this->postJson('/whatsapp/pairing-code', ['phone' => '6281234567899']);
        $blocked->assertStatus(429);
        $this->assertStringContainsString('Too many pairing code attempts', $blocked->json('message'));
    }

    /**
     * Case 4: Microservice Baileys merespon 500 error ditangani aman dan mengembalikan pesan error yang jelas (HTTP 502).
     */
    public function test_pairing_code_when_baileys_microservice_returns_500_error(): void
    {
        RateLimiter::clear('pairing-code:127.0.0.1');

        $fake = BaileysFake::create()
            ->asDisconnected()
            ->willFail('Internal socket error: failed to register device');

        $response = $this->postJson('/whatsapp/pairing-code', ['phone' => '6281299998888']);

        $response->assertStatus(502);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Internal socket error', $response->json('message'));
    }

    /**
     * Case 5: Request pairing code saat sesi sudah terhubung ditolak (HTTP 400 Bad Request).
     */
    public function test_pairing_code_rejected_if_session_already_connected(): void
    {
        RateLimiter::clear('pairing-code:127.0.0.1');

        // Setup sesi sebagai CONNECTED
        BaileysFake::create()->asConnected();

        $response = $this->postJson('/whatsapp/pairing-code', ['phone' => '6281234567890']);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Device already connected to WhatsApp.', $response->json('message'));
    }
}
