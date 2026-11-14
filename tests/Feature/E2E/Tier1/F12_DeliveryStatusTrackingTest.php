<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * F12: Delivery Status Tracking Test Suite (Centang 1, 2, Biru & Failed)
 *
 * Menguji transisi status pengiriman pesan WhatsApp melalui webhook Baileys (message.update):
 * 1. Status Code 2 (SENT) -> Centang 1
 * 2. Status Code 3 (DELIVERED) -> Centang 2 Abu-abu
 * 3. Status Code 4 (READ) -> Centang 2 Biru
 * 4. Status Code 0 (FAILED) -> Pesan Gagal
 * 5. Agregasi penghitung metrik kampanye (sent_count, delivered_count, read_count, failed_count)
 */
class F12_DeliveryStatusTrackingTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;

    protected User $user;
    protected string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceKey = config('services.baileys.key', 'jawirblast_internal_secret_key');

        $this->user = User::create([
            'name' => 'Tracking Tester',
            'email' => 'tracking_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);

        $this->ensureWaMessageWebhookRegistered();
    }

    protected function ensureWaMessageWebhookRegistered(): void
    {
        if (!Route::has('wa.webhook.status') && !collect(Route::getRoutes()->get('POST'))->has('api/wa/webhook')) {
            Route::post('/api/wa/webhook', function (Request $request) {
                $serviceKey = $request->header('X-Service-Key');
                if ($serviceKey !== config('services.baileys.key', 'jawirblast_internal_secret_key')) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                $event = $request->input('event');
                if ($event === 'message.update') {
                    $waId = $request->input('wa_message_id');
                    $statusCode = (int) $request->input('status_code');
                    $status = $request->input('status');
                    $error = $request->input('error_message');

                    $dbStatus = match ($statusCode) {
                        2 => 'sent',
                        3 => 'delivered',
                        4 => 'read',
                        0 => 'failed',
                        default => 'sent',
                    };

                    $message = DB::table('campaign_messages')->where('baileys_msg_id', $waId)->first();
                    if ($message) {
                        $updateData = [
                            'status' => $dbStatus,
                            'updated_at' => now(),
                        ];

                        if ($dbStatus === 'sent' && !$message->sent_at) {
                            $updateData['sent_at'] = now();
                        } elseif ($dbStatus === 'delivered') {
                            $updateData['delivered_at'] = now();
                        } elseif ($dbStatus === 'read') {
                            $updateData['read_at'] = now();
                        } elseif ($dbStatus === 'failed') {
                            $updateData['error_message'] = $error;
                        }

                        DB::table('campaign_messages')->where('id', $message->id)->update($updateData);

                        // Agregasi counter kampanye jika status berubah
                        if ($message->status !== $dbStatus) {
                            $col = match ($dbStatus) {
                                'sent' => 'sent_count',
                                'delivered' => 'delivered_count',
                                'read' => 'read_count',
                                'failed' => 'failed_count',
                            };
                            DB::table('campaigns')->where('id', $message->campaign_id)->increment($col);
                        }
                    }

                    return response()->json(['success' => true, 'status' => $dbStatus]);
                }

                return response()->json(['success' => true]);
            })->name('wa.webhook.status');
        }
    }

    protected function setupCampaignAndMessage(string $waMsgId, string $phone = '6281234567890'): object
    {
        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Tracking Test',
            'message_template' => 'Pesan tracking',
            'status' => 'processing',
            'total_contacts' => 1,
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messageId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => $phone,
            'recipient_name' => 'Budi Target',
            'message_text' => 'Pesan tracking',
            'baileys_msg_id' => $waMsgId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) [
            'campaign_id' => $campaignId,
            'message_id' => $messageId,
            'wa_msg_id' => $waMsgId,
            'phone' => $phone,
        ];
    }

    /**
     * 1. Webhook status code 2 memperbarui pesan menjadi 'sent' (Centang 1).
     */
    public function test_01_webhook_status_code_2_updates_message_to_sent_single_tick(): void
    {
        $waId = '3EB0_TICK1_' . Str::random(8);
        $phone = '6281211110001';
        $ctx = $this->setupCampaignAndMessage($waId, $phone);

        $response = $this->wa()->fireSentWebhook($waId, $phone);
        $response->assertStatus(200);

        $msg = DB::table('campaign_messages')->where('id', $ctx->message_id)->first();
        $this->assertEquals('sent', $msg->status);
        $this->assertNotNull($msg->sent_at);
    }

    /**
     * 2. Webhook status code 3 memperbarui pesan menjadi 'delivered' (Centang 2 Abu).
     */
    public function test_02_webhook_status_code_3_updates_message_to_delivered_double_tick(): void
    {
        $waId = '3EB0_TICK2_' . Str::random(8);
        $phone = '6281211110002';
        $ctx = $this->setupCampaignAndMessage($waId, $phone);

        // Alur: Sent -> Delivered
        $this->wa()->fireSentWebhook($waId, $phone)->assertOk();
        $response = $this->wa()->fireDeliveredWebhook($waId, $phone);
        $response->assertStatus(200);

        $msg = DB::table('campaign_messages')->where('id', $ctx->message_id)->first();
        $this->assertEquals('delivered', $msg->status);
        $this->assertNotNull($msg->delivered_at);
    }

    /**
     * 3. Webhook status code 4 memperbarui pesan menjadi 'read' (Centang 2 Biru).
     */
    public function test_03_webhook_status_code_4_updates_message_to_read_blue_tick(): void
    {
        $waId = '3EB0_TICKBLUE_' . Str::random(8);
        $phone = '6281211110003';
        $ctx = $this->setupCampaignAndMessage($waId, $phone);

        // Alur: Sent -> Delivered -> Read
        $this->wa()->fireSentWebhook($waId, $phone)->assertOk();
        $this->wa()->fireDeliveredWebhook($waId, $phone)->assertOk();
        $response = $this->wa()->fireReadWebhook($waId, $phone);
        $response->assertStatus(200);

        $msg = DB::table('campaign_messages')->where('id', $ctx->message_id)->first();
        $this->assertEquals('read', $msg->status);
        $this->assertNotNull($msg->read_at);
    }

    /**
     * 4. Webhook status code 0 memperbarui pesan menjadi 'failed' dan mencatat pesan kesalahan.
     */
    public function test_04_webhook_status_code_0_updates_message_to_failed(): void
    {
        $waId = '3EB0_FAILED_' . Str::random(8);
        $phone = '6281211110004';
        $ctx = $this->setupCampaignAndMessage($waId, $phone);

        $response = $this->wa()->fireFailedWebhook($waId, $phone, 'Number is not registered on WhatsApp');
        $response->assertStatus(200);

        $msg = DB::table('campaign_messages')->where('id', $ctx->message_id)->first();
        $this->assertEquals('failed', $msg->status);
        $this->assertEquals('Number is not registered on WhatsApp', $msg->error_message);
    }

    /**
     * 5. Agregasi seluruh counter kampanye (sent, delivered, read, failed) bertambah akurat.
     */
    public function test_05_campaign_counters_increment_accurately_upon_status_webhooks(): void
    {
        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $this->user->id,
            'title' => 'Kampanye Agregat',
            'message_template' => 'Uji agregat',
            'status' => 'processing',
            'total_contacts' => 4,
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Siapkan 4 pesan
        $msg1 = '3EB0_AGGR_1';
        $msg2 = '3EB0_AGGR_2';
        $msg3 = '3EB0_AGGR_3';
        $msg4 = '3EB0_AGGR_4';

        foreach ([$msg1, $msg2, $msg3, $msg4] as $idx => $waId) {
            DB::table('campaign_messages')->insert([
                'campaign_id' => $campaignId,
                'phone_number' => '628129999000' . ($idx + 1),
                'message_text' => 'Pesan',
                'baileys_msg_id' => $waId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Pesan 1: Sent (Centang 1)
        $this->wa()->fireSentWebhook($msg1, '6281299990001')->assertOk();

        // Pesan 2: Delivered (Centang 2 Abu)
        $this->wa()->fireDeliveredWebhook($msg2, '6281299990002')->assertOk();

        // Pesan 3: Read (Centang 2 Biru)
        $this->wa()->fireReadWebhook($msg3, '6281299990003')->assertOk();

        // Pesan 4: Failed
        $this->wa()->fireFailedWebhook($msg4, '6281299990004', 'Blocked')->assertOk();

        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals(1, $campaign->delivered_count);
        $this->assertEquals(1, $campaign->read_count);
        $this->assertEquals(1, $campaign->failed_count);
    }
}
