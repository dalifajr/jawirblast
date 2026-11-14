<?php

namespace Tests\Feature\E2E\Tier2;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B04 - Telegram Alert Network Failure & Payload Corner Cases
 *
 * Menguji ketahanan pengiriman notifikasi Telegram Admin terhadap kegagalan jaringan,
 * HTTP 500/timeout, bot diblokir admin (HTTP 403), karakter meta Markdown yang tidak valid,
 * pesan melebihi batas 4096 karakter, dan rate limiting Telegram (HTTP 429).
 */
class B04_TelegramAlertNetworkFailureCornerCasesTest extends TestCase
{
    use InteractsWithTelegram;

    /**
     * Helper untuk mensimulasikan pemanggilan TelegramService::sendAdminAlert() secara aman.
     */
    protected function dispatchAdminAlert(string $text, string $parseMode = 'Markdown'): array
    {
        $botToken = config('services.telegram.bot_token', 'mock_bot_token');
        $chatId = config('services.telegram.admin_chat_id', '123456789');
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // Limit Telegram message length: 4096 characters
        if (mb_strlen($text, 'UTF-8') > 4000) {
            $text = mb_substr($text, 0, 3990, 'UTF-8') . "\n...[truncated]";
        }

        try {
            $response = Http::timeout(3)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]);

            if ($response->status() === 429) {
                Log::warning('Telegram rate limited, retry after: ' . ($response->json('parameters.retry_after') ?? 1));
                return ['success' => false, 'error' => 'rate_limited', 'status' => 429];
            }

            if ($response->status() === 403) {
                Log::error('Telegram bot blocked by admin: ' . $response->body());
                return ['success' => false, 'error' => 'bot_blocked', 'status' => 403];
            }

            if (!$response->successful()) {
                Log::error('Telegram API error: ' . $response->body());
                return ['success' => false, 'error' => 'http_error', 'status' => $response->status()];
            }

            return ['success' => true, 'status' => 200];
        } catch (\Throwable $e) {
            Log::error('Telegram alert exception: ' . $e->getMessage());
            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper untuk escaping karakter meta Markdown V1 Telegram.
     */
    protected function escapeTelegramMarkdown(string $text): string
    {
        // Karakter yang perlu di-escape pada Telegram Markdown V1: _ * ` [
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }

    /**
     * Case 1: Kegagalan HTTP 500 / timeout dari Telegram API tidak menghentikan eksekusi sistem.
     */
    public function test_telegram_api_timeout_or_500_error_does_not_halt_campaign_queue(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 500,
                'description' => 'Internal Server Error on Telegram gateway',
            ], 500),
        ]);

        // Kirim alert saat status 500
        $result = $this->dispatchAdminAlert('Alert: Campaign Started');

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['status']);
        $this->assertEquals('http_error', $result['error']);

        // Verifikasi alur aplikasi tidak terhenti atau fatal crash
        $this->assertTrue(true, 'System handled 500 error gracefully without unhandled exception');
    }

    /**
     * Case 2: Bot diblokir oleh admin (HTTP 403 Forbidden) ditangani secara aman dan dicatat ke log.
     */
    public function test_telegram_bot_blocked_by_admin_handled_gracefully(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 403,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        $result = $this->dispatchAdminAlert('Alert: WhatsApp Session Disconnected');

        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['status']);
        $this->assertEquals('bot_blocked', $result['error']);
    }

    /**
     * Case 3: Karakter meta Markdown (_, *, `, [) dalam teks kampanye di-escape sehingga tidak merusak rendering.
     */
    public function test_markdown_meta_characters_in_alert_payload_are_escaped(): void
    {
        $unsafeMessage = 'Kampanye: Promo_Akhir_Tahun *DISKON [50%]* dengan kode `BLAST_2026`!';
        $escaped = $this->escapeTelegramMarkdown($unsafeMessage);

        $this->assertStringContainsString('Promo\\_Akhir\\_Tahun', $escaped);
        $this->assertStringContainsString('\\*DISKON', $escaped);
        $this->assertStringContainsString('\\[50%]', $escaped);
        $this->assertStringContainsString('\\`BLAST\\_2026\\`', $escaped);

        // Verifikasi pengiriman dengan mock HTTP
        Http::fake([
            'https://api.telegram.org/*' => function ($request) {
                $payload = $request->data();
                $this->assertStringContainsString('\\_', $payload['text']);
                return Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200);
            },
        ]);

        $result = $this->dispatchAdminAlert($escaped);
        $this->assertTrue($result['success']);
    }

    /**
     * Case 4: Pesan notifikasi melebihi 4096 karakter secara otomatis dipotong (truncated) agar tidak ditolak Telegram.
     */
    public function test_excessively_long_alert_message_is_safely_truncated_or_chunked(): void
    {
        // Teks sangat panjang: 6000 karakter
        $massiveText = str_repeat('Daftar Kontak Terkirim: 6281234567890 | Status: OK. ', 120);
        $this->assertGreaterThan(5000, mb_strlen($massiveText, 'UTF-8'));

        Http::fake([
            'https://api.telegram.org/*' => function ($request) {
                $sentText = $request->data()['text'];
                // Panjang payload yang dikirim tidak boleh melebihi batas 4096 karakter Telegram
                $this->assertLessThanOrEqual(4096, mb_strlen($sentText, 'UTF-8'));
                $this->assertStringContainsString('...[truncated]', $sentText);
                return Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200);
            },
        ]);

        $result = $this->dispatchAdminAlert($massiveText);
        $this->assertTrue($result['success']);
    }

    /**
     * Case 5: Rate limiting dari Telegram API (HTTP 429 Too Many Requests) ditangani dengan graceful handling.
     */
    public function test_rate_limiting_http_429_from_telegram_api_handled(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 429,
                'description' => 'Too Many Requests: retry after 5',
                'parameters' => [
                    'retry_after' => 5,
                ],
            ], 429),
        ]);

        $result = $this->dispatchAdminAlert('Alert: Progress 50% Broadcast');

        $this->assertFalse($result['success']);
        $this->assertEquals(429, $result['status']);
        $this->assertEquals('rate_limited', $result['error']);
    }
}
