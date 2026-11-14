<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\CampaignDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BaileysFake;
use Tests\TestCase;

class CampaignDispatchTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $otherUser;
    protected WhatsAppSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->session = WhatsAppSession::firstOrCreate(
            ['session_id' => 'default'],
            [
                'status' => 'connected',
                'user_jid' => '6281234567890:1@s.whatsapp.net',
                'last_connected_at' => now(),
            ]
        );
    }

    /**
     * 1. Pengguna tanpa login dialihkan ke halaman login saat mengakses /campaigns.
     */
    public function test_01_unauthenticated_user_redirected_to_login(): void
    {
        $this->get('/campaigns')->assertRedirect('/login');
        $this->get('/campaigns/create')->assertRedirect('/login');
    }

    /**
     * 2. Pengguna terautentikasi dapat melihat daftar kampanye dan metrik.
     */
    public function test_02_authenticated_user_can_view_campaign_list_and_metrics(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Promo Diskon Kemerdekaan',
            'message_template' => 'Halo {name}, nikmati promo!',
            'status' => 'draft',
            'total_contacts' => 10,
        ]);

        $response = $this->actingAs($this->user)->get('/campaigns');

        $response->assertOk();
        $response->assertSee('Promo Diskon Kemerdekaan');
        $response->assertSee('Total Kampanye');
    }

    /**
     * 3. Isolasi Multi-Tenant: Pengguna tidak dapat melihat atau mengontrol kampanye milik orang lain.
     */
    public function test_03_multi_tenant_isolation_prevents_access_to_other_users_campaign(): void
    {
        $otherCampaign = Campaign::create([
            'user_id' => $this->otherUser->id,
            'title' => 'Rahasia Pengguna Lain',
            'message_template' => 'Pesan rahasia',
            'status' => 'draft',
        ]);

        // Coba lihat kampanye orang lain -> harus 403
        $this->actingAs($this->user)
            ->get(route('campaigns.show', $otherCampaign->id))
            ->assertForbidden();

        // Coba pause kampanye orang lain -> harus 403
        $this->actingAs($this->user)
            ->post(route('campaigns.pause', $otherCampaign->id))
            ->assertForbidden();

        // Coba cancel kampanye orang lain -> harus 403
        $this->actingAs($this->user)
            ->post(route('campaigns.cancel', $otherCampaign->id))
            ->assertForbidden();
    }

    /**
     * 4. Validasi input pembuatan kampanye baru.
     */
    public function test_04_validates_campaign_creation_inputs(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('campaigns.store'), [
                'title' => '',
                'message_template' => '',
                'target_type' => 'invalid_type',
            ]);

        $response->assertSessionHasErrors(['title', 'message_template', 'target_type']);
    }

    /**
     * 5. Pembuatan kampanye membuat baris pesan pending untuk seluruh kontak sasaran.
     */
    public function test_05_creates_campaign_record_and_pending_messages(): void
    {
        Queue::fake();

        $c1 = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Kontak 1',
            'phone_number' => '6281200000001',
            'status' => 'active',
        ]);
        $c2 = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Kontak 2',
            'phone_number' => '6281200000002',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('campaigns.store'), [
                'title' => 'Kampanye Kilat',
                'message_template' => '{Halo|Hai} {name}, ini pesan uji!',
                'min_delay_seconds' => 3,
                'max_delay_seconds' => 7,
                'target_type' => 'selected',
                'contact_ids' => [$c1->id, $c2->id],
                'action' => 'save_draft',
            ]);

        $response->assertRedirect();

        $campaign = Campaign::where('title', 'Kampanye Kilat')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals('draft', $campaign->status);
        $this->assertEquals(2, $campaign->total_contacts);

        $messages = CampaignMessage::where('campaign_id', $campaign->id)->get();
        $this->assertCount(2, $messages);
        foreach ($messages as $msg) {
            $this->assertEquals('pending', $msg->status);
            $this->assertStringNotContainsString('{name}', $msg->message_text);
        }
    }

    /**
     * 6. Aksi Start memicu status processing dan mendispatch SendCampaignMessageJob dengan delay bertingkat.
     */
    public function test_06_campaign_start_action_transitions_status_to_processing_and_dispatches_jobs(): void
    {
        Queue::fake();

        $c1 = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Target 1',
            'phone_number' => '6281211111111',
            'status' => 'active',
        ]);

        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Siaran Langsung',
            'message_template' => 'Halo {name}!',
            'status' => 'draft',
            'min_delay_seconds' => 2,
            'max_delay_seconds' => 4,
            'total_contacts' => 1,
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $c1->id,
            'phone_number' => $c1->phone_number,
            'recipient_name' => $c1->name,
            'message_text' => 'Halo Target 1!',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)->post(route('campaigns.start', $campaign->id));

        $response->assertRedirect();
        $campaign->refresh();
        $this->assertEquals('processing', $campaign->status);
        $this->assertNotNull($campaign->started_at);

        Queue::assertPushedOn('broadcasts', SendCampaignMessageJob::class);
    }

    /**
     * 7. Siklus Kontrol: Pause, Resume, dan Cancel kampanye.
     */
    public function test_07_campaign_pause_resume_and_cancel_lifecycle(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Siklus Hidup Kampanye',
            'message_template' => 'Template',
            'status' => 'processing',
            'total_contacts' => 2,
        ]);

        $msg = CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '6281233333333',
            'message_text' => 'Isi pesan',
            'status' => 'pending',
        ]);

        // A. Pause
        $this->actingAs($this->user)->post(route('campaigns.pause', $campaign->id))->assertRedirect();
        $campaign->refresh();
        $this->assertEquals('paused', $campaign->status);

        // B. Resume
        $this->actingAs($this->user)->post(route('campaigns.resume', $campaign->id))->assertRedirect();
        $campaign->refresh();
        $this->assertEquals('processing', $campaign->status);

        // C. Cancel
        $this->actingAs($this->user)->post(route('campaigns.cancel', $campaign->id))->assertRedirect();
        $campaign->refresh();
        $this->assertEquals('cancelled', $campaign->status);

        $msg->refresh();
        $this->assertEquals('failed', $msg->status);
        $this->assertEquals('Campaign was cancelled by user', $msg->error_message);
    }

    /**
     * 8. Endpoint AJAX preview-spintax mengembalikan jumlah variasi dan sampel teks.
     */
    public function test_08_preview_spintax_endpoint_returns_json_count_and_samples(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('campaigns.preview_spintax'), [
            'template' => '{Halo|Hai} {name}, ini penawaran {spesial|eksklusif}!',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 4,
        ]);
        $response->assertJsonStructure(['samples']);
    }

    /**
     * 9. Endpoint status kampanye mengembalikan metrik real-time JSON.
     */
    public function test_09_campaign_status_endpoint_returns_real_time_metrics(): void
    {
        $campaign = Campaign::create([
            'user_id' => $this->user->id,
            'title' => 'Test Status JSON',
            'message_template' => 'Pesan',
            'status' => 'processing',
            'total_contacts' => 10,
            'sent_count' => 6,
            'read_count' => 4,
            'failed_count' => 1,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('campaigns.status', $campaign->id));

        $response->assertOk();
        $response->assertJson([
            'id' => $campaign->id,
            'status' => 'processing',
            'total_contacts' => 10,
            'sent_count' => 6,
            'read_count' => 4,
            'failed_count' => 1,
            'progress_percent' => 70, // (6 + 1) / 10 = 70%
            'is_completed' => false,
        ]);
    }
}
