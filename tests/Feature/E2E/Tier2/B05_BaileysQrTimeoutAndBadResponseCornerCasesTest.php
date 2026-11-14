<?php

namespace Tests\Feature\E2E\Tier2;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B05 - Baileys QR Timeout & Bad Response Corner Cases
 *
 * Menguji skenario batas pada interaksi mikroservice Baileys (Port 3000):
 * koneksi ditolak (ECONNREFUSED), Base64 QR code korup / malformed,
 * polling QR saat status sudah CONNECTED (HTTP 400), header X-Service-Key tidak valid (HTTP 401),
 * dan penanganan timeout mikroservice saat inisialisasi socket.
 */
class B05_BaileysQrTimeoutAndBadResponseCornerCasesTest extends TestCase
{
    use InteractsWithWhatsApp;

    protected string $baileysUrl = 'http://127.0.0.1:3000';
    protected string $validServiceKey = 'jawirblast_internal_secret_key';

    /**
     * Helper untuk memanggil Baileys client dengan perlindungan exception.
     */
    protected function callBaileysEndpoint(string $endpoint, string $method = 'GET', array $data = [], ?string $customKey = null): array
    {
        $key = $customKey ?? $this->validServiceKey;

        try {
            $request = Http::timeout(2)->withHeaders([
                'X-Service-Key' => $key,
                'Content-Type' => 'application/json',
            ]);

            $url = $this->baileysUrl . $endpoint;
            $response = $method === 'POST' ? $request->post($url, $data) : $request->get($url);

            return [
                'status' => $response->status(),
                'success' => $response->successful(),
                'data' => $response->json(),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => 503,
                'success' => false,
                'data' => null,
                'error' => 'connection_refused',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Case 1: Baileys service tidak aktif (ECONNREFUSED) ditangani anggun tanpa fatal crash.
     */
    public function test_baileys_service_connection_refused_handled_gracefully(): void
    {
        // Simulasikan kegagalan koneksi fisik (ConnectionException)
        Http::fake([
            $this->baileysUrl . '/*' => function () {
                throw new ConnectionException('Failed to connect to 127.0.0.1 port 3000: Connection refused');
            },
        ]);

        $result = $this->callBaileysEndpoint('/api/session/status');

        $this->assertFalse($result['success']);
        $this->assertEquals(503, $result['status']);
        $this->assertEquals('connection_refused', $result['error']);
    }

    /**
     * Case 2: Endpoint QR mengembalikan string Base64 rusak / invalid data URL dideteksi dengan tepat.
     */
    public function test_corrupted_base64_qr_data_url_rejection_or_handling(): void
    {
        $corruptedQrPayloads = [
            'not_a_base64_string',
            'data:image/png;base64,CORRUPTED_BASE64_GARBAGE!@#$%^&*()',
            'data:text/plain;base64,SGVsbG8gV29ybGQ=', // Bukan image PNG
            '', // Kosong
        ];

        foreach ($corruptedQrPayloads as $badQr) {
            Http::fake([
                $this->baileysUrl . '/api/session/qr' => Http::response([
                    'success' => true,
                    'qr' => $badQr,
                ], 200),
            ]);

            $result = $this->callBaileysEndpoint('/api/session/qr');
            $this->assertTrue($result['success']);

            $qrData = $result['data']['qr'] ?? '';
            $isValidDataUrl = str_starts_with($qrData, 'data:image/png;base64,') &&
                base64_decode(substr($qrData, strlen('data:image/png;base64,')), true) !== false;

            // Verifikasi payload yang rusak dikenali sebagai tidak valid
            if ($badQr !== 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=') {
                $this->assertFalse($isValidDataUrl, "Corrupted QR payload '{$badQr}' must not be considered valid PNG base64");
            }
        }
    }

    /**
     * Case 3: Polling QR saat status sudah CONNECTED mengembalikan 400 Bad Request.
     */
    public function test_qr_polling_when_already_connected_returns_bad_request(): void
    {
        $fake = BaileysFake::create()->asConnected();

        $result = $this->callBaileysEndpoint('/api/session/qr');

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('Already connected', $result['data']['message'] ?? '');
    }

    /**
     * Case 4: Request dengan header X-Service-Key salah atau kosong ditolak dengan HTTP 401.
     */
    public function test_invalid_or_missing_x_service_key_header_is_rejected(): void
    {
        Http::fake([
            $this->baileysUrl . '/*' => function ($request) {
                $key = $request->header('X-Service-Key');
                if (empty($key) || $key[0] !== $this->validServiceKey) {
                    return Http::response(['success' => false, 'message' => 'Unauthorized service key'], 401);
                }
                return Http::response(['success' => true], 200);
            },
        ]);

        // 1. Key kosong
        $resEmpty = $this->callBaileysEndpoint('/api/session/status', 'GET', [], '');
        $this->assertEquals(401, $resEmpty['status']);
        $this->assertEquals('Unauthorized service key', $resEmpty['data']['message'] ?? '');

        // 2. Key salah
        $resInvalid = $this->callBaileysEndpoint('/api/session/status', 'GET', [], 'wrong_service_key_999');
        $this->assertEquals(401, $resInvalid['status']);

        // 3. Key valid lolos
        $resValid = $this->callBaileysEndpoint('/api/session/status', 'GET', [], $this->validServiceKey);
        $this->assertEquals(200, $resValid['status']);
    }

    /**
     * Case 5: Penanganan timeout mikroservice saat inisialisasi socket Baileys.
     */
    public function test_microservice_timeout_during_session_start(): void
    {
        Http::fake([
            $this->baileysUrl . '/api/session/start' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 2001 milliseconds');
            },
        ]);

        $result = $this->callBaileysEndpoint('/api/session/start', 'POST');

        $this->assertFalse($result['success']);
        $this->assertEquals(503, $result['status']);
        $this->assertEquals('connection_refused', $result['error']);
    }
}
