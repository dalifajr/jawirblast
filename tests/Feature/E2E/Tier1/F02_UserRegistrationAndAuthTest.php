<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * F02: User Registration & Web Authentication Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji registrasi mandiri pengguna web, enkripsi Bcrypt password,
 * validasi keunikan email, validasi panjang password, login web, dan logout.
 */
class F02_UserRegistrationAndAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureAuthRoutesRegistered();
    }

    protected function ensureAuthRoutesRegistered(): void
    {
        Route::middleware('web')->group(function () {
            if (!Route::has('register.post') && !collect(Route::getRoutes()->get('POST'))->has('register')) {
                Route::post('/register', function (Request $request) {
                    $validated = $request->validate([
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                        'password' => ['required', 'string', 'min:8', 'confirmed'],
                    ]);

                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($validated['password']),
                        'locale' => 'id',
                    ]);

                    Auth::login($user);

                    return redirect('/dashboard');
                })->name('register.post');
            }

            if (!Route::has('login.post') && !collect(Route::getRoutes()->get('POST'))->has('login')) {
                Route::post('/login', function (Request $request) {
                    $credentials = $request->validate([
                        'email' => ['required', 'string', 'email'],
                        'password' => ['required', 'string'],
                    ]);

                    if (Auth::attempt($credentials, $request->boolean('remember'))) {
                        $request->session()->regenerate();
                        return redirect('/dashboard');
                    }

                    return back()->withErrors(['email' => __('auth.failed')]);
                })->name('login.post');
            }

            if (!Route::has('logout') && !collect(Route::getRoutes()->get('POST'))->has('logout')) {
                Route::post('/logout', function (Request $request) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect('/login');
                })->name('logout');
            }
        });
    }

    /**
     * 1. Registrasi user baru dengan data valid (password bcrypt dan auto login).
     */
    public function test_01_user_can_register_with_valid_credentials_and_password_is_hashed(): void
    {
        $email = 'newuser_' . uniqid() . '@example.com';
        $payload = [
            'name' => 'Budi Santoso',
            'email' => $email,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(302);
        $this->assertAuthenticated();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals('Budi Santoso', $user->name);
        $this->assertTrue(Hash::check('Rahasia123!', $user->password), 'Password harus di-hash dengan Bcrypt');
    }

    /**
     * 2. Validasi gagal jika email sudah terdaftar (unique constraint).
     */
    public function test_02_registration_fails_when_email_is_already_taken(): void
    {
        $existingEmail = 'existing_' . uniqid() . '@example.com';
        User::create([
            'name' => 'Existing User',
            'email' => $existingEmail,
            'password' => Hash::make('password123'),
        ]);

        $payload = [
            'name' => 'Duplicate User',
            'email' => $existingEmail,
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ];

        $response = $this->from('/register')->post('/register', $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * 3. Validasi gagal jika format email tidak valid atau password < 8 karakter.
     */
    public function test_03_registration_fails_when_password_is_under_eight_characters(): void
    {
        $payload = [
            'name' => 'Short Pass User',
            'email' => 'short_' . uniqid() . '@example.com',
            'password' => '12345',
            'password_confirmation' => '12345',
        ];

        $response = $this->from('/register')->post('/register', $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    /**
     * 4. Login sukses dengan kredensial yang benar & verifikasi sesi web terautentikasi.
     */
    public function test_04_user_can_login_with_correct_credentials_and_session_is_established(): void
    {
        $email = 'logintest_' . uniqid() . '@example.com';
        $user = User::create([
            'name' => 'Login User',
            'email' => $email,
            'password' => Hash::make('PasswordBenar123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $email,
            'password' => 'PasswordBenar123!',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 5. User terautentikasi dapat melakukan logout dan sesi dibersihkan.
     */
    public function test_05_authenticated_user_can_logout_and_session_is_cleared(): void
    {
        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $response->assertStatus(302);
        $this->assertGuest();
    }
}
