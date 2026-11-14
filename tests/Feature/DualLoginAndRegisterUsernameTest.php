<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DualLoginAndRegisterUsernameTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'Pengguna Biasa',
            'username' => 'userbiasa',
            'email' => 'userbiasa@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }

    public function test_user_can_login_with_email(): void
    {
        $response = $this->post(route('login.post'), [
            'login' => 'dzulfikrialifajri@gmail.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_user_can_login_with_username(): void
    {
        $response = $this->post(route('login.post'), [
            'login' => 'dzulfikri',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_user_can_login_with_username_case_insensitive(): void
    {
        $response = $this->post(route('login.post'), [
            'login' => 'DZULFIKRI',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_login_with_backward_compatible_email_field(): void
    {
        $response = $this->post(route('login.post'), [
            'email' => 'dzulfikri',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->post(route('login.post'), [
            'login' => 'dzulfikri',
            'password' => 'salah_password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_check_username_endpoint_validates_empty_and_length(): void
    {
        $resEmpty = $this->getJson(route('register.check-username', ['username' => '']));
        $resEmpty->assertStatus(422)
                 ->assertJson(['valid' => false, 'available' => false]);

        $resShort = $this->getJson(route('register.check-username', ['username' => 'ab']));
        $resShort->assertStatus(422)
                 ->assertJson(['valid' => false, 'available' => false]);

        $resLong = $this->getJson(route('register.check-username', ['username' => str_repeat('a', 35)]));
        $resLong->assertStatus(422)
                ->assertJson(['valid' => false, 'available' => false]);
    }

    public function test_check_username_endpoint_validates_regex_and_reserved_words(): void
    {
        $resInvalidChars = $this->getJson(route('register.check-username', ['username' => 'user name!#']));
        $resInvalidChars->assertStatus(422)
                        ->assertJson(['valid' => false, 'available' => false]);

        $resReserved = $this->getJson(route('register.check-username', ['username' => 'admin']));
        $resReserved->assertStatus(422)
                    ->assertJson(['valid' => false, 'available' => false]);
    }

    public function test_check_username_endpoint_detects_existing_username(): void
    {
        $response = $this->getJson(route('register.check-username', ['username' => 'dzulfikri']));
        $response->assertStatus(200)
                 ->assertJson([
                     'valid' => true,
                     'available' => false,
                 ]);
    }

    public function test_check_username_endpoint_returns_available_for_new_username(): void
    {
        $response = $this->getJson(route('register.check-username', ['username' => 'pengguna_baru.2026']));
        $response->assertStatus(200)
                 ->assertJson([
                     'valid' => true,
                     'available' => true,
                 ]);
    }

    public function test_register_creates_new_user_with_username(): void
    {
        $response = $this->post(route('register.post'), [
            'name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budi@example.com',
            'role' => 'user',
        ]);

        $newUser = User::where('username', 'budisantoso')->first();
        $this->assertNotNull($newUser);
        $this->assertAuthenticatedAs($newUser);
    }

    public function test_register_fails_if_username_already_exists(): void
    {
        $response = $this->post(route('register.post'), [
            'name' => 'Kembaran Dzulfikri',
            'username' => 'dzulfikri', // sudah dipakai
            'email' => 'kembaran@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('users', [
            'email' => 'kembaran@example.com',
        ]);
    }

    public function test_regular_user_does_not_see_user_management_in_sidebar(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(route('admin.users.index'));
        $response->assertDontSee(__('nav.user_management'));
    }

    public function test_superadmin_sees_user_management_in_sidebar(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.users.index'));
        $response->assertSee(__('nav.user_management'));
    }

    public function test_regular_user_is_forbidden_from_accessing_admin_user_routes(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.users.index'));
        $response->assertStatus(403);
    }
}
