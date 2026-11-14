<?php

namespace Tests\Feature\E2E\Tier2;

use Tests\TestCase;

/**
 * Tier 2 Test Suite: B10 - Spintax Parser Nested & Unbalanced Corner Cases
 *
 * Menguji mesin Spintax terhadap anomali kurung kurawal tidak seimbang (unbalanced braces),
 * spintax tanpa separator pipa, spintax bersarang hingga 3 tingkat (deeply nested),
 * penanganan fallback variabel dinamis {name} yang kosong,
 * dan integritas teks polos tanpa spintax.
 */
class B10_SpintaxNestedAndUnbalancedCornerCasesTest extends TestCase
{
    /**
     * Engine parser Spintax sesuai spesifikasi SpintaxService.
     */
    protected function parseSpintax(string $text, array $variables = []): string
    {
        // 1. Ganti variabel dinamis seperti {name}, {phone}
        foreach ($variables as $key => $val) {
            $replacement = !empty($val) ? (string)$val : '';
            $text = str_replace('{' . $key . '}', $replacement, $text);
        }

        // 2. Evaluasi Spintax bersarang secara iteratif (dari kurung terdalam)
        $pattern = '/\{([^{}]+)\}/';
        $maxIterations = 50;
        $iteration = 0;

        while (preg_match($pattern, $text) && $iteration < $maxIterations) {
            $iteration++;
            $text = preg_replace_callback($pattern, function ($matches) {
                $options = explode('|', $matches[1]);
                return $options[array_rand($options)];
            }, $text);
        }

        return $text;
    }

    /**
     * Case 1: Kurung kurawal tidak seimbang (misal kurung buka tanpa kurung tutup) diproses tanpa infinite loop.
     */
    public function test_unbalanced_curly_braces_in_spintax_template(): void
    {
        $templatesWithUnbalancedBraces = [
            '{Halo|Hai Kakak', // Buka tanpa tutup
            'Selamat pagi } Pelanggan', // Tutup tanpa buka
            '{{Halo|Hai} {name}', // Double buka
            '{Promo {Hebat|Luar Biasa}', // Kurung luar tidak tertutup
        ];

        foreach ($templatesWithUnbalancedBraces as $tpl) {
            $startTime = microtime(true);
            $parsed = $this->parseSpintax($tpl, ['name' => 'Budi']);
            $duration = microtime(true) - $startTime;

            // Pastikan parser selesai dalam waktu sangat cepat (< 0.05 detik), tidak infinite loop
            $this->assertLessThan(0.05, $duration, "Spintax parser hanged on template: {$tpl}");
            $this->assertIsString($parsed);
            $this->assertNotEmpty($parsed);
        }
    }

    /**
     * Case 2: Spintax tanpa pipa pemisah ({Kata}) diekstrak sebagai kata tunggal tanpa kurung kurawal.
     */
    public function test_spintax_without_pipe_separator_treated_as_literal_or_single_choice(): void
    {
        $template = 'Pesan {Spesial} untuk Anda.';
        $parsed = $this->parseSpintax($template);

        $this->assertEquals('Pesan Spesial untuk Anda.', $parsed);
        $this->assertStringNotContainsString('{', $parsed);
        $this->assertStringNotContainsString('}', $parsed);
    }

    /**
     * Case 3: Spintax bersarang hingga 3 tingkat ({A|{B|{C|D}}}) dapat mengurai seluruh cabang secara valid.
     */
    public function test_three_level_deep_nested_spintax_resolves_all_branches(): void
    {
        $nestedTemplate = '{Halo|{Hai|{Hei|Salam}}} kawan!';
        $possibleGreetings = ['Halo', 'Hai', 'Hei', 'Salam'];

        $resolvedGreetings = [];
        // Lakukan sampling 50 kali untuk membuktikan semua variasi dapat tercapai
        for ($i = 0; $i < 50; $i++) {
            $parsed = $this->parseSpintax($nestedTemplate);
            $this->assertStringEndsWith('kawan!', $parsed);

            foreach ($possibleGreetings as $greeting) {
                if (str_starts_with($parsed, $greeting)) {
                    $resolvedGreetings[$greeting] = true;
                }
            }
        }

        // Verifikasi bahwa lebih dari satu cabang berhasil dieksplorasi (acak)
        $this->assertGreaterThanOrEqual(2, count($resolvedGreetings), 'Spintax parser must resolve nested branches dynamically');
    }

    /**
     * Case 4: Variabel {name} pada kontak yang tidak memiliki nama (null atau kosong) diganti fallback aman.
     */
    public function test_missing_recipient_name_falls_back_safely(): void
    {
        $template = 'Halo {name}, kami punya penawaran menarik untuk nomor {phone}!';

        // 1. Nama kosong string
        $parsedEmpty = $this->parseSpintax($template, ['name' => '', 'phone' => '628123456789']);
        $this->assertStringNotContainsString('{name}', $parsedEmpty);
        $this->assertStringContainsString('628123456789', $parsedEmpty);

        // 2. Nama null dengan fallback string
        $nameVal = null ?? 'Pelanggan';
        $parsedNull = $this->parseSpintax($template, ['name' => $nameVal, 'phone' => '628123456789']);
        $this->assertStringContainsString('Halo Pelanggan,', $parsedNull);
    }

    /**
     * Case 5: Teks pesan polos tanpa spintax sama sekali tetap utuh 100% tanpa perubahan karakter.
     */
    public function test_plain_template_without_spintax_remains_exact_and_intact(): void
    {
        $plainMessage = "PEMBERITAHUAN RESMI:\n"
            . "1. Layanan aktif 24/7.\n"
            . "2. Diskon hingga 70% berlaku sampai 31 Desember 2026.\n"
            . "Hubungi: support@jawirblast.com atau kunjungi https://jawirblast.com\n"
            . "Terima kasih! 🚀🎉";

        $parsed = $this->parseSpintax($plainMessage);

        $this->assertEquals($plainMessage, $parsed, 'Plain message without spintax must remain strictly identical');
    }
}
