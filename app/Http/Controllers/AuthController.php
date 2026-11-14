<?php

namespace App\Http\Controllers;

use App\Models\User;
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
     * Memproses permintaan login pengguna dengan email dan password.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
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
