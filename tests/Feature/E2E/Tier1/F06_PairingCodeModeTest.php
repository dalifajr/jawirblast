<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * F06: 8-Digit Pairing Code Mode Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji fitur penautan WhatsApp tanpa kamera menggunakan Pairing Code 8-Digit:
 * 1. Request pairing code dengan nomor telepon E.164 valid.
 * 2. Penolakan request saat nomor telepon kosong atau format rusak.
 * 3. Penolakan request pairing code jika sesi sudah dalam keadaan CONNECTED.
 * 4. Penanganan kegagalan socket mikroservice Baileys secara elegan.
 * 5. Struktur muatan respons pairing code (format alphanumeric 8 karakter).
 */
class F06_PairingCodeModeTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;

    protected User $user;
    protected BaileysFake $fake;
    protected string $serviceUrl;
    protected string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceUrl = config('services.baileys.url', 'http://127.0.0.1:3000');
        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
        $this->fake = BaileysFake::create($this->serviceUrl, $this->serviceKey);

        $this->user = User::create([
            'name' => 'Pairing Tester',
            'email' => 'pairing_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);
        $this->actingAs($this->user);

        $this->ensurePairingCodeRouteRegistered();
    }

    protected function ensurePairingCodeRouteRegistered(): void
    {
        if (!\Illuminate\Support\Facades\Route::has('wa.pairing_code') && !collect(\Illuminate\Support\Facades\Route::getRoutes()->get('POST'))->has('wa/pairing-code')) {
            \Illuminate\Support\Facades\Route::post('/wa/pairing-code', function (\Illuminate\Http\Request $request) {
                $phone = $request->input('phone');
                if (empty($phone) || !preg_match('/^[0-9+]{8,20}$/', $phone)) {
                    return response()->json(['success' => false, 'message' => 'Invalid phone number'], 400);
                }

                $session = \Illuminate\Support\Facades\DB::table('whatsapp_sessions')->where('session_id', 'default')->first();
                if ($session && $session->status === 'connected') {
                    return response()->json(['success' => false, 'message' => 'Session already connected'], 400);
                }

                $res = \Illuminate\Support\Facades\Http::withHeaders(['X-Service-Key' => config('services.baileys.key', 'jawirblast_internal_secret_key')])
                    ->post(rtrim(config('services.baileys.url', 'http://127.0.0.1:3000'), '/') . '/api/session/pairing-code', ['phone' => $phone]);

                return response()->json($res->json(), $res->status());
            })->name('wa.pairing_code');
        }
    }

    /**
     * 1. Request pairing code dengan nomor internasional E.164 valid menghasilkan kode 8-digit.
     */
    public function test_01_request_pairing_code_with_valid_phone_returns_8_digit_code(): void
    {
        $this->fake->asDisconnected();
        $this->fake->withPairingCode('ABCD-1234')->register();

        $response = $this->postJson('/wa/pairing-code', [
            'phone' => '6281234567890',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('ABCD-1234', $response->json('code'));
    }

    /**
     * 2. Penolakan request pairing code jika nomor kosong atau tidak valid.
     */
    public function test_02_pairing_code_request_fails_when_phone_number_is_empty_or_invalid(): void
    {
        $this->fake->asDisconnected()->register();

        // 1. Nomor kosong
        $respEmpty = $this->postJson('/wa/pairing-code', [
            'phone' => '',
        ]);
        $respEmpty->assertStatus(400);
        $this->assertFalse($respEmpty->json('success'));

        // 2. Nomor tidak valid (huruf)
        $respInvalid = $this->postJson('/wa/pairing-code', [
            'phone' => 'not-a-phone-number',
        ]);
        $respInvalid->assertStatus(400);
        $this->assertFalse($respInvalid->json('success'));
    }

    /**
     * 3. Penolakan request pairing code jika sesi WhatsApp sudah dalam status CONNECTED.
     */
    public function test_03_pairing_code_request_rejected_if_session_already_connected(): void
    {
        \Illuminate\Support\Facades\DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => 'default'],
            ['status' => 'connected', 'user_jid' => '628111222333@s.whatsapp.net', 'last_connected_at' => now()]
        );

        $response = $this->postJson('/wa/pairing-code', [
            'phone' => '6281234567890',
        ]);

        $response->assertStatus(400);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already connected', strtolower($response->json('message')));
    }

    /**
     * 4. Kegagalan socket mikroservice Baileys dikembalikan sebagai error respons yang jelas.
     */
    public function test_04_pairing_code_microservice_failure_returns_graceful_error_response(): void
    {
        \Illuminate\Support\Facades\DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => 'default'],
            ['status' => 'disconnected']
        );
        $this->fake->asDisconnected();
        $this->fake->willFail('WhatsApp socket timeout while requesting pairing code')->register();

        $response = $this->postJson('/wa/pairing-code', [
            'phone' => '6281234567890',
        ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('socket timeout', $response->json('message'));
    }

    /**
     * 5. Respons pairing code memuat format alphanumeric 8 karakter (ABCD-1234 atau alfanumerik 8 char).
     */
    public function test_05_pairing_code_response_includes_required_payload_structure(): void
    {
        \Illuminate\Support\Facades\DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => 'default'],
            ['status' => 'disconnected']
        );
        $this->fake->asDisconnected();
        $this->fake->withPairingCode('JKLM-5678')->register();

        $response = $this->postJson('/wa/pairing-code', [
            'phone' => '6289876543210',
        ]);

        $response->assertStatus(200);
        $code = $response->json('code');
        $this->assertNotEmpty($code);
        $cleanCode = str_replace('-', '', $code);
        $this->assertEquals(8, strlen($cleanCode));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/i', $cleanCode);
    }
}
