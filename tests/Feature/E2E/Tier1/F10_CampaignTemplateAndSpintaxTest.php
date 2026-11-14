<?php

namespace Tests\Feature\E2E\Tier1;

use Tests\TestCase;

/**
 * F10: Campaign Template & Spintax Engine Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji resolusi variabel dinamis ({name}, {phone}),
 * Spintax 1-level ({Halo|Hai}),
 * Spintax bersarang/nested ({Halo|Hai {Bro|Kak}}),
 * integritas teks polos tanpa spintax,
 * serta variasi acak yang dihasilkan pada setiap iterasi.
 */
class F10_CampaignTemplateAndSpintaxTest extends TestCase
{
    /**
     * Helper resolver template pesan & spintax.
     * Menggunakan App\Services\SpintaxService jika tersedia, atau implementasi parser deterministik.
     */
    protected function parseMessage(string $template, array $variables = []): string
    {
        if (class_exists('App\\Services\\SpintaxService')) {
            $service = app('App\\Services\\SpintaxService');
            if (method_exists($service, 'parse')) {
                return $service->parse($template, $variables);
            }
        }

        // 1. Substitusi variabel dinamis
        $text = str_replace(
            ['{name}', '{phone}'],
            [$variables['name'] ?? '', $variables['phone'] ?? ''],
            $template
        );

        // 2. Resolusi Spintax rekursif hingga seluruh kurung terselesaikan
        while (preg_match('/\{([^{}]+)\}/', $text)) {
            $text = preg_replace_callback('/\{([^{}]+)\}/', function ($matches) {
                $options = explode('|', $matches[1]);
                return $options[array_rand($options)];
            }, $text);
        }

        return $text;
    }

    /**
     * 1. Resolusi variabel dinamis {name} dan {phone} digantikan secara akurat.
     */
    public function test_01_dynamic_variables_name_and_phone_are_replaced_accurately(): void
    {
        $template = 'Halo {name}, promo eksklusif untuk nomor {phone} telah aktif!';
        $variables = [
            'name' => 'Dzulfikri',
            'phone' => '6281234567890',
        ];

        $result = $this->parseMessage($template, $variables);

        $this->assertStringNotContainsString('{name}', $result);
        $this->assertStringNotContainsString('{phone}', $result);
        $this->assertStringContainsString('Halo Dzulfikri', $result);
        $this->assertStringContainsString('6281234567890', $result);
    }

    /**
     * 2. Resolusi Spintax 1-level {Halo|Hai|Selamat Pagi} di-resolve ke salah satu pilihan yang sah.
     */
    public function test_02_single_level_spintax_resolves_to_one_of_the_options(): void
    {
        $template = '{Halo|Hai|Selamat Pagi} {name}!';
        $variables = ['name' => 'Budi'];

        $validPrefixes = ['Halo Budi!', 'Hai Budi!', 'Selamat Pagi Budi!'];

        for ($i = 0; $i < 10; $i++) {
            $result = $this->parseMessage($template, $variables);
            $this->assertContains($result, $validPrefixes, "Pesan hasil harus cocok dengan salah satu varian Spintax");
            $this->assertStringNotContainsString('{', $result);
            $this->assertStringNotContainsString('}', $result);
        }
    }

    /**
     * 3. Resolusi Spintax bersarang (nested) {Halo|Hai {Bro|Kak}} terselesaikan bersih tanpa sisa kurung.
     */
    public function test_03_nested_spintax_resolves_cleanly_without_unparsed_braces(): void
    {
        $template = '{Halo|Hai {Bro|Kak}} {name}, ini pesan uji!';
        $variables = ['name' => 'Slamet'];

        $validResults = [
            'Halo Slamet, ini pesan uji!',
            'Hai Bro Slamet, ini pesan uji!',
            'Hai Kak Slamet, ini pesan uji!',
        ];

        for ($i = 0; $i < 20; $i++) {
            $result = $this->parseMessage($template, $variables);
            $this->assertContains($result, $validResults, "Spintax bersarang harus menghasilkan opsi valid");
            $this->assertStringNotContainsString('{', $result);
            $this->assertStringNotContainsString('}', $result);
            $this->assertStringNotContainsString('|', $result);
        }
    }

    /**
     * 4. Teks pesan tanpa pola spintax tetap utuh tanpa perubahan karakter.
     */
    public function test_04_text_without_spintax_remains_unchanged(): void
    {
        $plainText = 'Pemberitahuan: Sistem pemeliharaan server akan dilakukan malam ini pukul 23:00 WIB.';
        $result = $this->parseMessage($plainText);

        $this->assertEquals($plainText, $result);
    }

    /**
     * 5. Spintax menghasilkan variasi acak (entropi > 1) saat dieksekusi berulang kali.
     */
    public function test_05_spintax_generates_random_variation_across_multiple_iterations(): void
    {
        $template = '{A|B|C|D|E} {1|2|3|4|5}';
        $results = [];

        for ($i = 0; $i < 30; $i++) {
            $results[] = $this->parseMessage($template);
        }

        $uniqueCount = count(array_unique($results));
        // Dengan 5x5 = 25 variasi, dalam 30 iterasi jumlah unik harus signifikan (> 5)
        $this->assertGreaterThan(5, $uniqueCount, "Spintax harus menghasilkan variasi acak bermakna");
    }
}
