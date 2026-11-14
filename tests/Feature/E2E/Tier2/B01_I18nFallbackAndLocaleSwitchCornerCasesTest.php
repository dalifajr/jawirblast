<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B01 - i18n Fallback & Locale Switching Corner Cases
 *
 * Menguji skenario batas dan anomali pada sistem lokalisasi multi-bahasa (id, en, zh),
 * termasuk fallback kode bahasa invalid, teks multibyte Hanzi & emoji, switch beruntun,
 * key translasi hilang, dan preseden header Accept-Language.
 */
class B01_I18nFallbackAndLocaleSwitchCornerCasesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Case 1: Akses bahasa yang tidak didukung (/lang/xx) otomatis fallback ke bahasa default ('id').
     */
    public function test_unsupported_locale_falls_back_to_default_locale(): void
    {
        $unsupportedLocales = ['xx', 'invalid_locale', '123', 'fr-FR', 'es'];

        foreach ($unsupportedLocales as $locale) {
            $response = $this->get(route('lang.switch', ['locale' => $locale]));

            $response->assertRedirect();
            $response->assertSessionHas('locale', 'id');
            $response->assertCookie('locale', 'id');
        }
    }

    /**
     * Case 2: Teks multibyte bahasa Mandarin (Simplified Chinese) dan emoji tetap utuh tanpa korupsi.
     */
    public function test_chinese_simplified_characters_and_emojis_remain_intact(): void
    {
        // Switch ke bahasa Mandarin
        $response = $this->get(route('lang.switch', ['locale' => 'zh']));
        $response->assertSessionHas('locale', 'zh');

        App::setLocale('zh');

        // Uji integritas string multibyte Hanzi dengan karakter khusus dan emoji
        $multibyteTestString = '欢迎使用 JAWIRBLAST 营销广播系统 🚀🔥 全速群发中...';
        
        $this->assertEquals('zh', App::getLocale());
        $this->assertEquals(34, mb_strlen($multibyteTestString, 'UTF-8'));
        $this->assertStringContainsString('营销广播系统', $multibyteTestString);
        $this->assertStringContainsString('🚀🔥', $multibyteTestString);

        // Verifikasi roundtrip session storage tidak merusak encoding UTF-8
        session(['zh_test_sample' => $multibyteTestString]);
        $this->assertEquals($multibyteTestString, session('zh_test_sample'));
    }

    /**
     * Case 3: Switch bahasa berulang kali dalam satu sesi tidak merusak session flash dan cookie.
     */
    public function test_rapid_successive_locale_switches_do_not_corrupt_session(): void
    {
        $sequence = ['en', 'zh', 'id', 'en', 'zh', 'id'];

        foreach ($sequence as $expectedLocale) {
            $response = $this->get(route('lang.switch', ['locale' => $expectedLocale]));
            $response->assertRedirect();
            $response->assertSessionHas('locale', $expectedLocale);
            $response->assertCookie('locale', $expectedLocale);
        }

        // Pastikan session state terakhir tetap konsisten
        $this->assertEquals('id', session('locale'));
    }

    /**
     * Case 4: Key translasi yang tidak ada (missing translation key) mengembalikan nama key string tanpa crash.
     */
    public function test_missing_translation_key_returns_key_string_without_exception(): void
    {
        $locales = ['id', 'en', 'zh'];

        foreach ($locales as $locale) {
            App::setLocale($locale);

            $missingKey = 'messages.completely_non_existent_key_' . uniqid();
            $translated = __($missingKey);

            // Laravel translation contract: jika key tidak ditemukan, kembalikan key itu sendiri
            $this->assertEquals($missingKey, $translated);
            $this->assertIsString($translated);
        }
    }

    /**
     * Case 5: Deteksi header HTTP Accept-Language dan aturan presedennya terhadap session.
     */
    public function test_http_accept_language_header_detection_and_precedence(): void
    {
        // 1. First visit tanpa session dengan header bahasa Mandarin
        $response = $this->withHeaders([
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ])->get(route('login'));

        $response->assertOk();
        $this->assertEquals('zh', App::getLocale());

        // 2. Bahasa yang tidak didukung (e.g. Jerman) fallback ke default ('id')
        Session::flush();
        $responseFallback = $this->withHeaders([
            'Accept-Language' => 'de-DE,de;q=0.9',
        ])->get(route('login'));

        $responseFallback->assertOk();
        $this->assertEquals('id', App::getLocale());

        // 3. Preferensi session meng-override header Accept-Language
        $this->withSession(['locale' => 'en'])
            ->withHeaders(['Accept-Language' => 'zh-CN,zh;q=0.9'])
            ->get(route('login'));

        $this->assertEquals('en', App::getLocale());
    }
}
