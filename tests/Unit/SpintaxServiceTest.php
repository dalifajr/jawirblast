<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Services\SpintaxService;
use Tests\TestCase;

class SpintaxServiceTest extends TestCase
{
    protected SpintaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpintaxService();
    }

    /**
     * 1. Memverifikasi template Spintax 1-level selalu menghasilkan salah satu opsi yang valid.
     */
    public function test_01_resolves_standard_single_level_spintax(): void
    {
        $template = '{Halo|Hai|Selamat Pagi} Kawan';
        $validOptions = ['Halo Kawan', 'Hai Kawan', 'Selamat Pagi Kawan'];

        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->parse($template);
            $this->assertContains($result, $validOptions);
            $this->assertStringNotContainsString('{', $result);
            $this->assertStringNotContainsString('}', $result);
            $this->assertStringNotContainsString('|', $result);
        }
    }

    /**
     * 2. Memverifikasi Spintax bersarang (nested) terselesaikan bersih hingga tingkat terdalam.
     */
    public function test_02_resolves_nested_spintax_to_deepest_level(): void
    {
        $template = '{Halo|Hai {Bro|Kak}} {name}!';
        $variables = ['name' => 'Dzulfikri'];

        $validResults = [
            'Halo Dzulfikri!',
            'Hai Bro Dzulfikri!',
            'Hai Kak Dzulfikri!',
        ];

        for ($i = 0; $i < 30; $i++) {
            $result = $this->service->parse($template, $variables);
            $this->assertContains($result, $validResults);
            $this->assertStringNotContainsString('{', $result);
            $this->assertStringNotContainsString('}', $result);
        }
    }

    /**
     * 3. Memverifikasi resolusi variabel dinamis {name}, {nama}, {phone}, {nomor}.
     */
    public function test_03_replaces_dynamic_tags_name_and_phone(): void
    {
        $template = 'Pesan untuk {name} (nama alias: {nama}), nomor telepon {phone} / {nomor}';
        $variables = [
            'name' => 'Budi Santoso',
            'phone' => '6281234567890',
        ];

        $result = $this->service->parse($template, $variables);

        $this->assertStringNotContainsString('{name}', $result);
        $this->assertStringNotContainsString('{nama}', $result);
        $this->assertStringNotContainsString('{phone}', $result);
        $this->assertStringNotContainsString('{nomor}', $result);
        $this->assertStringContainsString('Budi Santoso', $result);
        $this->assertStringContainsString('6281234567890', $result);
    }

    /**
     * 4. Memverifikasi substitusi custom_fields dari array Contact.
     */
    public function test_04_replaces_custom_fields_from_contact_json(): void
    {
        $contact = new Contact([
            'name' => 'Siti Aminah',
            'phone_number' => '6289876543210',
            'custom_fields' => [
                'kota' => 'Surabaya',
                'poin' => 2500,
            ],
        ]);

        $template = 'Halo {name}, Anda memiliki {poin} poin reward di kota {kota}!';
        $result = $this->service->parse($template, $contact);

        $this->assertStringContainsString('Siti Aminah', $result);
        $this->assertStringContainsString('2500 poin', $result);
        $this->assertStringContainsString('kota Surabaya', $result);
        $this->assertStringNotContainsString('{poin}', $result);
        $this->assertStringNotContainsString('{kota}', $result);
    }

    /**
     * 5. Penanganan fallback aman saat variabel bernilai null atau string kosong.
     */
    public function test_05_safely_handles_null_or_empty_variables(): void
    {
        $template = 'Halo {name}, nomor Anda adalah {phone}.';
        $variables = [
            'name' => null,
            'phone' => '',
        ];

        $result = $this->service->parse($template, $variables);

        $this->assertEquals('Halo , nomor Anda adalah .', $result);
        $this->assertStringNotContainsString('{name}', $result);
        $this->assertStringNotContainsString('{phone}', $result);
    }

    /**
     * 6. Kurung kurawal tidak seimbang berhenti tanpa loop tak terhingga (< 0.05 detik).
     */
    public function test_06_unbalanced_curly_braces_terminates_without_infinite_loop(): void
    {
        $brokenTemplates = [
            '{Halo|Hai Kawan',
            'Selamat Siang } Pelanggan',
            '{{Halo|Hai} {name}',
            '{Promo {Hebat|Luar Biasa}',
        ];

        foreach ($brokenTemplates as $tpl) {
            $start = microtime(true);
            $result = $this->service->parse($tpl, ['name' => 'Umar']);
            $duration = microtime(true) - $start;

            $this->assertLessThan(0.05, $duration, "Spintax parsing hanged on: {$tpl}");
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        }
    }

    /**
     * 7. Token kurung kurawal tunggal tanpa pipa diperlakukan sebagai literal tanpa kurung.
     */
    public function test_07_treats_single_token_without_pipe_as_literal(): void
    {
        $template = 'Pesan {Pemberitahuan} resmi untuk pelanggan.';
        $result = $this->service->parse($template);

        $this->assertEquals('Pesan Pemberitahuan resmi untuk pelanggan.', $result);
        $this->assertStringNotContainsString('{', $result);
        $this->assertStringNotContainsString('}', $result);
    }

    /**
     * 8. Teks polos tanpa spintax tetap 100% utuh tanpa perubahan.
     */
    public function test_08_plain_text_without_spintax_remains_unaltered(): void
    {
        $plain = "Halo Pelanggan Yth,\n\nTerima kasih atas pesanan Anda! 🎉\nStatus: DIKIRIM (Resi: JNE12345).";
        $result = $this->service->parse($plain);

        $this->assertEquals($plain, $result);
    }

    /**
     * 9. Pengacakan spintax menghasilkan entropi yang memadai pada eksekusi berulang.
     */
    public function test_09_spintax_randomness_produces_adequate_entropy(): void
    {
        $template = '{Halo|Hai|Salam|Pagi|Siang|Sore|Malam} {Kawan|Sahabat|Teman|Bro|Kak|Sobat}!';
        $results = [];

        for ($i = 0; $i < 50; $i++) {
            $results[$this->service->parse($template)] = true;
        }

        // 7 * 6 = 42 variasi, dalam 50 eksekusi harus ada setidaknya 8 variasi unik
        $this->assertGreaterThanOrEqual(8, count($results));
    }

    /**
     * 10. Kalkulator variasi kombinatorik menghitung jumlah kemungkinan dengan benar.
     */
    public function test_10_calculates_correct_combinatorial_variations_count(): void
    {
        // 1. Teks polos
        $this->assertEquals(1, $this->service->countVariations('Teks polos tanpa spintax'));

        // 2. 1-level 3 opsi
        $this->assertEquals(3, $this->service->countVariations('{Halo|Hai|Selamat Pagi} Kawan'));

        // 3. Perkalian sekuensial: 2 * 3 = 6
        $this->assertEquals(6, $this->service->countVariations('{Halo|Hai} {Kawan|Sahabat|Sobat}'));

        // 4. Nested spintax: {Halo|Hai {Bro|Kak}} => 1 + (1 * 2) = 3
        $this->assertEquals(3, $this->service->countVariations('{Halo|Hai {Bro|Kak}}'));
    }

    /**
     * 11. Generator sampel pratinjau menghasilkan array sampel pesan.
     */
    public function test_11_generates_preview_samples_list(): void
    {
        $template = '{Halo|Hai} {name}, ini penawaran {hebat|menarik}!';
        $samples = $this->service->previewSamples($template, ['name' => 'Fulan'], 5);

        $this->assertCount(5, $samples);
        foreach ($samples as $sample) {
            $this->assertStringContainsString('Fulan', $sample);
            $this->assertStringNotContainsString('{', $sample);
            $this->assertStringNotContainsString('}', $sample);
        }
    }

    /**
     * 12. Memverifikasi variabel berisi format mata uang ($100, $50, $25) dan backslash (\1)
     * tidak rusak atau tertelan oleh evaluasi regex backreference.
     */
    public function test_12_preserves_currency_symbols_and_regex_backreferences_in_variables(): void
    {
        $template = 'Halo {name}, Anda mendapatkan bonus {bonus}, voucher {voucher}, dan diskon {diskon} dengan kode {code}!';
        $variables = [
            'name' => 'Budi',
            'bonus' => '$100',
            'voucher' => '$50',
            'diskon' => '$25',
            'code' => '\1',
        ];

        $result = $this->service->parse($template, $variables);

        $this->assertEquals(
            'Halo Budi, Anda mendapatkan bonus $100, voucher $50, dan diskon $25 dengan kode \1!',
            $result
        );
        $this->assertStringContainsString('$100', $result);
        $this->assertStringContainsString('$50', $result);
        $this->assertStringContainsString('$25', $result);
        $this->assertStringContainsString('\1', $result);
    }

    /**
     * 13. Memverifikasi penggantian custom_fields Contact dengan nilai nominal mata uang dan format angka dolar.
     */
    public function test_13_preserves_currency_values_in_contact_custom_fields(): void
    {
        $contact = new Contact([
            'name' => 'Dzulfikri',
            'phone_number' => '6281122334455',
            'custom_fields' => [
                'saldo' => '$1,000.00',
                'potongan' => '$50',
                'cashback' => '$10',
                'ref_code' => '\2-SPECIAL',
            ],
        ]);

        $template = '{Hai|Halo} {name}, saldo akun Anda adalah {saldo}. Gunakan potongan {potongan} dan dapatkan cashback {cashback} (Ref: {ref_code}).';
        $result = $this->service->parse($template, $contact);

        $this->assertStringContainsString('Dzulfikri', $result);
        $this->assertStringContainsString('$1,000.00', $result);
        $this->assertStringContainsString('$50', $result);
        $this->assertStringContainsString('$10', $result);
        $this->assertStringContainsString('\2-SPECIAL', $result);
        $this->assertStringNotContainsString('{saldo}', $result);
        $this->assertStringNotContainsString('{potongan}', $result);
        $this->assertStringNotContainsString('{cashback}', $result);
        $this->assertStringNotContainsString('{ref_code}', $result);
    }
}
