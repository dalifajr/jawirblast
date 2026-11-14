<?php

namespace Tests\Feature\E2E\Tier1;

use Illuminate\Support\Facades\Http;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * F05: Baileys Microservice & QR Pairing Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji komunikasi REST API antara Laravel dan Microservice Baileys (port 3000):
 * 1. Pengecekan status sesi (CONNECTED/DISCONNECTED/QR_READY)
 * 2. Inisialisasi socket Baileys (/api/session/start)
 * 3. Pengambilan QR Code Base64 Data URL (/api/session/qr)
 * 4. Penolakan QR Code saat sesi sudah CONNECTED (HTTP 400)
 * 5. Logout dan pemutusan sesi (/api/session/logout)
 */
class F05_BaileysMicroserviceAndQrTest extends TestCase
{
    use InteractsWithWhatsApp;

    protected BaileysFake $fake;
    protected string $serviceUrl;
    protected string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceUrl = config('services.baileys.url', 'http://127.0.0.1:3000');
        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
        $this->fake = BaileysFake::create($this->serviceUrl, $this->serviceKey);
    }

    /**
     * 1. Endpoint /api/session/status mengembalikan status sesi saat ini dan userJid jika terhubung.
     */
    public function test_01_baileys_status_endpoint_returns_current_session_state(): void
    {
        $this->fake->asConnected('6281234567890@s.whatsapp.net')->register();

        $response = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->get("{$this->serviceUrl}/api/session/status");

        $this->assertTrue($response->successful());
        $this->assertEquals('CONNECTED', $response->json('status'));
        $this->assertEquals('6281234567890@s.whatsapp.net', $response->json('userJid'));

        // Transisi ke disconnected
        $this->fake->asDisconnected()->register();
        $respDisc = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->get("{$this->serviceUrl}/api/session/status");

        $this->assertTrue($respDisc->successful());
        $this->assertEquals('DISCONNECTED', $respDisc->json('status'));
        $this->assertNull($respDisc->json('userJid'));
    }

    /**
     * 2. Endpoint /api/session/start menginisialisasi socket Baileys dan mengembalikan konfirmasi.
     */
    public function test_02_baileys_session_start_initializes_socket_connection(): void
    {
        $response = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->post("{$this->serviceUrl}/api/session/start");

        $this->assertTrue($response->successful());
        $this->assertEquals('Socket initializing', $response->json('message'));
        $this->assertTrue($response->json('success'));
    }

    /**
     * 3. Endpoint /api/session/qr menghasilkan string Base64 data URL saat status QR_READY.
     */
    public function test_03_baileys_qr_endpoint_returns_base64_data_url_when_ready(): void
    {
        $sampleQr = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=';
        $this->fake->asQrReady($sampleQr)->register();

        $response = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->get("{$this->serviceUrl}/api/session/qr");

        $this->assertTrue($response->successful());
        $this->assertTrue($response->json('success'));
        $this->assertEquals($sampleQr, $response->json('qr'));
        $this->assertStringStartsWith('data:image/png;base64,', $response->json('qr'));
    }

    /**
     * 4. Endpoint QR menolak request (HTTP 400 Bad Request) jika status sesi sudah CONNECTED.
     */
    public function test_04_baileys_qr_endpoint_rejects_with_400_when_already_connected(): void
    {
        $this->fake->asConnected('62899998888@s.whatsapp.net')->register();

        $response = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->get("{$this->serviceUrl}/api/session/qr");

        $this->assertEquals(400, $response->status());
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already connected', strtolower($response->json('message')));
    }

    /**
     * 5. Endpoint /api/session/logout memutuskan sesi dan mereset status menjadi DISCONNECTED.
     */
    public function test_05_baileys_logout_endpoint_disconnects_session_and_cleans_credentials(): void
    {
        $this->fake->asConnected('62811223344@s.whatsapp.net')->register();

        $response = Http::withHeaders(['X-Service-Key' => $this->serviceKey])
            ->post("{$this->serviceUrl}/api/session/logout");

        $this->assertTrue($response->successful());
        $this->assertEquals('Logged out', $response->json('message'));
        $this->assertEquals('DISCONNECTED', $this->fake->getSessionStatus());
    }
}
