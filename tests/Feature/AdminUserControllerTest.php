<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'role' => 'superadmin',
            'is_suspended' => false,
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'role' => 'user',
            'wallet_balance' => 500000,
            'referral_balance' => 50000,
            'is_suspended' => false,
        ]);
    }

    public function test_superadmin_can_view_user_management_index(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('admin.users.index'));

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('Super Administrator');
    }

    public function test_regular_user_cannot_view_user_management_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_user_detail_json_api_returns_chart_data(): void
    {
        // Buat kampanye & pesan terkirim/gagal
        $campaign = Campaign::create([
            'user_id' => $this->regularUser->id,
            'title' => 'Promo Diskon Akhir Tahun',
            'status' => 'completed',
            'message_template' => 'Halo {name}',
            'pacing_seconds' => 5,
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'contact_id' => null,
            'phone_number' => '628123456789',
            'message_text' => 'Test message',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'contact_id' => null,
            'phone_number' => '628987654321',
            'message_text' => 'Failed message',
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        Withdrawal::create([
            'user_id' => $this->regularUser->id,
            'amount' => 100000,
            'payment_method' => 'BCA',
            'account_info' => '1234567890 a/n John',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->superadmin)->getJson(route('admin.users.show', $this->regularUser->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $this->regularUser->id,
                'name' => 'John Doe',
                'username' => 'johndoe',
            ],
        ]);
        $response->assertJsonStructure([
            'chart' => ['labels', 'sent', 'failed'],
            'withdrawals',
            'campaigns',
        ]);
    }

    public function test_superadmin_can_create_new_user(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('admin.users.store'), [
            'name' => 'Alice Wonderland',
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password' => 'secret12345',
            'role' => 'user',
            'wallet_balance' => 250000,
            'referral_balance' => 15000,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'username' => 'alice',
            'wallet_balance' => 250000,
        ]);
    }

    public function test_superadmin_can_update_user(): void
    {
        $response = $this->actingAs($this->superadmin)->put(route('admin.users.update', $this->regularUser->id), [
            'name' => 'John Doe Updated',
            'username' => 'johnupdated',
            'email' => 'johnupdated@example.com',
            'role' => 'user',
            'wallet_balance' => 750000,
            'referral_balance' => 60000,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'John Doe Updated',
            'wallet_balance' => 750000,
        ]);
    }

    public function test_superadmin_can_suspend_and_unsuspend_user(): void
    {
        $suspendRes = $this->actingAs($this->superadmin)->post(route('admin.users.suspend', $this->regularUser->id), [
            'reason' => 'Spam berlebihan',
        ]);

        $suspendRes->assertRedirect();
        $this->assertTrue($this->regularUser->fresh()->is_suspended);
        $this->assertEquals('Spam berlebihan', $this->regularUser->fresh()->suspended_reason);

        $unsuspendRes = $this->actingAs($this->superadmin)->post(route('admin.users.unsuspend', $this->regularUser->id));
        $unsuspendRes->assertRedirect();
        $this->assertFalse($this->regularUser->fresh()->is_suspended);
    }

    public function test_superadmin_can_impersonate_and_stop_impersonating(): void
    {
        $impRes = $this->actingAs($this->superadmin)->post(route('admin.users.impersonate', $this->regularUser->id));

        $impRes->assertRedirect(route('dashboard'));
        $this->assertEquals($this->regularUser->id, auth()->id());
        $this->assertTrue(session()->has('admin_impersonator_id'));

        $stopRes = $this->post(route('admin.users.stop-impersonating'));
        $stopRes->assertRedirect(route('admin.users.index'));
        $this->assertEquals($this->superadmin->id, auth()->id());
        $this->assertFalse(session()->has('admin_impersonator_id'));
    }
}
