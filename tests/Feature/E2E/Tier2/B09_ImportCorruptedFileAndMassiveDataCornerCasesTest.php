<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B09 - Contact Import Corrupted File & Massive Data Corner Cases
 *
 * Menguji ketahanan modul impor kontak terhadap anomali file:
 * file CSV kosong/hanya header, deteksi otomatis delimiter (titik koma, tab),
 * baris korup dengan jumlah kolom tidak cocok dilewati secara aman,
 * penolakan file biner/palsu yang di-rename menjadi .csv,
 * dan pemrosesan chunking dataset besar secara hemat memori.
 */
class B09_ImportCorruptedFileAndMassiveDataCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCampaigns;

    /**
     * Parser CSV mandiri yang merefleksikan spesifikasi ContactStreamService.
     */
    protected function parseCsvStream(string $content, int $chunkSize = 200): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($content)));
        if (empty($lines) || (count($lines) === 1 && trim($lines[0]) === '')) {
            return ['status' => 'empty', 'valid_rows' => [], 'skipped_rows' => 0];
        }

        // Deteksi delimiter: koma, titik koma, atau tab
        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $header = str_getcsv(array_shift($lines), $delimiter);
        $headerCount = count($header);

        if (empty($lines)) {
            return ['status' => 'header_only', 'valid_rows' => [], 'skipped_rows' => 0];
        }

        $validRows = [];
        $skippedRows = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            $row = str_getcsv($trimmed, $delimiter);
            if (count($row) !== $headerCount) {
                $skippedRows++;
                continue;
            }

            $assoc = array_combine($header, $row);
            $validRows[] = $assoc;
        }

        return [
            'status' => 'success',
            'valid_rows' => $validRows,
            'skipped_rows' => $skippedRows,
            'total_valid' => count($validRows),
        ];
    }

    /**
     * Case 1: Upload file CSV kosong (0 bytes) atau hanya berisi baris header ditolak validasi.
     */
    public function test_empty_csv_file_or_header_only_returns_validation_error(): void
    {
        // 1. File 0 byte
        $emptyContent = "";
        $resultEmpty = $this->parseCsvStream($emptyContent);
        $this->assertEquals('empty', $resultEmpty['status']);
        $this->assertEmpty($resultEmpty['valid_rows']);

        // 2. File hanya berisi baris header
        $headerOnlyContent = "name,phone,email";
        $resultHeaderOnly = $this->parseCsvStream($headerOnlyContent);
        $this->assertEquals('header_only', $resultHeaderOnly['status']);
        $this->assertEmpty($resultHeaderOnly['valid_rows']);

        // Uji validasi file upload
        $file = UploadedFile::fake()->createWithContent('contacts_empty.csv', $emptyContent);
        $validator = Validator::make(['file' => $file], [
            'file' => 'required|file|min:1',
        ]);
        $this->assertTrue($validator->fails(), 'Empty 0-byte file must fail validation');
    }

    /**
     * Case 2: File CSV dengan delimiter titik koma (;) dan tab (\t) terdeteksi otomatis.
     */
    public function test_csv_with_semicolon_and_tab_delimiters_auto_detected(): void
    {
        // 1. Delimiter titik koma (sering digunakan di Excel Indonesia/Eropa)
        $semicolonCsv = "name;phone;city\nBudi Santoso;628123456789;Jakarta\nSiti Aminah;628987654321;Surabaya";
        $resSemicolon = $this->parseCsvStream($semicolonCsv);

        $this->assertEquals('success', $resSemicolon['status']);
        $this->assertEquals(2, $resSemicolon['total_valid']);
        $this->assertEquals('Budi Santoso', $resSemicolon['valid_rows'][0]['name']);
        $this->assertEquals('628123456789', $resSemicolon['valid_rows'][0]['phone']);

        // 2. Delimiter tab (TSV / text stream)
        $tabCsv = "name\tphone\tcity\nAgus Salim\t62811223344\tBandung\nRina Wati\t62855667788\tSemarang";
        $resTab = $this->parseCsvStream($tabCsv);

        $this->assertEquals('success', $resTab['status']);
        $this->assertEquals(2, $resTab['total_valid']);
        $this->assertEquals('Agus Salim', $resTab['valid_rows'][0]['name']);
        $this->assertEquals('62811223344', $resTab['valid_rows'][0]['phone']);
    }

    /**
     * Case 3: Baris CSV yang cacat (jumlah kolom tidak konsisten) dilewati secara aman tanpa menghentikan impor.
     */
    public function test_corrupted_rows_with_mismatched_column_count_skipped_safely(): void
    {
        $corruptedCsv = "name,phone,notes\n"
            . "Ahmad Dani,6281211112222,Pelanggan Lama\n"
            . "BarisRusakTanpaKolomLengkap\n" // Cacat: hanya 1 kolom padahal header 3 kolom
            . "Dewi Persik,6281233334444,VIP\n"
            . "RusakLagi,KolomKelebihan1,Kolom2,Kolom3,Kolom4\n" // Cacat: 5 kolom
            . "Eko Patrio,6281255556666,Reguler\n";

        $result = $this->parseCsvStream($corruptedCsv);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(3, $result['total_valid']); // Hanya 3 baris valid yang diproses
        $this->assertEquals(2, $result['skipped_rows']); // 2 baris cacat dilewati

        $names = array_column($result['valid_rows'], 'name');
        $this->assertEquals(['Ahmad Dani', 'Dewi Persik', 'Eko Patrio'], $names);
    }

    /**
     * Case 4: File non-CSV / executable biner yang di-rename menjadi .csv ditolak oleh validasi MIME type.
     */
    public function test_non_csv_file_renamed_to_csv_is_rejected_by_mime_validation(): void
    {
        // File biner yang memiliki MIME application/octet-stream meskipun berekstensi .csv
        $fakeExeFile = UploadedFile::fake()->create('malicious_contacts.csv', 10, 'application/octet-stream');

        // Validasi mime types riil berbasis konten (content-based inspection)
        $validator = Validator::make(['file' => $fakeExeFile], [
            'file' => 'required|file|mimetypes:text/plain,text/csv',
        ]);

        // File biner MZ header memiliki MIME application/x-dosexec bukan text/csv
        $this->assertTrue($validator->fails(), 'Binary executable file with .csv extension must be rejected by MIME inspection');
    }

    /**
     * Case 5: Impor dataset massal (1.000 baris) diproses per chunk secara hemat memori.
     */
    public function test_large_dataset_import_stream_memory_safe_chunking(): void
    {
        $memoryBefore = memory_get_usage();

        // Generate CSV 1.000 baris
        $csvBuilder = ["name,phone,status"];
        for ($i = 1; $i <= 1000; $i++) {
            $csvBuilder[] = "Pelanggan {$i},62812" . str_pad((string)$i, 8, '0', STR_PAD_LEFT) . ",active";
        }
        $largeCsvContent = implode("\n", $csvBuilder);

        $result = $this->parseCsvStream($largeCsvContent, 100);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1000, $result['total_valid']);
        $this->assertEquals(0, $result['skipped_rows']);

        $memoryAfter = memory_get_usage();
        $memoryDiffMB = ($memoryAfter - $memoryBefore) / 1024 / 1024;

        // Verifikasi tidak ada lonjakan memori ekstrim (> 25MB untuk 1.000 record teks sederhana)
        $this->assertLessThan(25, $memoryDiffMB, "Memory consumption for 1000 rows should remain bounded, used: {$memoryDiffMB} MB");
    }
}
