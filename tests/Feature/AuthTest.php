<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Halaman formulir login dapat diakses oleh guest (HTTP 200).
     */
    public function test_guest_can_view_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    /**
     * Test 2: Pengguna yang sudah login dialihkan dari halaman login ke dashboard.
     */
    public function test_authenticated_user_redirected_from_login_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/dashboard');
    }

    /**
     * Test 3: Pengguna dapat login dengan kredensial yang valid.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'dzulfikri@jawirblast.test',
            'password' => Hash::make('PasswordAman123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'dzulfikri@jawirblast.test',
            'password' => 'PasswordAman123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test 4: Upaya login gagal jika password salah (session error & tetap guest).
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'dzulfikri@jawirblast.test',
            'password' => Hash::make('PasswordAman123!'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'dzulfikri@jawirblast.test',
            'password' => 'PasswordSalahTotal!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test 5: Upaya login gagal jika email belum terdaftar di sistem.
     */
    public function test_user_cannot_login_with_unregistered_email(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'tidak_ada@jawirblast.test',
            'password' => 'PasswordAman123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test 6: Validasi formulir login diberlakukan (field email dan password wajib diisi).
     */
    public function test_login_validation_rules_enforced(): void
    {
        $response = $this->post('/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    /**
     * Test 7: Rate limiting memblokir upaya brute-force login setelah 5 kali gagal (HTTP 429).
     */
    public function test_login_rate_limiting_throttles_excessive_attempts(): void
    {
        // Bersihkan hit rate limiter sebelum pengujian
        RateLimiter::clear('login.post|127.0.0.1');

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'brute@jawirblast.test',
                'password' => 'salah_' . $i,
            ]);
        }

        // Percobaan ke-6 harus di-throttle oleh middleware throttle:5,1
        $blockedResponse = $this->post('/login', [
            'email' => 'brute@jawirblast.test',
            'password' => 'salah_6',
        ]);

        $blockedResponse->assertStatus(429);
    }

    /**
     * Test 8: Halaman formulir registrasi mandiri dapat diakses oleh guest (HTTP 200).
     */
    public function test_guest_can_view_register_form(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }

    /**
     * Test 9: Pengguna yang sudah login dialihkan dari formulir registrasi ke dashboard.
     */
    public function test_authenticated_user_redirected_from_register_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect('/dashboard');
    }

    /**
     * Test 10: Registrasi mandiri berhasil dengan enkripsi Bcrypt dan sesi otomatis.
     */
    public function test_user_can_register_successfully(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@jawirblast.test',
            'password' => 'RahasiaHebat123!',
            'password_confirmation' => 'RahasiaHebat123!',
        ];

        $response = $this->post('/register', $payload);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'budi@jawirblast.test',
            'name' => 'Budi Santoso',
        ]);

        $user = User::where('email', 'budi@jawirblast.test')->first();
        $this->assertTrue(Hash::check('RahasiaHebat123!', $user->password));
    }

    /**
     * Test 11: Registrasi gagal jika alamat email sudah terdaftar sebelumnya.
     */
    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplikat@jawirblast.test']);

        $response = $this->post('/register', [
            'name' => 'Pengguna Kembar',
            'email' => 'duplikat@jawirblast.test',
            'password' => 'RahasiaHebat123!',
            'password_confirmation' => 'RahasiaHebat123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test 12: Registrasi gagal jika password kurang dari 8 karakter atau konfirmasi tidak cocok.
     */
    public function test_user_cannot_register_with_mismatched_or_short_password(): void
    {
        // 1. Password kurang dari 8 karakter
        $responseShort = $this->post('/register', [
            'name' => 'Pendek',
            'email' => 'pendek@jawirblast.test',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);
        $responseShort->assertSessionHasErrors('password');

        // 2. Konfirmasi password tidak cocok
        $responseMismatch = $this->post('/register', [
            'name' => 'Tidak Cocok',
            'email' => 'mismatch@jawirblast.test',
            'password' => 'PasswordPanjang123!',
            'password_confirmation' => 'PasswordBedaLagi456!',
        ]);
        $responseMismatch->assertSessionHasErrors('password');
    }

    /**
     * Test 13: Logout mengeluarkan pengguna dan menginvalidasi sesi.
     */
    public function test_user_can_logout_and_session_is_invalidated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
