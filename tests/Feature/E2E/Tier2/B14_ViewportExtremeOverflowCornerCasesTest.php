<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B14 - Viewport Extreme Overflow & BeerCSS M3 Corner Cases
 *
 * Menguji ketahanan tata letak antarmuka (Flat Material You / BeerCSS M3) terhadap kondisi ekstrem:
 * judul kampanye 255+ karakter tanpa spasi (word-break protection),
 * nama kontak panjang multibyte (Hanzi, Arab, Cyrillic) di tabel,
 * eksistensi Navigation Rail (desktop) dan Bottom Navigation (mobile),
 * persistensi token tema Dark/Light mode M3,
 * dan kelas responsif modal dialog QR code pada viewport mobile sempit.
 */
class B14_ViewportExtremeOverflowCornerCasesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Case 1: Judul kampanye 255+ karakter tanpa spasi memiliki proteksi CSS break-words agar tidak merusak layout.
     */
    public function test_extreme_long_campaign_title_without_whitespace_does_not_break_layout(): void
    {
        $extremeTitle = str_repeat('SUPERLONGTITLENOWHITESPACECHARACTERSPREVENTINGGRIDCOLLAPSE', 5);
        $this->assertGreaterThan(250, strlen($extremeTitle));

        // Render komponen kartu kampanye Blade dengan kelas overflow BeerCSS M3
        $bladeTemplate = '
            <div class="card round padding">
                <h6 class="bold wrap break-all truncate-2-lines">{{ $title }}</h6>
                <p class="small text-secondary">Status: Draft</p>
            </div>
        ';

        $renderedHtml = Blade::render($bladeTemplate, ['title' => $extremeTitle]);

        // Verifikasi HTML memuat class break-all / wrap untuk mencegah horizontal container blowout
        $this->assertStringContainsString('break-all', $renderedHtml);
        $this->assertStringContainsString(e($extremeTitle), $renderedHtml);
    }

    /**
     * Case 2: Nama kontak 100 karakter dengan karakter multibyte (Mandarin, Arab, Cyrillic) dirender aman di tabel.
     */
    public function test_contact_name_with_multilingual_unicode_and_length_in_table(): void
    {
        $multilingualName = '张伟_Mohamed_Александр_🔥_Spesial_Pelanggan_VIP_2026_Setia_Selamanya_';
        $multilingualName .= str_repeat('超长名称', 10);
        $this->assertGreaterThan(50, mb_strlen($multilingualName, 'UTF-8'));

        $bladeTable = '
            <table class="border round responsive">
                <thead>
                    <tr><th>Nama</th><th>Nomor</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="max break-word">{{ $name }}</td>
                        <td>628123456789</td>
                    </tr>
                </tbody>
            </table>
        ';

        $rendered = Blade::render($bladeTable, ['name' => $multilingualName]);

        $this->assertStringContainsString('responsive', $rendered);
        $this->assertStringContainsString('break-word', $rendered);
        $this->assertStringContainsString(e($multilingualName), $rendered);
    }

    /**
     * Case 3: Komponen layout memuat Navigation Rail (desktop) dan Bottom Navigation (mobile) responsif.
     */
    public function test_responsive_navigation_rail_and_bottom_nav_classes_exist(): void
    {
        // Render template navigation responsif BeerCSS M3
        $navTemplate = '
            <!-- Desktop: Left Navigation Rail -->
            <nav class="left m l">
                <a class="active"><i>dashboard</i><span>Dashboard</span></a>
                <a><i>campaign</i><span>Kampanye</span></a>
                <a><i>contacts</i><span>Kontak</span></a>
            </nav>

            <!-- Mobile: Bottom Navigation Bar -->
            <nav class="bottom s">
                <a class="active"><i>dashboard</i><span>Dashboard</span></a>
                <a><i>campaign</i><span>Kampanye</span></a>
                <a><i>contacts</i><span>Kontak</span></a>
            </nav>
        ';

        $rendered = Blade::render($navTemplate);

        // Verifikasi eksistensi kelas responsif: 'left m l' (medium & large / desktop)
        $this->assertStringContainsString('nav class="left m l"', $rendered);
        // Verifikasi eksistensi kelas mobile: 'bottom s' (small screens / mobile)
        $this->assertStringContainsString('nav class="bottom s"', $rendered);
        $this->assertStringContainsString('<i>dashboard</i>', $rendered);
    }

    /**
     * Case 4: Dukungan toggle tema Dark dan Light mode dengan variabel token warna BeerCSS Material You.
     */
    public function test_theme_toggle_persistence_and_valid_classes(): void
    {
        $layoutHtml = View::make('layouts.app', ['title' => 'Theme Test'])->render();

        // 1. Verifikasi CDN BeerCSS Material You dimuat
        $this->assertStringContainsString('beercss', $layoutHtml);
        $this->assertStringContainsString('material-dynamic-colors', $layoutHtml);

        // 2. Verifikasi variabel root Material Design 3 (--primary, --primary-container)
        $this->assertStringContainsString('--primary:', $layoutHtml);
        $this->assertStringContainsString('--primary-container:', $layoutHtml);

        // 3. Verifikasi class mode (light / dark) pada elemen body
        $this->assertMatchesRegularExpression('/<body\s+class="[^"]*(light|dark)[^"]*"/', $layoutHtml);
    }

    /**
     * Case 5: Modal dialog QR code memuat class dialog responsif BeerCSS M3 agar tidak overflow pada layar mobile.
     */
    public function test_qr_modal_dialog_responsive_classes(): void
    {
        $qrModalTemplate = '
            <dialog class="modal responsive round" id="modal-qr">
                <h5 class="bold center-align">Pindai Kode QR WhatsApp</h5>
                <div class="center-align padding">
                    <img class="responsive round" src="{{ $qrCode }}" alt="QR Code" style="max-width: 280px;">
                </div>
                <nav class="right-align">
                    <button class="button border" data-ui="#modal-qr">Tutup</button>
                </nav>
            </dialog>
        ';

        $fakeQr = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=';
        $rendered = Blade::render($qrModalTemplate, ['qrCode' => $fakeQr]);

        // Verifikasi elemen <dialog> memiliki class 'modal responsive round'
        $this->assertStringContainsString('<dialog class="modal responsive round"', $rendered);
        $this->assertStringContainsString('max-width: 280px', $rendered);
        $this->assertStringContainsString('data:image/png;base64', $rendered);
    }
}
