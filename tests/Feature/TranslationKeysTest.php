<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class TranslationKeysTest extends TestCase
{
    /**
     * Domain translasi yang wajib ada di JAWIRBLAST.
     */
    protected array $domains = [
        'auth',
        'dashboard',
        'contacts',
        'campaign',
        'session',
        'messages',
        'common',
        'nav',
    ];

    /**
     * Bahasa yang didukung.
     */
    protected array $locales = ['id', 'en', 'zh'];

    /**
     * Pastikan semua file domain translasi ada untuk setiap bahasa.
     */
    public function test_all_translation_files_exist(): void
    {
        foreach ($this->locales as $locale) {
            foreach ($this->domains as $domain) {
                $filePath = lang_path("{$locale}/{$domain}.php");
                $this->assertFileExists($filePath, "File {$filePath} harus ada.");
            }
        }
    }

    /**
     * Pastikan semua file translasi mengembalikan array non-kosong.
     */
    public function test_all_translation_files_return_non_empty_arrays(): void
    {
        foreach ($this->locales as $locale) {
            foreach ($this->domains as $domain) {
                $filePath = lang_path("{$locale}/{$domain}.php");
                $translations = require $filePath;

                $this->assertIsArray($translations, "Translasi {$locale}/{$domain} harus berupa array.");
                $this->assertNotEmpty($translations, "Translasi {$locale}/{$domain} tidak boleh kosong.");
            }
        }
    }

    /**
     * Pastikan key pada file translasi id, en, dan zh simetris (kunci-kunci utama tersedia di ketiga bahasa).
     */
    public function test_translation_keys_are_symmetric(): void
    {
        foreach ($this->domains as $domain) {
            $idKeys = array_keys(require lang_path("id/{$domain}.php"));
            $enKeys = array_keys(require lang_path("en/{$domain}.php"));
            $zhKeys = array_keys(require lang_path("zh/{$domain}.php"));

            sort($idKeys);
            sort($enKeys);
            sort($zhKeys);

            $this->assertEquals($idKeys, $enKeys, "Kunci translasi EN untuk {$domain} tidak cocok dengan ID.");
            $this->assertEquals($idKeys, $zhKeys, "Kunci translasi ZH untuk {$domain} tidak cocok dengan ID.");
        }
    }

    /**
     * Uji pemanggilan fungsi __() untuk memastikan translasi berhasil dimuat di runtime.
     */
    public function test_runtime_translation_lookup(): void
    {
        App::setLocale('id');
        $this->assertEquals('Dashboard Utama', __('dashboard.title'));
        $this->assertEquals('Terkirim (Centang 1)', __('messages.status_sent'));

        App::setLocale('en');
        $this->assertEquals('Dashboard Overview', __('dashboard.title'));
        $this->assertEquals('Sent (Single Tick)', __('messages.status_sent'));

        App::setLocale('zh');
        $this->assertEquals('控制台总览', __('dashboard.title'));
        $this->assertEquals('已发送 (单勾)', __('messages.status_sent'));
    }
}
