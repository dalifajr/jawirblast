<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramService
{
    /**
     * Sanitasi karakter khusus untuk parser HTML Telegram Bot API.
     */
    public static function escape(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Kirim pesan teks berformat HTML ke chat Telegram tertentu.
     */
    public static function sendMessage(string|int $chatId, string $htmlMessage): bool
    {
        $botToken = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');

        if (empty($botToken) || empty($chatId)) {
            Log::info('Telegram sendMessage dilewati: Token atau chat ID belum diset.');
            return false;
        }

        try {
            $response = Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $htmlMessage,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('Telegram Bot API merespons non-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim pesan Telegram: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tahap 1: Notifikasi Mulai Siaran Kampanye (Broadcast Start).
     */
    public static function notifyBroadcastStart(mixed $campaign, mixed $contacts = null, mixed $startedAt = null, ?string $senderPhone = null): bool
    {
        $adminChatId = config('services.telegram.admin_chat_id') ?: env('TELEGRAM_ADMIN_CHAT_ID');
        if (empty($adminChatId)) {
            return false;
        }

        if (is_object($campaign)) {
            $title = self::escape($campaign->title ?? 'Kampanye Baru');
            $total = (int) ($campaign->total_contacts ?? 0);
            $started = isset($campaign->started_at) && $campaign->started_at
                ? Carbon::parse($campaign->started_at)->format('d M Y, H:i:s')
                : Carbon::now()->format('d M Y, H:i:s');
            $sender = $senderPhone ?: ($campaign->session->user_jid ?? 'Aktif');
        } else {
            $title = self::escape((string) $campaign);
            $total = is_numeric($contacts) ? (int) $contacts : 0;
            $started = $startedAt
                ? Carbon::parse($startedAt)->format('d M Y, H:i:s')
                : Carbon::now()->format('d M Y, H:i:s');
            $sender = $senderPhone ?: 'Aktif';
        }

        $formattedTotal = number_format($total, 0, ',', '.');
        $senderEscaped = self::escape($sender);

        $text = "🚀 <b>SIARAN KAMPANYE DIMULAI</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "📌 <b>Kampanye:</b> {$title}\n"
              . "👥 <b>Total Target:</b> {$formattedTotal} Kontak\n"
              . "📱 <b>Pengirim WA:</b> <code>{$senderEscaped}</code>\n"
              . "🕒 <b>Waktu Mulai:</b> {$started} WIB\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "<i>Sistem sedang memproses antrean pesan secara bertahap...</i>";

        return self::sendMessage($adminChatId, $text);
    }

    /**
     * Tahap 2: Notifikasi Progres Berkala Kampanye (Progress Update).
     */
    public static function notifyBroadcastProgress(
        mixed $campaign,
        int $sent = 0,
        int $total = 0,
        int $delivered = 0,
        int $read = 0,
        int $failed = 0
    ): bool {
        $adminChatId = config('services.telegram.admin_chat_id') ?: env('TELEGRAM_ADMIN_CHAT_ID');
        if (empty($adminChatId)) {
            return false;
        }

        if (is_object($campaign)) {
            $title = self::escape($campaign->title ?? 'Kampanye');
            $total = (int) ($campaign->total_contacts ?? 1);
            $sent = (int) ($campaign->sent_count ?? 0);
            $delivered = (int) ($campaign->delivered_count ?? 0);
            $read = (int) ($campaign->read_count ?? 0);
            $failed = (int) ($campaign->failed_count ?? 0);
        } else {
            $title = self::escape((string) $campaign);
        }

        $totalSafe = max(1, $total);
        $processed = $sent + $failed;
        $percent = min(100, round(($processed / $totalSafe) * 100, 1));

        $filled = (int) round($percent / 10);
        $empty = 10 - $filled;
        $progressBar = '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';

        $text = "📊 <b>PROGRES KAMPANYE</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "📌 <b>Kampanye:</b> {$title}\n"
              . "📈 <b>Progres:</b> {$progressBar} {$percent}% ({$processed}/{$total})\n\n"
              . "✅ <b>Terkirim (Sent):</b> {$sent}\n"
              . "📬 <b>Diterima (Delivered):</b> {$delivered}\n"
              . "👀 <b>Dibaca (Read):</b> {$read}\n"
              . "❌ <b>Gagal (Failed):</b> {$failed}\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "<i>Status pengiriman terus diperbarui secara berkala...</i>";

        return self::sendMessage($adminChatId, $text);
    }

    /**
     * Tahap 3: Rekap Selesai Siaran Kampanye (Broadcast Completed).
     */
    public static function notifyBroadcastCompleted(
        mixed $campaign,
        int $total = 0,
        int $sent = 0,
        int $failed = 0,
        string $duration = ''
    ): bool {
        $adminChatId = config('services.telegram.admin_chat_id') ?: env('TELEGRAM_ADMIN_CHAT_ID');
        if (empty($adminChatId)) {
            return false;
        }

        if (is_object($campaign)) {
            $title = self::escape($campaign->title ?? 'Kampanye');
            $total = (int) ($campaign->total_contacts ?? 1);
            $sent = (int) ($campaign->sent_count ?? 0);
            $failed = (int) ($campaign->failed_count ?? 0);
            $completedAt = isset($campaign->completed_at) && $campaign->completed_at
                ? Carbon::parse($campaign->completed_at)->format('d M Y, H:i:s')
                : Carbon::now()->format('d M Y, H:i:s');
            if (isset($campaign->started_at) && isset($campaign->completed_at)) {
                $duration = Carbon::parse($campaign->started_at)->diffForHumans(Carbon::parse($campaign->completed_at), true);
            }
        } else {
            $title = self::escape((string) $campaign);
            $completedAt = Carbon::now()->format('d M Y, H:i:s');
        }

        $durationText = $duration ?: 'Selesai';
        $totalSafe = max(1, $total);
        $sentPct = round(($sent / $totalSafe) * 100, 1);
        $failPct = round(($failed / $totalSafe) * 100, 1);

        $text = "🏁 <b>KAMPANYE SELESAI DIPROSES</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "📌 <b>Kampanye:</b> {$title}\n"
              . "🕒 <b>Waktu Selesai:</b> {$completedAt} WIB\n"
              . "⏱ <b>Total Durasi:</b> {$durationText}\n\n"
              . "📊 <b>Rekapitulasi Pengiriman:</b>\n"
              . "• Total Kontak Target: <b>{$total}</b>\n"
              . "• Terkirim (Sent): <b>{$sent}</b> ({$sentPct}%)\n"
              . "• Gagal (Failed): <b>{$failed}</b> ({$failPct}%)\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "🎉 <i>Seluruh antrean pesan berhasil diselesaikan.</i>";

        return self::sendMessage($adminChatId, $text);
    }

    /**
     * Tahap 4: Peringatan Sesi WhatsApp Terputus (Session Dropped / Disconnected).
     */
    public static function notifySessionDisconnected(string $sessionIdentifier, string $reason = ''): bool
    {
        return self::notifySessionDropped($sessionIdentifier, $reason);
    }

    /**
     * Alias untuk notifikasi sesi WhatsApp terputus.
     */
    public static function notifySessionDropped(string $sessionIdentifier, string $reason = ''): bool
    {
        $adminChatId = config('services.telegram.admin_chat_id') ?: env('TELEGRAM_ADMIN_CHAT_ID');
        if (empty($adminChatId)) {
            return false;
        }

        $phone = self::escape($sessionIdentifier);
        $reasonText = self::escape($reason ?: 'Koneksi socket Baileys terputus / logout.');
        $now = Carbon::now()->format('d M Y, H:i:s');

        $text = "🚨 <b>PERINGATAN: SESI WHATSAPP TERPUTUS</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "📱 <b>Nomor / ID Sesi:</b> <code>{$phone}</code>\n"
              . "⚠️ <b>Alasan:</b> {$reasonText}\n"
              . "🕒 <b>Waktu Terputus:</b> {$now} WIB\n\n"
              . "⚠️ <b>Dampak Operasional:</b>\n"
              . "Pengiriman pesan kampanye yang sedang aktif akan tertunda hingga perangkat ditautkan kembali.\n"
              . "━━━━━━━━━━━━━━━━━━━━\n"
              . "👉 <i>Silakan buka Dashboard JAWIRBLAST dan pindai QR Code baru!</i>";

        return self::sendMessage($adminChatId, $text);
    }

    /**
     * Magic caller agar method dapat dipanggil sebagai instance method (Dependency Injection).
     */
    public function __call(string $method, array $arguments)
    {
        if (method_exists(self::class, $method)) {
            return forward_static_call_array([self::class, $method], $arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . self::class);
    }
}
