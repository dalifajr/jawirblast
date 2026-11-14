<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    /**
     * Ganti bahasa aplikasi dan simpan preferensi.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supportedLocales = ['id', 'en', 'zh'];

        if (!in_array($locale, $supportedLocales, true)) {
            $locale = 'id';
        }

        // Simpan ke Session & sinkronkan runtime App & Carbon
        $request->session()->put('locale', $locale);
        \Illuminate\Support\Facades\App::setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        // Jika user sedang login, update ke database pengguna
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $user->locale = $locale;
            $user->save();
        }

        // Buat cookie persisten selama 1 tahun
        $cookie = cookie()->forever('locale', $locale);

        return redirect()->back()->withCookie($cookie);
    }
}
