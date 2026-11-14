<?php

namespace App\Http\Controllers;

use App\Models\TelegramLoginToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    /**
     * Mode 1: Menghasilkan token login deep-link baru untuk web browser.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $botUsername = config('services.telegram.bot_username') ?: env('TELEGRAM_BOT_USERNAME', 'JawirBlastBot');
        $token = Str::random(32);
        $expiresAt = now()->addMinutes(15);

        TelegramLoginToken::create([
            'token' => $token,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        $deepLink = "https://t.me/{$botUsername}?start=weblogin_{$token}";

        return response()->json([
            'success' => true,
            'token' => $token,
            'bot_username' => $botUsername,
            'bot_url' => $deepLink,
            'deep_link' => $deepLink,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Alias method untuk kompatibilitas.
     */
    public function requestToken(Request $request): JsonResponse
    {
        return $this->generateToken($request);
    }

    /**
     * Mode 1: Polling status token dari Web Browser.
     */
    public function checkToken(Request $request, ?string $token = null): JsonResponse
    {
        $tokenString = $token ?: $request->route('token') ?: $request->input('token');

        if (!$tokenString) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'Token parameter is missing.',
            ], 404);
        }

        $record = TelegramLoginToken::where('token', $tokenString)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'Token not found',
            ], 404);
        }

        // Cek kedaluwarsa
        if (now()->isAfter($record->expires_at) || $record->status === 'expired') {
            if ($record->status !== 'expired') {
                $record->update(['status' => 'expired']);
            }
            return response()->json([
                'success' => false,
                'status' => 'expired',
                'message' => 'Token invalid or expired',
            ], 400);
        }

        // Cek apakah token sudah digunakan
        if ($record->status === 'used') {
            return response()->json([
                'success' => false,
                'status' => 'used',
                'message' => 'Token invalid or expired',
            ], 400);
        }

        // Status masih pending
        if ($record->status === 'pending') {
            return response()->json([
                'success' => true,
                'status' => 'pending',
                'authenticated' => false,
            ]);
        }

        // Status verified: Lakukan login
        if ($record->status === 'verified') {
            $affected = TelegramLoginToken::where('id', $record->id)
                ->where('status', 'verified')
                ->update(['status' => 'used', 'updated_at' => now()]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'status' => 'used',
                    'message' => 'Token login sudah digunakan.',
                ], 400);
            }

            $telegramId = $record->telegram_id;

            $user = User::firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => 'Telegram User ' . $telegramId,
                    'telegram_username' => $record->telegram_username ?? null,
                    'email' => 'tg_' . $telegramId . '@telegram.user',
                    'password' => Hash::make(Str::random(32)),
                    'locale' => app()->getLocale() ?: 'id',
                ]
            );

            Auth::login($user, true);
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'status' => 'verified',
                'authenticated' => true,
                'user_id' => $user->id,
                'redirect_url' => route('dashboard'),
            ]);
        }

        return response()->json([
            'success' => false,
            'status' => 'rejected',
            'message' => 'Invalid token status',
        ], 400);
    }

    /**
     * Menangani callback Telegram:
     * - Mode 2: Official Telegram Login Widget (GET dengan HMAC hash)
     * - Token callback: POST dengan parameter token
     */
    public function handleCallback(Request $request): RedirectResponse|JsonResponse
    {
        // Skenario A: Token POST callback langsung (seperti corner case testing)
        if ($request->isMethod('post') && $request->has('token') && !$request->has('hash')) {
            $tokenString = $request->input('token');
            $record = TelegramLoginToken::where('token', $tokenString)->first();

            if (!$record || $record->status !== 'verified') {
                return response()->json(['success' => false, 'error' => 'Invalid or unverified token'], 401);
            }

            if (now()->gt($record->expires_at)) {
                return response()->json(['success' => false, 'error' => 'Token expired'], 410);
            }

            $affected = TelegramLoginToken::where('id', $record->id)
                ->where('status', 'verified')
                ->update([
                    'status' => 'used',
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json(['success' => false, 'error' => 'Token already used or invalid'], 409);
            }

            return response()->json(['success' => true, 'telegram_id' => $record->telegram_id]);
        }

        // Skenario B: Mode 2 Official Telegram Login Widget
        $botToken = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        $authData = $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date']);
        $checkHash = $request->input('hash');

        if (empty($checkHash) || empty($authData['id']) || empty($botToken)) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Data verifikasi Telegram tidak lengkap.'], 400)
                : redirect()->route('login')->withErrors(['telegram' => 'Data verifikasi Telegram tidak lengkap.']);
        }

        // Anti-replay check: validitas 24 jam (86400 detik)
        if ((time() - (int) ($authData['auth_date'] ?? 0)) > 86400) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Data is outdated'], 400)
                : redirect()->route('login')->withErrors(['telegram' => 'Sesi autentikasi Telegram telah kedaluwarsa.']);
        }

        // Susun data-check string secara alfabetis
        $dataCheckArr = [];
        foreach ($authData as $k => $v) {
            if ($v !== null && $v !== '') {
                $dataCheckArr[] = "{$k}={$v}";
            }
        }
        sort($dataCheckArr);
        $dataCheckString = implode("\n", $dataCheckArr);

        // Algoritma Widget Resmi: secret_key = SHA256 binary dari bot token
        $secretKey = hash('sha256', $botToken, true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($computedHash, $checkHash)) {
            Log::warning('Verifikasi HMAC Telegram Login Widget gagal', ['data' => $authData]);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Data is not from Telegram'], 403)
                : redirect()->route('login')->withErrors(['telegram' => 'Tanda tangan Telegram tidak valid atau telah dimanipulasi.']);
        }

        // Autentikasi atau pembuatan user
        $telegramId = (int) $authData['id'];
        $name = trim(($authData['first_name'] ?? '') . ' ' . ($authData['last_name'] ?? ''));
        if (empty($name)) {
            $name = $authData['username'] ?? ('Telegram ' . $telegramId);
        }

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'name' => $name,
                'telegram_username' => $authData['username'] ?? null,
                'email' => 'tg_' . $telegramId . '@telegram.user',
                'password' => Hash::make(Str::random(32)),
                'locale' => app()->getLocale() ?: 'id',
            ]
        );

        if (!empty($authData['username']) && $user->telegram_username !== $authData['username']) {
            $user->update(['telegram_username' => $authData['username']]);
        }

        if (!empty($authData['photo_url']) && $user->photo_url !== $authData['photo_url']) {
            $user->update(['photo_url' => $authData['photo_url']]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
