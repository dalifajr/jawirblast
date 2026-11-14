<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppSession;
use App\Services\BaileysClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppSessionController extends Controller
{
    protected BaileysClientService $baileysService;

    public function __construct(BaileysClientService $baileysService)
    {
        $this->baileysService = $baileysService;
    }

    /**
     * Menampilkan halaman antarmuka sesi WhatsApp (BeerCSS M3 Flat).
     */
    public function index(): View
    {
        $session = WhatsAppSession::where('session_id', 'default')->first();
        $liveStatus = $this->baileysService->getStatus();
        $qrCode = null;

        if (!$session || !$session->isConnected()) {
            $qrCode = $this->baileysService->getQrCode();
        }

        return view('whatsapp.index', [
            'session' => $session,
            'liveStatus' => $liveStatus,
            'qrCode' => $qrCode,
        ]);
    }

    /**
     * Endpoint JSON polling status koneksi live.
     */
    public function status(): JsonResponse
    {
        $session = WhatsAppSession::where('session_id', 'default')->first();
        $liveStatus = $this->baileysService->getStatus();

        $statusStr = $liveStatus['status'] ?? ($session?->status ? strtoupper($session->status) : 'DISCONNECTED');
        $userJid = $liveStatus['userJid'] ?? $session?->user_jid;

        return response()->json([
            'success' => true,
            'status' => $statusStr,
            'userJid' => $userJid,
            'phone' => $session?->phone_number ?? ($userJid ? explode('@', $userJid)[0] : null),
            'db_status' => $session?->status ?? 'disconnected',
            'last_connected_at' => $session?->last_connected_at?->toIso8601String(),
        ]);
    }

    /**
     * Endpoint JSON untuk mengambil QR Code Base64.
     */
    public function qr(): JsonResponse
    {
        $session = WhatsAppSession::where('session_id', 'default')->first();
        if ($session && $session->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => 'Already connected',
            ], 400);
        }

        $qr = $this->baileysService->getQrCode();
        if ($qr) {
            return response()->json([
                'success' => true,
                'qr' => $qr,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'QR Code not ready yet',
        ], 200);
    }

    /**
     * Endpoint untuk menginisialisasi socket Baileys.
     */
    public function start(): JsonResponse
    {
        $result = $this->baileysService->startSession();
        return response()->json($result);
    }

    /**
     * Endpoint untuk meminta 8-digit Pairing Code.
     */
    public function requestPairingCode(Request $request): JsonResponse
    {
        $phone = $request->input('phone');
        if (empty($phone) || !is_string($phone) || !preg_match('/^\+?[0-9]{8,20}$/', $phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number',
            ], 400);
        }

        $session = WhatsAppSession::where('session_id', 'default')->first();
        if ($session && $session->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => 'Session already connected',
            ], 400);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) < 8 || strlen($cleanPhone) > 20) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format',
            ], 400);
        }

        $res = $this->baileysService->requestPairingCode($cleanPhone);

        $payload = $res['data'] ?? $res;
        $httpCode = $res['status'] ?? 200;

        return response()->json($payload, $httpCode);
    }

    /**
     * Endpoint untuk memutus sesi WhatsApp.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $this->baileysService->logout();

        WhatsAppSession::updateOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'disconnected',
                'user_jid' => null,
            ]
        );

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out',
            ]);
        }

        return redirect()->route('whatsapp.index')->with('success', __('session.disconnect_success'));
    }
}
