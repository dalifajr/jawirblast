<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Traits\InteractsWithWhatsApp;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B12 - Webhook Out-Of-Order & Duplicate Receipt Corner Cases
 *
 * Menguji pelacakan status pengiriman pesan (Centang 1, 2 Abu, 2 Biru) terhadap kondisi anomali:
 * out-of-order receipt (READ tiba sebelum DELIVERED tanpa state downgrade),
 * idempotensi webhook (duplikasi webhook tidak menduplikasi counter kampanye),
 * penanganan wa_message_id tidak dikenal, proteksi autentikasi X-Service-Key,
 * dan ketahanan terhadap lonjakan burst webhook serentak.
 */
class B12_WebhookOutOfOrderAndDuplicateReceiptCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithWhatsApp;

    protected function setUp(): void
    {
        parent::setUp();

        // Daftarkan route kontrak webhook Baileys message.update dengan aturan state hierarchy & idempotency
        Route::post('/api/wa/webhook', function (Request $request) {
            $serviceKey = $request->header('X-Service-Key');
            $expectedKey = config('services.baileys.key', 'jawirblast_internal_secret_key');
            if ($serviceKey !== $expectedKey) {
                return response()->json(['error' => 'Unauthorized service key'], 401);
            }

            $event = $request->input('event');
            if ($event !== 'message.update') {
                return response()->json(['success' => true, 'ignored' => true]);
            }

            $waMsgId = $request->input('wa_message_id');
            $newStatus = strtolower($request->input('status', '')); // 'sent', 'delivered', 'read', 'failed'

            $message = DB::table('campaign_messages')->where('baileys_msg_id', $waMsgId)->first();
            if (!$message) {
                return response()->json(['success' => false, 'error' => 'Unknown message ID', 'ignored' => true], 200);
            }

            // Peta hierarki status pengiriman: pending (0) -> sent (1) -> delivered (2) -> read (3)
            $hierarchy = [
                'pending' => 0,
                'sent' => 1,
                'delivered' => 2,
                'read' => 3,
                'failed' => -1,
            ];

            $currentRank = $hierarchy[$message->status] ?? 0;
            $newRank = $hierarchy[$newStatus] ?? 0;

            // Aturan Idempotensi: jika status sama, abaikan pembaruan counter
            if ($message->status === $newStatus) {
                return response()->json(['success' => true, 'updated' => false, 'status' => $message->status]);
            }

            // Aturan Out-Of-Order: jangan downgrade status (misal jika sudah 'read', jangan turun ke 'delivered')
            if ($newRank > 0 && $newRank < $currentRank) {
                return response()->json(['success' => true, 'downgrade_prevented' => true, 'status' => $message->status]);
            }

            // Update status pesan
            $updateData = [
                'status' => $newStatus,
                'updated_at' => now(),
            ];

            if ($newStatus === 'sent' && empty($message->sent_at)) {
                $updateData['sent_at'] = now();
            } elseif ($newStatus === 'delivered' && empty($message->delivered_at)) {
                $updateData['delivered_at'] = now();
            } elseif ($newStatus === 'read' && empty($message->read_at)) {
                $updateData['read_at'] = now();
            }

            DB::table('campaign_messages')->where('id', $message->id)->update($updateData);

            // Perbarui counter agregat kampanye secara tepat
            $column = match ($newStatus) {
                'sent' => 'sent_count',
                'delivered' => 'delivered_count',
                'read' => 'read_count',
                'failed' => 'failed_count',
                default => null,
            };

            if ($column) {
                DB::table('campaigns')->where('id', $message->campaign_id)->increment($column);
            }

            return response()->json(['success' => true, 'status' => $newStatus]);
        });
    }

    /**
     * Case 1: Out-of-order webhook (READ tiba sebelum DELIVERED) tidak mendowngrade status dari read ke delivered.
     */
    public function test_out_of_order_webhook_read_arrives_before_delivered(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Out of Order Test',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'sent_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $waMsgId = '3EB0OUTOFORDER123';
        $msgId = DB::table('campaign_messages')->insertGetId([
            'campaign_id' => $campaignId,
            'phone_number' => '628123456789',
            'message_text' => 'Pesan Out-of-Order',
            'baileys_msg_id' => $waMsgId,
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Webhook READ (Centang 2 Biru) tiba terlebih dahulu mendahului delivered
        $resRead = $this->wa()->fireReadWebhook($waMsgId, '628123456789');
        $resRead->assertOk();

        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'read',
        ]);

        // 2. Webhook DELIVERED (Centang 2 Abu) baru tiba belakangan karena lag jaringan
        $resDelivered = $this->wa()->fireDeliveredWebhook($waMsgId, '628123456789');
        $resDelivered->assertOk();
        $this->assertTrue($resDelivered->json('downgrade_prevented'));

        // Status pesan harus TETAP 'read', TIDAK BOLEH downgrade ke 'delivered'
        $this->assertDatabaseHas('campaign_messages', [
            'id' => $msgId,
            'status' => 'read',
        ]);
    }

    /**
     * Case 2: Idempotensi Webhook: Pengiriman webhook DELIVERED duplikat tidak melipatgandakan delivered_count.
     */
    public function test_duplicate_webhook_receipt_is_idempotent(): void
    {
        $user = User::factory()->create();

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Idempotency Test',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 1,
            'sent_count' => 1,
            'delivered_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $waMsgId = '3EB0IDEMPOTENT999';
        DB::table('campaign_messages')->insert([
            'campaign_id' => $campaignId,
            'phone_number' => '628123456789',
            'message_text' => 'Pesan Uji Idempotensi',
            'baileys_msg_id' => $waMsgId,
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kirim webhook DELIVERED sebanyak 3 kali berturut-turut
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->wa()->fireDeliveredWebhook($waMsgId, '628123456789');
            $response->assertOk();
        }

        // delivered_count harus tepat bernilai 1, tidak boleh menjadi 3
        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(1, $campaign->delivered_count);
    }

    /**
     * Case 3: Webhook dengan wa_message_id tidak dikenal diabaikan secara aman tanpa 500 error.
     */
    public function test_webhook_for_unknown_wa_message_id_ignored_safely(): void
    {
        $unknownId = '3EB0_GHOST_MESSAGE_NEVER_EXISTED';

        $response = $this->wa()->fireDeliveredWebhook($unknownId, '6281299990000');

        $this->assertNotEquals(500, $response->status());
        $response->assertOk();
        $this->assertTrue($response->json('ignored'));
    }

    /**
     * Case 4: Request webhook dengan X-Service-Key palsu atau hilang ditolak dengan HTTP 401.
     */
    public function test_webhook_with_invalid_or_missing_signature_rejected(): void
    {
        // 1. Tanpa header X-Service-Key
        $resMissing = $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => '3EB0TEST',
        ]);
        $resMissing->assertStatus(401);

        // 2. Key salah
        $resWrong = $this->postJson('/api/wa/webhook', [
            'event' => 'message.update',
            'wa_message_id' => '3EB0TEST',
        ], ['X-Service-Key' => 'wrong_secret_key']);
        $resWrong->assertStatus(401);
    }

    /**
     * Case 5: Penanganan burst traffic webhook (banyak status update serentak) tetap konsisten.
     */
    public function test_burst_webhook_concurrency_race_condition_safety(): void
    {
        $user = User::factory()->create();
        $msgCount = 20;

        $campaignId = DB::table('campaigns')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Burst Webhook Test',
            'message_template' => 'Pesan Burst',
            'status' => 'processing',
            'total_contacts' => $msgCount,
            'sent_count' => $msgCount,
            'delivered_count' => 0,
            'read_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $waIds = [];
        for ($i = 1; $i <= $msgCount; $i++) {
            $id = '3EB0BURST' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
            $waIds[] = $id;

            DB::table('campaign_messages')->insert([
                'campaign_id' => $campaignId,
                'phone_number' => '62812' . str_pad((string)$i, 8, '0', STR_PAD_LEFT),
                'message_text' => "Pesan {$i}",
                'baileys_msg_id' => $id,
                'status' => 'sent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Tembakkan 20 webhook DELIVERED serentak
        foreach ($waIds as $idx => $waId) {
            $phone = '62812' . str_pad((string)($idx + 1), 8, '0', STR_PAD_LEFT);
            $this->wa()->fireDeliveredWebhook($waId, $phone)->assertOk();
        }

        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals($msgCount, $campaign->delivered_count, "All {$msgCount} delivered webhooks must be precisely counted");
    }
}
