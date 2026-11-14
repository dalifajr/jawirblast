<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Menampilkan formulir login.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Memproses permintaan login pengguna dengan email ATAU username.
     */
    public function login(Request $request): RedirectResponse
    {
        $input = $request->input('login', $request->input('email'));

        if (empty($input)) {
            $request->validate([
                'email' => ['required'],
                'password' => ['required', 'string'],
            ]);
        } else {
            $request->validate([
                'password' => ['required', 'string'],
            ]);
        }

        $loginInput = trim((string) $input);
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $remember = $request->boolean('remember');

        $credentials = [
            $fieldType => $fieldType === 'username' ? strtolower($loginInput) : $loginInput,
            'password' => $request->input('password'),
        ];

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'login' => [__('auth.failed')],
            'email' => [__('auth.failed')],
        ]);
    }

    /**
     * Endpoint live AJAX untuk mengecek ketersediaan username.
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $rawUsername = trim((string) $request->query('username', ''));
        $username = strtolower($rawUsername);

        if ($username === '') {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => __('auth.username_required'),
            ], 422);
        }

        if (mb_strlen($username) < 3) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => __('auth.username_too_short'),
            ], 422);
        }

        if (mb_strlen($username) > 30) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => __('auth.username_too_long'),
            ], 422);
        }

        if (!preg_match('/^[a-z0-9_.]+$/', $username)) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => __('auth.username_invalid'),
            ], 422);
        }

        $reserved = ['admin', 'administrator', 'root', 'superadmin', 'system', 'null', 'api', 'bot', 'support'];
        if (in_array($username, $reserved, true)) {
            return response()->json([
                'valid' => false,
                'available' => false,
                'message' => __('auth.username_reserved'),
            ], 422);
        }

        $exists = User::where('username', $username)->exists();

        if ($exists) {
            return response()->json([
                'valid' => true,
                'available' => false,
                'message' => __('auth.username_taken'),
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'available' => true,
            'message' => __('auth.username_available'),
        ], 200);
    }

    /**
     * Menampilkan formulir pendaftaran akun baru (self-registration).
     */
    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /**
     * Memproses registrasi pengguna baru.
     */
    public function register(Request $request): RedirectResponse
    {
        // Fallback username dari email jika tidak dikirim (backward compatibility)
        if (!$request->filled('username') && $request->filled('email')) {
            $emailParts = explode('@', (string) $request->input('email'));
            $suggestedUsername = preg_replace('/[^a-z0-9_.]/', '', strtolower($emailParts[0]));
            if (strlen($suggestedUsername) < 3) {
                $suggestedUsername .= 'usr' . rand(10, 99);
            }
            $request->merge(['username' => $suggestedUsername]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_.]+$/',
                'unique:users,username',
                'not_in:admin,administrator,root,superadmin,system,null,api,bot,support',
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'locale' => app()->getLocale() ?: 'id',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Registrasi berhasil. Selamat datang di JAWIRBLAST!');
    }

    /**
     * Mengeluarkan pengguna dari sistem dan menginvalidasi sesi.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
