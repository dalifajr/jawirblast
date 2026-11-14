<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

class BaileysFake
{
    protected string $baseUrl;
    protected string $serviceKey;
    protected string $sessionStatus = 'CONNECTED';
    protected ?string $userJid = '628123456789@s.whatsapp.net';
    protected string $qrCode = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=';
    protected string $pairingCode = 'ABCD-1234';
    protected bool $shouldFail = false;
    protected string $failErrorMessage = 'Failed to connect to WhatsApp socket';
    protected array $dispatchedMessages = [];

    public function __construct(?string $baseUrl = null, ?string $serviceKey = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.baileys.url', 'http://127.0.0.1:3000'), '/');
        $this->serviceKey = $serviceKey ?? config('services.baileys.key', 'jawirblast_internal_secret_key');
    }

    public static function create(?string $baseUrl = null, ?string $serviceKey = null): self
    {
        $instance = new self($baseUrl, $serviceKey);
        $instance->register();
        return $instance;
    }

    public function asConnected(string $userJid = '628123456789@s.whatsapp.net'): self
    {
        $this->sessionStatus = 'CONNECTED';
        $this->userJid = $userJid;
        return $this;
    }

    public function asDisconnected(): self
    {
        $this->sessionStatus = 'DISCONNECTED';
        $this->userJid = null;
        return $this;
    }

    public function asQrReady(string $qr = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII='): self
    {
        $this->sessionStatus = 'QR_READY';
        $this->qrCode = $qr;
        return $this;
    }

    public function withPairingCode(string $code): self
    {
        $this->pairingCode = $code;
        return $this;
    }

    public function willFail(string $errorMessage = 'WhatsApp socket disconnected'): self
    {
        $this->shouldFail = true;
        $this->failErrorMessage = $errorMessage;
        return $this;
    }

    public function willSucceed(): self
    {
        $this->shouldFail = false;
        return $this;
    }

    public function getSessionStatus(): string
    {
        return $this->sessionStatus;
    }

    public function getUserJid(): ?string
    {
        return $this->userJid;
    }

    public function getPairingCode(): string
    {
        return $this->pairingCode;
    }

    public function getQrCode(): string
    {
        return $this->qrCode;
    }

    public function register(): void
    {
        Http::fake([
            $this->baseUrl . '/api/session/status' => function () {
                return Http::response([
                    'success' => true,
                    'status' => $this->sessionStatus,
                    'userJid' => $this->userJid,
                ], 200);
            },

            $this->baseUrl . '/api/session/start' => function () {
                return Http::response([
                    'success' => true,
                    'message' => 'Socket initializing',
                ], 200);
            },

            $this->baseUrl . '/api/session/qr' => function () {
                if ($this->sessionStatus === 'CONNECTED') {
                    return Http::response([
                        'success' => false,
                        'message' => 'Already connected',
                    ], 400);
                }
                return Http::response([
                    'success' => true,
                    'qr' => $this->qrCode,
                ], 200);
            },

            $this->baseUrl . '/api/session/pairing-code' => function ($request) {
                if ($this->shouldFail) {
                    return Http::response([
                        'success' => false,
                        'message' => $this->failErrorMessage,
                    ], 500);
                }
                return Http::response([
                    'success' => true,
                    'code' => $this->pairingCode,
                ], 200);
            },

            $this->baseUrl . '/api/session/logout' => function () {
                $this->sessionStatus = 'DISCONNECTED';
                $this->userJid = null;
                return Http::response([
                    'success' => true,
                    'message' => 'Logged out',
                ], 200);
            },

            $this->baseUrl . '/api/message/send' => function ($request) {
                if ($this->shouldFail) {
                    return Http::response([
                        'success' => false,
                        'message' => $this->failErrorMessage,
                    ], 500);
                }

                $data = $request->data();
                $waMessageId = '3EB0' . strtoupper(Str::random(12));

                $this->dispatchedMessages[] = [
                    'recipient_id' => $data['recipient_id'] ?? null,
                    'phone' => $data['phone'] ?? '',
                    'message' => $data['message'] ?? '',
                    'media_path' => $data['media_path'] ?? null,
                    'wa_message_id' => $waMessageId,
                    'dispatched_at' => (int) (microtime(true) * 1000),
                ];

                return Http::response([
                    'success' => true,
                    'wa_message_id' => $waMessageId,
                    'status' => 'SENT',
                ], 200);
            },
        ]);
    }

    // --- Webhook Simulation Helpers ---

    public function triggerMessageWebhook(
        TestCase $test,
        string $waMessageId,
        string $phone,
        string $status,
        int $statusCode,
        ?int $timestamp = null,
        ?string $errorMessage = null
    ) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $remoteJid = $cleanPhone . '@s.whatsapp.net';

        $payload = [
            'event' => 'message.update',
            'wa_message_id' => $waMessageId,
            'remote_jid' => $remoteJid,
            'status' => $status,
            'status_code' => $statusCode,
            'timestamp' => $timestamp ?? (int) (microtime(true) * 1000),
        ];

        if ($errorMessage !== null) {
            $payload['error_message'] = $errorMessage;
        }

        return $test->postJson('/api/wa/webhook', $payload, [
            'X-Service-Key' => $this->serviceKey,
        ]);
    }

    public function triggerConnectionWebhook(
        TestCase $test,
        string $status,
        ?string $userJid = null,
        ?string $reason = null
    ) {
        return $test->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => $status,
            'user_jid' => $userJid ?? $this->userJid,
            'reason' => $reason,
        ], [
            'X-Service-Key' => $this->serviceKey,
        ]);
    }

    public function simulateFullTickCycle(
        TestCase $test,
        string $waMessageId,
        string $phone,
        bool $includeRead = true
    ): void {
        // Step 1: Centang 1 (SENT / Status 2)
        $this->triggerMessageWebhook($test, $waMessageId, $phone, 'SENT', 2)
            ->assertStatus(200);

        // Step 2: Centang 2 Abu-Abu (DELIVERED / Status 3)
        $this->triggerMessageWebhook($test, $waMessageId, $phone, 'DELIVERED', 3)
            ->assertStatus(200);

        // Step 3: Centang 2 Biru (READ / Status 4)
        if ($includeRead) {
            $this->triggerMessageWebhook($test, $waMessageId, $phone, 'READ', 4)
                ->assertStatus(200);
        }
    }

    // --- Assertions & Getters ---

    public function assertDispatchedCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->dispatchedMessages,
            "Expected {$count} messages dispatched, but got " . count($this->dispatchedMessages)
        );
    }

    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    public function clearDispatchedMessages(): void
    {
        $this->dispatchedMessages = [];
    }
}
