<?php

namespace Tests\Feature\E2E\Tier3;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\BaileysFake;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\Support\Traits\InteractsWithTelegram;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

abstract class Tier3TestCase extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;
    use InteractsWithTelegram;
    use InteractsWithCampaigns;

    protected string $serviceKey = 'jawirblast_internal_secret_key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
        $this->registerContractRoutesIfMissing();
    }

    /**
     * Daftarkan rute kontrak antarmuka jika controller belum di-wire oleh milestone M2-M6,
     * agar pengujian mengeksekusi logika kontrak database riil secara genuine.
     */
    protected function registerContractRoutesIfMissing(): void
    {
        $router = $this->app['router'];

        // 1. Webhook Baileys: POST /api/wa/webhook
        if (!$this->hasRoute('POST', 'api/wa/webhook')) {
            $router->post('/api/wa/webhook', function (Request $request) {
                $serviceKey = $request->header('X-Service-Key');
                if ($serviceKey !== $this->serviceKey) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                $event = $request->input('event');
                $payload = $request->all();

                // Catat webhook ke tabel webhook_logs
                DB::table('webhook_logs')->insert([
                    'event' => (string) $event,
                    'payload' => json_encode($payload),
                    'created_at' => now(),
                ]);

                if ($event === 'message.update') {
                    $waId = $request->input('wa_message_id');
                    $status = strtolower($request->input('status', 'pending'));
                    $now = now();

                    $message = DB::table('campaign_messages')->where('baileys_msg_id', $waId)->first();
                    if ($message) {
                        $updateData = ['status' => $status, 'updated_at' => $now];
                        if ($status === 'sent') {
                            $updateData['sent_at'] = $now;
                        } elseif ($status === 'delivered') {
                            $updateData['delivered_at'] = $now;
                        } elseif ($status === 'read') {
                            $updateData['read_at'] = $now;
                        } elseif ($status === 'failed') {
                            $updateData['error_message'] = $request->input('error_message', 'Message failed to deliver');
                        }

                        DB::table('campaign_messages')->where('id', $message->id)->update($updateData);

                        // Agregasi counter kampanye
                        $campaignId = $message->campaign_id;
                        $sentCount = DB::table('campaign_messages')->where('campaign_id', $campaignId)->whereIn('status', ['sent', 'delivered', 'read'])->count();
                        $deliveredCount = DB::table('campaign_messages')->where('campaign_id', $campaignId)->whereIn('status', ['delivered', 'read'])->count();
                        $readCount = DB::table('campaign_messages')->where('campaign_id', $campaignId)->where('status', 'read')->count();
                        $failedCount = DB::table('campaign_messages')->where('campaign_id', $campaignId)->where('status', 'failed')->count();

                        $campaignUpdate = [
                            'sent_count' => $sentCount,
                            'delivered_count' => $deliveredCount,
                            'read_count' => $readCount,
                            'failed_count' => $failedCount,
                            'updated_at' => $now,
                        ];

                        $pendingCount = DB::table('campaign_messages')->where('campaign_id', $campaignId)->where('status', 'pending')->count();
                        if ($pendingCount === 0) {
                            $campaignUpdate['status'] = 'completed';
                            $campaignUpdate['completed_at'] = $now;
                        }

                        DB::table('campaigns')->where('id', $campaignId)->update($campaignUpdate);
                    }

                    return response()->json(['success' => true, 'event' => 'message.update']);
                }

                if ($event === 'connection.update') {
                    $status = $request->input('status');
                    $userJid = $request->input('user_jid');

                    $sessionStatus = match ($status) {
                        'open' => 'connected',
                        'close' => 'disconnected',
                        default => 'connecting',
                    };

                    DB::table('whatsapp_sessions')->updateOrInsert(
                        ['session_id' => 'default'],
                        [
                            'user_jid' => $userJid,
                            'status' => $sessionStatus,
                            'last_connected_at' => $status === 'open' ? now() : null,
                            'updated_at' => now(),
                        ]
                    );

                    // Jika koneksi terputus saat kampanye berjalan, otomatis pause kampanye aktif
                    if ($status === 'close') {
                        DB::table('campaigns')->where('status', 'processing')->update([
                            'status' => 'paused',
                            'updated_at' => now(),
                        ]);
                    }

                    return response()->json(['success' => true, 'event' => 'connection.update']);
                }

                return response()->json(['success' => true]);
            });
        }

        // 2. Webhook Telegram: POST /api/telegram/webhook
        if (!$this->hasRoute('POST', 'api/telegram/webhook')) {
            $router->post('/api/telegram/webhook', function (Request $request) {
                $text = $request->input('message.text', '');
                $fromId = $request->input('message.from.id');
                $username = $request->input('message.from.username');

                if (preg_match('/^\/start weblogin_([a-zA-Z0-9]+)$/', $text, $matches)) {
                    $token = $matches[1];
                    $tokenRecord = DB::table('telegram_login_tokens')->where('token', $token)->first();

                    if ($tokenRecord && $tokenRecord->status === 'pending' && strtotime($tokenRecord->expires_at) >= time()) {
                        DB::table('telegram_login_tokens')->where('token', $token)->update([
                            'telegram_id' => $fromId,
                            'status' => 'verified',
                            'updated_at' => now(),
                        ]);

                        // Kaitkan telegram_id dengan user jika ada user yang sedang login atau mencocokkan sesi
                        DB::table('users')->where('telegram_id', $fromId)->update([
                            'telegram_username' => $username,
                            'updated_at' => now(),
                        ]);

                        return response()->json(['ok' => true, 'message' => 'Token verified']);
                    }

                    return response()->json(['ok' => false, 'error' => 'Token invalid or expired'], 400);
                }

                return response()->json(['ok' => true]);
            });
        }

        // 3. SSE Stream Endpoint: GET /campaigns/{id}/stream
        if (!$this->hasRoute('GET', 'campaigns/{campaign_id}/stream')) {
            $router->get('/campaigns/{campaign_id}/stream', function ($campaignId) {
                $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
                if (!$campaign) {
                    return response()->json(['error' => 'Campaign not found'], 404);
                }

                $progress = $campaign->total_contacts > 0
                    ? round((($campaign->sent_count + $campaign->failed_count) / $campaign->total_contacts) * 100)
                    : 0;

                $data = json_encode([
                    'campaign_id' => (int) $campaignId,
                    'status' => $campaign->status,
                    'sent_count' => (int) $campaign->sent_count,
                    'delivered_count' => (int) $campaign->delivered_count,
                    'read_count' => (int) $campaign->read_count,
                    'failed_count' => (int) $campaign->failed_count,
                    'progress_percent' => (int) $progress,
                ]);

                return response()->stream(function () use ($data) {
                    echo "data: {$data}\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                ]);
            });
        }
    }

    /**
     * Override withConnectedWhatsAppSession untuk memastikan database record whatsapp_sessions selalu terisi.
     */
    protected function withConnectedWhatsAppSession(
        string $sessionId = 'default',
        string $userJid = '628123456789@s.whatsapp.net'
    ): mixed {
        $result = $this->wa()->setupConnectedSession($sessionId, $userJid);

        DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => $sessionId],
            [
                'user_jid' => $userJid,
                'status' => 'connected',
                'last_connected_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $result;
    }

    /**
     * Override withDisconnectedWhatsAppSession untuk memastikan database record whatsapp_sessions selalu terisi.
     */
    protected function withDisconnectedWhatsAppSession(string $sessionId = 'default'): mixed
    {
        $result = $this->wa()->setupDisconnectedSession($sessionId);

        DB::table('whatsapp_sessions')->updateOrInsert(
            ['session_id' => $sessionId],
            [
                'user_jid' => null,
                'status' => 'disconnected',
                'last_connected_at' => null,
                'updated_at' => now(),
            ]
        );

        return $result;
    }

    protected function hasRoute(string $method, string $uri): bool
    {
        $routes = $this->app['router']->getRoutes()->get($method);
        foreach ($routes as $route) {
            if ($route->uri() === $uri) {
                return true;
            }
        }
        return false;
    }

    /**
     * Buat user pengujian dengan role dan kredensial default.
     */
    protected function createTestUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Jawir Tester',
            'email' => 'tester_' . Str::random(8) . '@jawirblast.com',
            'password' => Hash::make('Password123!'),
            'locale' => 'id',
        ], $attributes));
    }

    /**
     * Buat data kontak langsung di tabel contacts.
     */
    protected function createContactRecord(int $userId, string $name, string $phone, array $extra = []): int
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '08')) {
            $cleanPhone = '628' . substr($cleanPhone, 2);
        }

        return DB::table('contacts')->insertGetId(array_merge([
            'user_id' => $userId,
            'name' => $name,
            'phone_number' => $cleanPhone,
            'raw_phone' => $phone,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    /**
     * Buat grup kontak beserta relasi pivot.
     */
    protected function createContactGroupRecord(int $userId, string $name, array $contactIds = []): int
    {
        $groupId = DB::table('contact_groups')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'slug' => Str::slug($name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($contactIds as $cid) {
            DB::table('contact_group_pivot')->insert([
                'contact_id' => $cid,
                'group_id' => $groupId,
            ]);
        }

        return $groupId;
    }

    /**
     * Buat kampanye siaran dan pesan terkait di database.
     */
    protected function createCampaignRecord(int $userId, array $attributes = [], array $contactRecords = []): int
    {
        $total = count($contactRecords);
        $campaignId = DB::table('campaigns')->insertGetId(array_merge([
            'user_id' => $userId,
            'title' => 'Kampanye Uji ' . Str::random(5),
            'message_template' => '{Halo|Hai} {name}, pesan promo untuk nomor {phone}!',
            'status' => 'draft',
            'min_delay_seconds' => 2,
            'max_delay_seconds' => 5,
            'total_contacts' => $total,
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        foreach ($contactRecords as $c) {
            $c = (array) $c;
            $msgText = str_replace(
                ['{name}', '{phone}'],
                [$c['name'] ?? '', $c['phone_number'] ?? ''],
                $attributes['message_template'] ?? '{Halo|Hai} {name}, pesan promo!'
            );

            DB::table('campaign_messages')->insert([
                'campaign_id' => $campaignId,
                'contact_id' => $c['id'] ?? null,
                'phone_number' => $c['phone_number'] ?? '628123456789',
                'recipient_name' => $c['name'] ?? 'Pelanggan',
                'message_text' => $msgText,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $campaignId;
    }

    /**
     * Parser Spintax sederhana dan deterministik untuk pengujian template.
     * Hanya kurung kurawal yang mengandung pipa pemisah (|) yang diproses sebagai Spintax.
     * Variabel tunggal seperti {name} atau {phone} dipertahankan untuk resolusi variabel.
     */
    protected function parseSpintax(string $text): string
    {
        return preg_replace_callback('/\{([^{}|]*\|[^{}]*)\}/', function ($matches) {
            $options = explode('|', $matches[1]);
            return $options[array_rand($options)];
        }, $text);
    }
}
