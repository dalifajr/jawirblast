<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikri',
            'email' => 'dzulfikri@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('Dzulfikri Alifajri');
        $response->assertSee('dzulfikri@example.com');
        $response->assertSee(route('admin.users.index'));
    }

    public function test_guest_cannot_view_profile_page(): void
    {
        $response = $this->get(route('profile'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_update_profile_info(): void
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Dzulfikri Updated',
            'username' => 'dzulupdate',
            'email' => 'dzulupdate@example.com',
            'telegram_id' => '12345678',
        ]);

        $response->assertRedirect(route('profile'));
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Dzulfikri Updated',
            'username' => 'dzulupdate',
            'email' => 'dzulupdate@example.com',
            'telegram_id' => '12345678',
        ]);
    }

    public function test_user_can_update_password(): void
    {
        $response = $this->actingAs($this->user)->put(route('profile.password'), [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('profile'));
        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    public function test_sidebar_displays_user_management_and_profile_buttons(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.users.index'));
        $response->assertSee(route('profile'));
    }
}
