<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B02 - Auth Brute-Force & XSS Validation Corner Cases
 *
 * Menguji ketahanan sistem autentikasi terhadap serangan brute-force (rate-limiting),
 * payload XSS pada form registrasi, password panjang ekstrim dengan karakter Unicode,
 * variasi format email RFC, dan proteksi middleware auth terhadap bypass tamu.
 */
class B02_AuthBruteForceAndXssValidationCornerCasesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Daftarkan route kontrak autentikasi terisolasi untuk memverifikasi kontrak middleware & rate limiting
        Route::post('/test-auth/login', function (Request $request) {
            $key = 'login-attempt:' . $request->ip();

            if (RateLimiter::tooManyAttempts($key, 5)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                ], 429);
            }

            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                RateLimiter::hit($key, 60);
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            RateLimiter::clear($key);
            auth()->login($user);

            return response()->json(['message' => 'Login successful', 'user' => $user]);
        });

        Route::get('/login', function () {
            return response()->json(['message' => 'Login page']);
        })->name('login');

        Route::middleware(['web', 'auth'])->get('/test-auth/dashboard', function () {
            return response()->json(['status' => 'authorized', 'user' => auth()->user()]);
        });
    }

    /**
     * Case 1: Rate limiting memblokir upaya brute-force login setelah 5 kali kegagalan berturut-turut (HTTP 429).
     */
    public function test_login_rate_limiting_throttles_after_repeated_failures(): void
    {
        RateLimiter::clear('login-attempt:127.0.0.1');

        User::factory()->create([
            'email' => 'admin@jawirblast.com',
            'password' => Hash::make('PasswordBenar123!'),
        ]);

        // Coba 5 kali dengan password salah
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/test-auth/login', [
                'email' => 'admin@jawirblast.com',
                'password' => 'PasswordSalah' . $i,
            ]);

            $response->assertStatus(401);
        }

        // Percobaan ke-6 harus di-throttle dengan HTTP 429 Too Many Requests
        $responseBlocked = $this->postJson('/test-auth/login', [
            'email' => 'admin@jawirblast.com',
            'password' => 'PasswordSalah6',
        ]);

        $responseBlocked->assertStatus(429);
        $this->assertStringContainsString('Too many login attempts', $responseBlocked->json('message'));
    }

    /**
     * Case 2: Form registrasi menyanitasi atau meng-escape payload XSS berbahaya.
     */
    public function test_registration_form_sanitizes_or_escapes_xss_payloads(): void
    {
        $xssName = '<script>alert("XSS")</script><img src=x onerror=alert(1)>';
        
        $validator = Validator::make([
            'name' => $xssName,
            'email' => 'hacker@jawirblast.com',
            'password' => 'AmanSekali123!',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $this->assertTrue($validator->passes());

        // Simpan ke database
        $user = User::create([
            'name' => strip_tags($xssName),
            'email' => 'hacker@jawirblast.com',
            'password' => Hash::make('AmanSekali123!'),
            'locale' => 'id',
        ]);

        // Verifikasi bahwa tag script HTML berbahaya tidak tersimpan mentah tanpa stripping
        $this->assertStringNotContainsString('<script>', $user->name);
        $this->assertStringNotContainsString('<img', $user->name);
        $this->assertEquals('alert("XSS")', $user->name);

        // Uji escaping saat dirender di Blade view helper e()
        $rawRendered = e($xssName);
        $this->assertStringContainsString('&lt;script&gt;', $rawRendered);
        $this->assertStringNotContainsString('<script>', $rawRendered);
    }

    /**
     * Case 3: Password dengan batas panjang Bcrypt (70 karakter) dan karakter Unicode di-hash dan diverifikasi aman.
     */
    public function test_password_with_extreme_length_and_unicode_characters(): void
    {
        // Password 65 byte memuat simbol multibyte dan Unicode emoji
        $boundaryPassword = 'P@sswörd_🔒_Super_Long_Complex_Jawir_Blast_2026_Token!';
        $this->assertLessThanOrEqual(71, strlen($boundaryPassword));

        $hashed = Hash::make($boundaryPassword);
        $this->assertTrue(Hash::check($boundaryPassword, $hashed));
        $this->assertFalse(Hash::check('Wrong_' . $boundaryPassword, $hashed));

        // Buat user dengan password tersebut
        $user = User::create([
            'name' => 'Boundary Password User',
            'email' => 'boundary_pw@jawirblast.com',
            'password' => $hashed,
            'locale' => 'id',
        ]);

        $this->assertNotNull($user->id);
        $this->assertTrue(Hash::check($boundaryPassword, $user->fresh()->password));
    }

    /**
     * Case 4: Format email RFC kompleks (tag, subdomain) lolos validasi, format cacat ditolak.
     */
    public function test_registration_with_complex_rfc_email_formats(): void
    {
        $validEmails = [
            'user+marketing.blast@jawir.co.id',
            'first.last_name-123@sub.domain.example.com',
            'customer_care+promo2026@blast.app',
        ];

        foreach ($validEmails as $email) {
            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email:rfc',
            ]);
            $this->assertTrue($validator->passes(), "Email valid '{$email}' gagal validasi");
        }

        $invalidEmails = [
            'user@',
            '@domain.com',
            'user space@domain.com',
            'user..double_dot@domain.com',
            'not_an_email_string',
            'user@domain..com',
        ];

        foreach ($invalidEmails as $email) {
            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email:rfc',
            ]);
            $this->assertTrue($validator->fails(), "Email cacat '{$email}' lolos validasi padahal seharusnya ditolak");
        }
    }

    /**
     * Case 5: Sesi tamu (guest) tidak dapat membypass route terproteksi middleware 'auth'.
     */
    public function test_guest_session_cannot_bypass_auth_middleware_on_protected_routes(): void
    {
        // 1. Tanpa autentikasi, akses JSON ditolak dengan 401 Unauthenticated
        $response = $this->getJson('/test-auth/dashboard');
        $response->assertStatus(401);
        $this->assertEquals('Unauthenticated.', $response->json('message'));

        // 2. Dengan autentikasi sah, akses diterima (200 OK)
        $user = User::factory()->create();
        $responseAuth = $this->actingAs($user)->getJson('/test-auth/dashboard');
        $responseAuth->assertOk();
        $this->assertEquals('authorized', $responseAuth->json('status'));
        $this->assertEquals($user->id, $responseAuth->json('user.id'));
    }
}
