<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Bahasa yang didukung oleh sistem JAWIRBLAST.
     */
    protected array $supportedLocales = ['id', 'en', 'zh'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        // Terapkan locale ke Laravel Application dan Carbon Date Formatter
        App::setLocale($locale);
        Carbon::setLocale($locale);

        // Jalankan request ke pipeline berikutnya
        $response = $next($request);

        $currentLocale = App::getLocale();

        // Jangan timpa jika response sudah memiliki cookie locale
        $hasLocaleCookie = false;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'locale') {
                $hasLocaleCookie = true;
                break;
            }
        }

        if (!$hasLocaleCookie && $request->cookie('locale') !== $currentLocale) {
            if (method_exists($response, 'withCookie')) {
                $response->withCookie(cookie()->forever('locale', $currentLocale));
            } else {
                $response->headers->setCookie(cookie()->forever('locale', $currentLocale));
            }
        }

        return $response;
    }

    /**
     * Selesaikan locale berdasarkan hierarki:
     * 1. Session -> 2. Cookie -> 3. User Database -> 4. Accept-Language Header -> 5. Default Fallback
     */
    protected function resolveLocale(Request $request): string
    {
        $default = Config::get('app.locale', 'id');

        // 1. Cek dari Session
        if (Session::has('locale')) {
            $sessionLocale = Session::get('locale');
            if (in_array($sessionLocale, $this->supportedLocales, true)) {
                return $sessionLocale;
            }
        }

        // 2. Cek dari Cookie
        $cookieLocale = $request->cookie('locale');
        if ($cookieLocale && in_array($cookieLocale, $this->supportedLocales, true)) {
            Session::put('locale', $cookieLocale);
            return $cookieLocale;
        }

        // 3. Cek dari Pengguna Terautentikasi (Database users.locale)
        if (Auth::check() && !empty(Auth::user()->locale)) {
            $userLocale = Auth::user()->locale;
            if (in_array($userLocale, $this->supportedLocales, true)) {
                Session::put('locale', $userLocale);
                return $userLocale;
            }
        }

        // 4. Deteksi Browser Header Accept-Language
        $preferred = $request->getPreferredLanguage($this->supportedLocales);
        if ($preferred && in_array($preferred, $this->supportedLocales, true)) {
            Session::put('locale', $preferred);
            return $preferred;
        }

        // 5. Fallback Default ('id')
        Session::put('locale', $default);
        return $default;
    }
}
