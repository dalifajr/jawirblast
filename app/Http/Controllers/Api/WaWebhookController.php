<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WaWebhookController extends Controller
{
    /**
     * Menangani callback webhook asinkron dari mikroservice Baileys.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $incomingKey = $request->header('X-Service-Key');
        $validKeys = [
            config('services.baileys.key'),
            'blast_internal_secret_key_2026',
            'jawirblast_internal_secret_key',
        ];

        if (!$incomingKey || !in_array($incomingKey, array_filter($validKeys), true)) {
            Log::warning('Unauthorized webhook request to /api/wa/webhook', [
                'ip' => $request->ip(),
                'incoming_key' => $incomingKey ? substr($incomingKey, 0, 5) . '...' : null,
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $sessionId = $request->input('session_id', 'default');

        // Event 1: Perubahan Status Koneksi Soket (connection.update)
        if ($event === 'connection.update') {
            $status = $request->input('status'); // open, close, connecting
            $userJid = $request->input('user_jid');

            $dbStatus = match ($status) {
                'open' => 'connected',
                'close' => 'disconnected',
                'connecting' => 'connecting',
                default => 'disconnected',
            };

            $updateData = [
                'status' => $dbStatus,
                'updated_at' => now(),
            ];

            if ($dbStatus === 'connected') {
                $updateData['last_connected_at'] = now();
                if (!empty($userJid)) {
                    $updateData['user_jid'] = $userJid;
                }
            } elseif ($dbStatus === 'disconnected') {
                $updateData['user_jid'] = null;
            }

            if (Schema::hasTable('whatsapp_sessions')) {
                DB::table('whatsapp_sessions')->updateOrInsert(
                    ['session_id' => $sessionId],
                    array_merge($updateData, ['created_at' => now()])
                );
            }

            // Jika koneksi terputus saat kampanye berjalan, otomatis pause kampanye aktif secara defensif
            if ($status === 'close' && Schema::hasTable('campaigns')) {
                DB::table('campaigns')->where('status', 'processing')->update([
                    'status' => 'paused',
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('webhook_logs')) {
                DB::table('webhook_logs')->insert([
                    'event' => 'connection.update',
                    'payload' => json_encode($request->all()),
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $dbStatus,
            ]);
        }

        // Event 2: Pembaruan Tanda Terima Pesan (message.update)
        if ($event === 'message.update') {
            $waId = $request->input('wa_message_id');
            $statusCode = $request->input('status_code');
            $statusInput = strtolower((string) $request->input('status', ''));
            $error = $request->input('error_message');

            $dbStatus = match (true) {
                $statusCode === 2 || $statusInput === 'sent' => 'sent',
                $statusCode === 3 || $statusInput === 'delivered' => 'delivered',
                $statusCode === 4 || $statusInput === 'read' => 'read',
                $statusCode === 0 || $statusCode === 1 || $statusInput === 'failed' => 'failed',
                default => 'sent',
            };

            if (Schema::hasTable('campaign_messages')) {
                $message = DB::table('campaign_messages')->where('baileys_msg_id', $waId)->first();
                if ($message) {
                    $now = now();
                    $updateData = [
                        'status' => $dbStatus,
                        'updated_at' => $now,
                    ];

                    if ($dbStatus === 'sent' && !$message->sent_at) {
                        $updateData['sent_at'] = $now;
                    } elseif ($dbStatus === 'delivered') {
                        $updateData['delivered_at'] = $now;
                    } elseif ($dbStatus === 'read') {
                        $updateData['read_at'] = $now;
                        // Jika pesan sebelumnya sudah dikirim ke antrean, maka mencapai tahap read
                        // memastikan pesan telah tersampaikan (delivered)
                        if (!empty($message->sent_at) && empty($message->delivered_at)) {
                            $updateData['delivered_at'] = $now;
                        }
                    } elseif ($dbStatus === 'failed') {
                        $updateData['error_message'] = $error ?? 'Message delivery failed';
                    }

                    DB::table('campaign_messages')->where('id', $message->id)->update($updateData);

                    // Agregasi universal penghitung statistik kampanye (Murni Berbasis Data & Timestamp)
                    if ($message->status !== $dbStatus && Schema::hasTable('campaigns')) {
                        $campaign = DB::table('campaigns')->where('id', $message->campaign_id)->first();
                        if ($campaign) {
                            $campaignId = $message->campaign_id;

                            $sentCount = DB::table('campaign_messages')
                                ->where('campaign_id', $campaignId)
                                ->where('status', '!=', 'failed')
                                ->where(function ($q) {
                                    $q->whereNotNull('sent_at')
                                      ->orWhere('status', 'sent');
                                })->count();

                            $deliveredCount = DB::table('campaign_messages')
                                ->where('campaign_id', $campaignId)
                                ->where('status', '!=', 'failed')
                                ->where(function ($q) {
                                    $q->whereNotNull('delivered_at')
                                      ->orWhere('status', 'delivered');
                                })->count();

                            $readCount = DB::table('campaign_messages')
                                ->where('campaign_id', $campaignId)
                                ->where('status', '!=', 'failed')
                                ->where(function ($q) {
                                    $q->whereNotNull('read_at')
                                      ->orWhere('status', 'read');
                                })->count();

                            $failedCount = DB::table('campaign_messages')
                                ->where('campaign_id', $campaignId)
                                ->where('status', 'failed')
                                ->count();

                            $campaignUpdate = [
                                'sent_count' => $sentCount,
                                'delivered_count' => $deliveredCount,
                                'read_count' => $readCount,
                                'failed_count' => $failedCount,
                                'updated_at' => $now,
                            ];

                            $pendingCount = DB::table('campaign_messages')
                                ->where('campaign_id', $campaignId)
                                ->where('status', 'pending')
                                ->count();

                            if ($pendingCount === 0) {
                                $campaignUpdate['status'] = 'completed';
                                $campaignUpdate['completed_at'] = $now;
                            }

                            DB::table('campaigns')->where('id', $campaignId)->update($campaignUpdate);
                        }
                    }
                }
            }

            if (Schema::hasTable('webhook_logs')) {
                DB::table('webhook_logs')->insert([
                    'event' => 'message.update',
                    'payload' => json_encode($request->all()),
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $dbStatus,
            ]);
        }

        // Event umum lainnya
        if (Schema::hasTable('webhook_logs')) {
            DB::table('webhook_logs')->insert([
                'event' => $event ?? 'unknown',
                'payload' => json_encode($request->all()),
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
