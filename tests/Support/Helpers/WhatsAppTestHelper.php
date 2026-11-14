<?php

namespace Tests\Support\Helpers;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Tests\Support\BaileysFake;

class WhatsAppTestHelper
{
    protected TestCase $test;
    protected BaileysFake $fake;
    protected string $serviceKey;
    protected array $mockSessions = [];

    public function __construct(TestCase $test)
    {
        $this->test = $test;
        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
        $this->fake = BaileysFake::create();
    }

    public function getFake(): BaileysFake
    {
        return $this->fake;
    }

    public function setupConnectedSession(
        string $sessionId = 'default',
        string $userJid = '628123456789@s.whatsapp.net'
    ): mixed {
        $this->fake->asConnected($userJid);

        if (class_exists('App\\Models\\WhatsAppSession')) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $sessionClass */
            $sessionClass = 'App\\Models\\WhatsAppSession';
            return $sessionClass::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_jid' => $userJid,
                    'status' => 'connected',
                    'last_connected_at' => now(),
                ]
            );
        }

        $this->mockSessions[$sessionId] = [
            'session_id' => $sessionId,
            'user_jid' => $userJid,
            'status' => 'connected',
            'last_connected_at' => date('Y-m-d H:i:s'),
        ];

        return (object) $this->mockSessions[$sessionId];
    }

    public function setupDisconnectedSession(string $sessionId = 'default'): mixed
    {
        $this->fake->asDisconnected();

        if (class_exists('App\\Models\\WhatsAppSession')) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $sessionClass */
            $sessionClass = 'App\\Models\\WhatsAppSession';
            return $sessionClass::updateOrCreate(
                ['session_id' => $sessionId],
                [
                    'user_jid' => null,
                    'status' => 'disconnected',
                ]
            );
        }

        $this->mockSessions[$sessionId] = [
            'session_id' => $sessionId,
            'user_jid' => null,
            'status' => 'disconnected',
            'last_connected_at' => null,
        ];

        return (object) $this->mockSessions[$sessionId];
    }

    public function fireSentWebhook(string $waMessageId, string $phone, ?int $timestamp = null): TestResponse
    {
        return $this->fireStatusWebhook($waMessageId, $phone, 'SENT', 2, $timestamp);
    }

    public function fireDeliveredWebhook(string $waMessageId, string $phone, ?int $timestamp = null): TestResponse
    {
        return $this->fireStatusWebhook($waMessageId, $phone, 'DELIVERED', 3, $timestamp);
    }

    public function fireReadWebhook(string $waMessageId, string $phone, ?int $timestamp = null): TestResponse
    {
        return $this->fireStatusWebhook($waMessageId, $phone, 'READ', 4, $timestamp);
    }

    public function fireFailedWebhook(string $waMessageId, string $phone, string $error = 'Failed', ?int $timestamp = null): TestResponse
    {
        return $this->fireStatusWebhook($waMessageId, $phone, 'FAILED', 0, $timestamp, $error);
    }

    public function fireStatusWebhook(
        string $waMessageId,
        string $phone,
        string $status,
        int $statusCode,
        ?int $timestamp = null,
        ?string $error = null
    ): TestResponse {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $payload = [
            'event' => 'message.update',
            'wa_message_id' => $waMessageId,
            'remote_jid' => "{$cleanPhone}@s.whatsapp.net",
            'status' => $status,
            'status_code' => $statusCode,
            'timestamp' => $timestamp ?? (int) (microtime(true) * 1000),
        ];

        if ($error !== null) {
            $payload['error_message'] = $error;
        }

        return $this->test->postJson('/api/wa/webhook', $payload, [
            'X-Service-Key' => $this->serviceKey,
        ]);
    }

    public function fireConnectionWebhook(string $status, ?string $userJid = null, ?string $reason = null): TestResponse
    {
        return $this->test->postJson('/api/wa/webhook', [
            'event' => 'connection.update',
            'status' => $status,
            'user_jid' => $userJid ?? $this->fake->getUserJid() ?? '628123456789@s.whatsapp.net',
            'reason' => $reason,
        ], [
            'X-Service-Key' => $this->serviceKey,
        ]);
    }

    public function simulateFullTickCycle(mixed $message, bool $includeRead = true): void
    {
        $waId = null;
        $phone = null;

        if (is_object($message)) {
            $waId = $message->baileys_msg_id ?? null;
            $phone = $message->phone_number ?? $message->phone ?? null;

            if (empty($waId)) {
                $waId = '3EB0' . strtoupper(Str::random(12));
                if (method_exists($message, 'update')) {
                    $message->update(['baileys_msg_id' => $waId]);
                } else {
                    $message->baileys_msg_id = $waId;
                }
            }
        } elseif (is_array($message)) {
            $waId = $message['baileys_msg_id'] ?? ('3EB0' . strtoupper(Str::random(12)));
            $phone = $message['phone_number'] ?? $message['phone'] ?? '628123456789';
        } else {
            $waId = '3EB0' . strtoupper(Str::random(12));
            $phone = (string) $message;
        }

        // 1. Sent (Centang 1)
        $this->fireSentWebhook($waId, $phone)->assertOk();

        // 2. Delivered (Centang 2 Abu)
        $this->fireDeliveredWebhook($waId, $phone)->assertOk();

        // 3. Read (Centang 2 Biru)
        if ($includeRead) {
            $this->fireReadWebhook($waId, $phone)->assertOk();
        }
    }

    public function assertMessageStatus(mixed $message, string $expectedStatus): void
    {
        if (is_object($message) && method_exists($message, 'fresh')) {
            $fresh = $message->fresh();
            Assert::assertEquals(
                $expectedStatus,
                $fresh->status,
                "Message ID {$message->id} status expected '{$expectedStatus}', got '{$fresh->status}'"
            );
        } elseif (is_object($message)) {
            Assert::assertEquals($expectedStatus, $message->status);
        } elseif (is_array($message)) {
            Assert::assertEquals($expectedStatus, $message['status'] ?? null);
        }
    }

    public function assertCampaignCounters(
        mixed $campaign,
        int $sent,
        int $delivered,
        int $read,
        int $failed
    ): void {
        if (is_object($campaign) && method_exists($campaign, 'fresh')) {
            $fresh = $campaign->fresh();
            Assert::assertEquals($sent, $fresh->sent_count, "Campaign sent_count mismatch");
            Assert::assertEquals($delivered, $fresh->delivered_count, "Campaign delivered_count mismatch");
            Assert::assertEquals($read, $fresh->read_count, "Campaign read_count mismatch");
            Assert::assertEquals($failed, $fresh->failed_count, "Campaign failed_count mismatch");
        } elseif (is_object($campaign)) {
            Assert::assertEquals($sent, $campaign->sent_count ?? 0);
            Assert::assertEquals($delivered, $campaign->delivered_count ?? 0);
            Assert::assertEquals($read, $campaign->read_count ?? 0);
            Assert::assertEquals($failed, $campaign->failed_count ?? 0);
        }
    }
}
