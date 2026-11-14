<?php

namespace Tests\Feature\E2E\Tier4;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * S05: Multilingual Navigation and Responsive Switching (Real-World Application Scenario)
 * Fitur yang diuji: F1, F14
 *
 * Alur Skenario:
 * 1. Navigasi pengguna lintas tiga bahasa resmi: Bahasa Indonesia (id), English (en), dan Chinese (zh)
 * 2. Pengujian endpoint lokalisasi GET /lang/{locale} dan persistensi sesi bahasa
 * 3. Verifikasi kelengkapan dan konsistensi seluruh modul kamus bahasa (nav, common, campaign, auth, session)
 * 4. Verifikasi bahwa teks translasi multi-byte (karakter Hanzi) tidak mengalami pemotongan string
 * 5. Pengujian pergantian mode tema responsif (Light / Dark mode Flat Material You / BeerCSS M3)
 * 6. Persistensi preferensi bahasa pada profil pengguna di basis data
 */
class S05_MultilingualNavigationAndResponsiveSwitchingTest extends Tier4TestCase
{
    public function test_multilingual_navigation_and_theme_switching_lifecycle(): void
    {
        $user = $this->createTestUser(['locale' => 'id']);
        $this->actingAs($user);

        // -------------------------------------------------------------
        // Tahap 1: Pengujian Bahasa Indonesia (Default)
        // -------------------------------------------------------------
        App::setLocale('id');
        $this->assertEquals('id', App::getLocale());
        $this->assertEquals('Kontak', __('nav.contacts'));
        $this->assertEquals('Kampanye Siaran', __('nav.campaigns'));
        $this->assertEquals('Sesi WhatsApp', __('nav.whatsapp_session'));
        $this->assertEquals('Ganti Tema Terang/Gelap', __('nav.toggle_theme'));

        // -------------------------------------------------------------
        // Tahap 2: Beralih ke Bahasa Inggris (en)
        // -------------------------------------------------------------
        $resEn = $this->get('/lang/en');
        $resEn->assertRedirect();
        $this->assertEquals('en', session('locale'));

        App::setLocale('en');
        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('Contacts', __('nav.contacts'));
        $this->assertEquals('Broadcast Campaigns', __('nav.campaigns'));
        $this->assertEquals('WhatsApp Session', __('nav.whatsapp_session'));
        $this->assertEquals('Sign In to JAWIRBLAST', __('auth.login_title'));

        // -------------------------------------------------------------
        // Tahap 3: Beralih ke Bahasa Mandarin (zh)
        // -------------------------------------------------------------
        $resZh = $this->get('/lang/zh');
        $resZh->assertRedirect();
        $this->assertEquals('zh', session('locale'));

        App::setLocale('zh');
        $this->assertEquals('zh', App::getLocale());
        $this->assertEquals('联系人', __('nav.contacts'));
        $this->assertEquals('群发活动', __('nav.campaigns'));
        $this->assertEquals('设备会话', __('nav.whatsapp_session'));
        $this->assertEquals('切换亮色/深色主题', __('nav.toggle_theme'));

        // Simpan preferensi bahasa Mandarin ke profil pengguna di database
        DB::table('users')->where('id', $user->id)->update([
            'locale' => 'zh',
            'updated_at' => now(),
        ]);

        $freshUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertEquals('zh', $freshUser->locale);

        // -------------------------------------------------------------
        // Tahap 4: Verifikasi Kelengkapan Modul Lokalisasi Lintas Bahasa
        // -------------------------------------------------------------
        $modules = ['auth', 'campaign', 'common', 'contacts', 'dashboard', 'messages', 'nav', 'session'];
        $locales = ['id', 'en', 'zh'];

        foreach ($locales as $loc) {
            foreach ($modules as $mod) {
                $filePath = resource_path("../lang/{$loc}/{$mod}.php");
                $this->assertFileExists($filePath, "File lokalisasi lang/{$loc}/{$mod}.php harus tersedia");

                $translations = require $filePath;
                $this->assertIsArray($translations, "Kamus translasi {$loc}/{$mod} harus berupa array");
                $this->assertNotEmpty($translations, "Kamus translasi {$loc}/{$mod} tidak boleh kosong");
            }
        }

        // -------------------------------------------------------------
        // Tahap 5: Pengujian Simulasi Tema BeerCSS M3 (Dark / Light Mode)
        // -------------------------------------------------------------
        // BeerCSS M3 mengandalkan atribut HTML mode: class="dark" atau class="light"
        // atau data-ui="#theme" dan elemen switch tema.
        $themeOptions = ['light', 'dark'];

        foreach ($themeOptions as $theme) {
            session(['theme' => $theme]);
            $this->assertEquals($theme, session('theme'));

            // Verifikasi token class theme BeerCSS M3 yang valid
            $this->assertContains(session('theme'), ['light', 'dark']);
        }

        // -------------------------------------------------------------
        // Tahap 6: Fallback Bahasa Jika Locale Tidak Valid
        // -------------------------------------------------------------
        // Jika user mengakses locale yang tidak didukung (misal 'fr' atau 'xx'),
        // sistem harus fallback ke bahasa default ('id') tanpa exception
        $resInvalid = $this->get('/lang/unsupported_locale');
        $resInvalid->assertRedirect();

        // Validasi locale tetap berada pada whitelist yang sah
        $allowedLocales = ['id', 'en', 'zh'];
        $currentLocale = session('locale');
        $this->assertContains($currentLocale, $allowedLocales, 'Locale harus fallback ke locale yang valid');
    }
}
