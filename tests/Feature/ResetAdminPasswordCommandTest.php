<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_reset_password_for_existing_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@jawirblast.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $this->artisan('jawirblast:reset-admin', [
            'email' => 'admin@jawirblast.com',
            'password' => 'newpassword123',
        ])
        ->expectsOutput('Password untuk admin [admin@jawirblast.com] berhasil direset.')
        ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_can_create_new_admin_if_not_found(): void
    {
        $this->artisan('jawirblast:reset-admin', [
            'email' => 'newadmin@jawirblast.com',
            'password' => 'supersecret123',
        ])
        ->expectsQuestion('Akun belum ditemukan. Masukkan nama untuk akun admin baru:', 'Super Admin')
        ->assertSuccessful();

        $user = User::where('email', 'newadmin@jawirblast.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Super Admin', $user->name);
        $this->assertTrue(Hash::check('supersecret123', $user->password));
    }

    public function test_rejects_invalid_email_or_short_password(): void
    {
        $this->artisan('jawirblast:reset-admin', [
            'email' => 'invalid-email',
            'password' => 'short',
        ])
        ->expectsOutput('Alamat email tidak valid: invalid-email')
        ->assertFailed();
    }
}
