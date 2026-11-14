<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramLoginToken;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    /**
     * Entry point webhook dari server Telegram.
     */
    public function handleWebhook(Request $request, ?TelegramService $telegramService = null): JsonResponse
    {
        $service = $telegramService ?: app(TelegramService::class);
        $message = $request->input('message');

        if (!$message || !isset($message['text'])) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $text = trim($message['text']);
        $chatId = $message['chat']['id'] ?? null;
        $fromId = $message['from']['id'] ?? $chatId;
        $firstName = $message['from']['first_name'] ?? 'Pengguna';
        $username = $message['from']['username'] ?? null;

        // Tangkap format /start weblogin_{token}
        if (preg_match('/^\/start\s+weblogin_([a-zA-Z0-9_\-\.\'\s]+)$/', $text, $matches)) {
            $tokenString = trim($matches[1]);
            $record = TelegramLoginToken::where('token', $tokenString)->first();

            if (!$record) {
                if ($chatId) {
                    $service->sendMessage($chatId, "⚠️ <b>Token Tidak Ditemukan</b>\n\nToken login ini tidak terdaftar di sistem JAWIRBLAST.");
                }
                return response()->json(['ok' => false, 'error' => 'Token not found'], 404);
            }

            if (now()->greaterThan($record->expires_at) || $record->status === 'expired') {
                $record->update(['status' => 'expired']);
                if ($chatId) {
                    $service->sendMessage($chatId, "⏳ <b>Token Kedaluwarsa</b>\n\nToken login telah melewati batas waktu berlaku. Silakan ulangi dari peramban Anda.");
                }
                return response()->json(['ok' => false, 'error' => 'Token has expired'], 410);
            }

            if ($record->status === 'used') {
                if ($chatId) {
                    $service->sendMessage($chatId, "⚠️ <b>Token Sudah Digunakan</b>\n\nToken ini sudah pernah dipakai untuk login sebelumnya.");
                }
                return response()->json(['ok' => false, 'error' => 'Token already used'], 409);
            }

            // Batalkan token verified lama untuk telegram_id yang sama agar tidak ada konflik
            if ($fromId) {
                TelegramLoginToken::where('telegram_id', $fromId)
                    ->where('status', 'verified')
                    ->where('id', '!=', $record->id)
                    ->update(['status' => 'expired']);
            }

            // Tandai token sebagai verified
            $record->update([
                'telegram_id' => $fromId,
                'status' => 'verified',
                'updated_at' => now(),
            ]);

            // Sinkronkan atau buat akun user
            if ($fromId) {
                $user = User::firstOrCreate(
                    ['telegram_id' => $fromId],
                    [
                        'name' => $firstName,
                        'telegram_username' => $username,
                        'email' => "tg_{$fromId}@telegram.user",
                        'password' => Hash::make(Str::random(32)),
                        'locale' => 'id',
                    ]
                );

                if ($username && $user->telegram_username !== $username) {
                    $user->update(['telegram_username' => $username]);
                }
            }

            if ($chatId) {
                $escapedName = TelegramService::escape($firstName);
                $service->sendMessage(
                    $chatId,
                    "✅ <b>Login Berhasil Dikonfirmasi!</b>\n\nHalo <b>{$escapedName}</b>, sesi Anda telah terverifikasi.\nSilakan kembali ke peramban Anda untuk masuk ke Dashboard JAWIRBLAST."
                );
            }

            return response()->json([
                'ok' => true,
                'action' => 'token_verified',
                'telegram_id' => $fromId,
                'message' => 'Token verified successfully',
            ]);
        }

        return response()->json(['ok' => true, 'ignored' => true]);
    }

    /**
     * Alias method handle untuk fleksibilitas pemanggilan.
     */
    public function handle(Request $request): JsonResponse
    {
        return $this->handleWebhook($request);
    }
}
