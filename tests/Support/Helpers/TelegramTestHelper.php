<?php

namespace Tests\Support\Helpers;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

class TelegramTestHelper
{
    protected TestCase $test;
    protected array $capturedTelegramMessages = [];

    public function __construct(TestCase $test)
    {
        $this->test = $test;
    }

    public function fakeTelegramApi(): void
    {
        $this->capturedTelegramMessages = [];

        Http::fake([
            'https://api.telegram.org/bot*' => function ($request) {
                if (Str::contains($request->url(), '/sendMessage')) {
                    $data = $request->data();
                    $this->capturedTelegramMessages[] = $data;

                    return Http::response([
                        'ok' => true,
                        'result' => [
                            'message_id' => rand(1000, 9999),
                            'date' => time(),
                            'text' => $data['text'] ?? '',
                            'chat' => [
                                'id' => $data['chat_id'] ?? 123456789,
                            ],
                        ],
                    ], 200);
                }

                return Http::response(['ok' => true, 'result' => true], 200);
            },
        ]);
    }

    public function generateLoginToken(mixed $user = null): mixed
    {
        $token = Str::random(32);
        $userId = is_object($user) ? ($user->id ?? null) : $user;

        if (class_exists('App\\Models\\TelegramLoginToken')) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $tokenClass */
            $tokenClass = 'App\\Models\\TelegramLoginToken';
            return $tokenClass::create([
                'token' => $token,
                'user_id' => $userId,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(15),
            ]);
        }

        return (object) [
            'token' => $token,
            'user_id' => $userId,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        ];
    }

    public function simulateDeepLinkStart(
        string $token,
        int $telegramId = 123456789,
        string $username = 'jawir_user',
        string $firstName = 'Jawir'
    ): TestResponse {
        $payload = [
            'update_id' => rand(10000, 99999),
            'message' => [
                'message_id' => rand(100, 999),
                'from' => [
                    'id' => $telegramId,
                    'is_bot' => false,
                    'first_name' => $firstName,
                    'username' => $username,
                ],
                'chat' => [
                    'id' => $telegramId,
                    'first_name' => $firstName,
                    'username' => $username,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => "/start weblogin_{$token}",
            ],
        ];

        return $this->test->postJson('/api/telegram/webhook', $payload);
    }

    public function assertAdminNotificationSent(string $needle): void
    {
        $found = false;
        foreach ($this->capturedTelegramMessages as $msg) {
            $text = $msg['text'] ?? '';
            if (Str::contains($text, $needle)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            "Failed asserting that a Telegram admin notification containing '{$needle}' was sent. Total sent: " . count($this->capturedTelegramMessages)
        );
    }

    public function assertNotificationCount(int $expectedCount): void
    {
        Assert::assertCount(
            $expectedCount,
            $this->capturedTelegramMessages,
            "Expected {$expectedCount} Telegram notifications, sent " . count($this->capturedTelegramMessages)
        );
    }

    public function getCapturedMessages(): array
    {
        return $this->capturedTelegramMessages;
    }

    public function clearCapturedMessages(): void
    {
        $this->capturedTelegramMessages = [];
    }
}
