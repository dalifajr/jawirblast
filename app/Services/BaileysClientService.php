<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaileysClientService
{
    protected string $baseUrl;
    protected string $serviceKey;
    protected int $timeout;

    public function __construct(?string $baseUrl = null, ?string $serviceKey = null, int $timeout = 8)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.baileys.url', 'http://127.0.0.1:3000'), '/');
        $this->serviceKey = $serviceKey ?? config('services.baileys.key', 'blast_internal_secret_key_2026');
        $this->timeout = $timeout;
    }

    /**
     * Mempersiapkan HTTP client dengan header autentikasi internal dan timeout.
     */
    protected function client()
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-Service-Key' => $this->serviceKey,
                'Accept' => 'application/json',
            ]);
    }

    /**
     * Mengambil status sesi saat ini dari mikroservice Baileys.
     */
    public function getStatus(): array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/session/status");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'statusCode' => $response->status(),
                'message' => $response->json('message', 'Failed to retrieve session status'),
            ];
        } catch (ConnectionException $e) {
            Log::warning('Baileys microservice unreachable on getStatus: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'message' => 'Baileys microservice offline / unreachable',
            ];
        } catch (Exception $e) {
            Log::error('Exception on Baileys getStatus: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'DISCONNECTED',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Menginisialisasi soket koneksi Baileys di mikroservice.
     */
    public function startSession(): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/session/start");
            return $response->json();
        } catch (Exception $e) {
            Log::error('Baileys startSession failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mengambil data QR Code Base64 Data URL jika tersedia.
     */
    public function getQrCode(): ?string
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/api/session/qr");

            if ($response->successful() && $response->json('success')) {
                return $response->json('qr');
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Baileys getQrCode failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengirim permintaan 8-Digit Pairing Code untuk nomor WhatsApp tertentu.
     */
    public function requestPairingCode(string $phone): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/session/pairing-code", [
                'phone' => $phone,
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (ConnectionException $e) {
            Log::error('Baileys connection error on requestPairingCode: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => 'WhatsApp socket timeout while requesting pairing code: ' . $e->getMessage(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Baileys requestPairingCode failed: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Memutuskan sesi WhatsApp dan menghapus kredensial multi-device.
     */
    public function logout(): array
    {
        try {
            $response = $this->client()->post("{$this->baseUrl}/api/session/logout");
            return $response->json();
        } catch (Exception $e) {
            Log::error('Baileys logout failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Mengirim pesan WhatsApp melalui mikroservice Baileys.
     */
    public function sendMessage(
        int|string|null $recipientId,
        string $phone,
        string $message,
        ?string $mediaPath = null
    ): array {
        try {
            $payload = [
                'recipient_id' => $recipientId,
                'phone' => $phone,
                'message' => $message,
            ];

            if ($mediaPath) {
                $payload['media_path'] = $mediaPath;
            }

            $response = $this->client()->post("{$this->baseUrl}/api/message/send", $payload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (Exception $e) {
            Log::error('Baileys sendMessage failed: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 500,
                'data' => [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
