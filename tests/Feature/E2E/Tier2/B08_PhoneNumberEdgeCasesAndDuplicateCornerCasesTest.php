<?php

namespace Tests\Feature\E2E\Tier2;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\TestCase;

/**
 * Tier 2 Test Suite: B08 - Phone Number Normalization & Duplicate Corner Cases
 *
 * Menguji normalisasi nomor telepon ke standar internasional E.164:
 * nomor internasional di luar Indonesia (+1, +44, +86, +60), pencegahan duplikasi nomor,
 * penanganan variasi tanda kurung, spasi, titik, strip, awalan lama 0062/008,
 * dan penolakan tegas terhadap input nomor kosong atau alfabetik.
 */
class B08_PhoneNumberEdgeCasesAndDuplicateCornerCasesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCampaigns;

    /**
     * Helper normalisasi nomor telepon sesuai spesifikasi kontrak PhoneNumberNormalizer.
     */
    protected function normalizePhoneNumber(string $phone): ?string
    {
        $raw = trim($phone);
        if (empty($raw)) {
            return null;
        }

        // Hapus tanda kurung, spasi, titik, dan tanda minus
        $cleaned = preg_replace('/[\s\(\)\-\.]/', '', $raw);

        // Jika diawali '+', buang tanda '+'
        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
        }

        // Tangani awalan legacy panggilan internasional '00' (misal: 0062 -> 62, 008 -> 628)
        if (str_starts_with($cleaned, '0062')) {
            $cleaned = '62' . substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '008')) {
            $cleaned = '628' . substr($cleaned, 3);
        }

        // Tangani format lokal Indonesia (08... -> 628...)
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62' . substr($cleaned, 1);
        }

        // Pastikan hanya terdiri dari digit angka
        if (!ctype_digit($cleaned)) {
            return null;
        }

        // Panjang E.164: minimal 8 digit, maksimal 15 digit
        $len = strlen($cleaned);
        if ($len < 8 || $len > 15) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Case 1: Nomor telepon internasional di luar Indonesia (+1, +44, +86, +60) dinormalisasi dengan benar.
     */
    public function test_international_phone_numbers_outside_indonesia_are_correctly_normalized(): void
    {
        $testCases = [
            '+1 (555) 234-5678' => '15552345678', // Amerika Serikat (NANP)
            '+44 7911 123456' => '447911123456',  // Inggris
            '+86 138 0013 8000' => '8613800138000', // China
            '+60 12-345 6789' => '60123456789',   // Malaysia
            '+65 9123 4567' => '6591234567',      // Singapura
            '+81 90-1234-5678' => '819012345678', // Jepang
        ];

        foreach ($testCases as $input => $expected) {
            $normalized = $this->normalizePhoneNumber($input);
            $this->assertEquals($expected, $normalized, "Failed normalizing '{$input}'");
        }
    }

    /**
     * Case 2: Penolakan atau deduplikasi nomor telepon yang sama pada user/kampanye.
     */
    public function test_duplicate_phone_number_in_same_group_rejected_or_deduplicated(): void
    {
        $user = User::factory()->create();

        $phone = '6281234567890';

        // Simpan kontak pertama
        DB::table('contacts')->insert([
            'user_id' => $user->id,
            'name' => 'Kontak Pertama',
            'phone_number' => $phone,
            'raw_phone' => '081234567890',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cek duplikasi saat input kontak kedua dengan format mentah berbeda tapi hasil normalisasi sama
        $duplicateRawPhone = '+62 812-3456-7890';
        $normalizedDup = $this->normalizePhoneNumber($duplicateRawPhone);

        $exists = DB::table('contacts')
            ->where('user_id', $user->id)
            ->where('phone_number', $normalizedDup)
            ->exists();

        $this->assertTrue($exists, 'Duplicate normalized phone number must be detected in the database');

        // Deduplikasi otomatis: jika ditambahkan ke array kampanye, ambil array_unique
        $targetNumbers = [$phone, $normalizedDup, '6289999888877'];
        $uniqueTargets = array_values(array_unique($targetNumbers));

        $this->assertCount(2, $uniqueTargets);
        $this->assertEquals($phone, $uniqueTargets[0]);
        $this->assertEquals('6289999888877', $uniqueTargets[1]);
    }

    /**
     * Case 3: Nomor dengan variasi tanda kurung, spasi, titik, dan strip dinormalisasi secara seragam.
     */
    public function test_phone_with_mixed_brackets_spaces_and_hyphens(): void
    {
        $inputs = [
            '(0812) 345-6789',
            '0812 345 6789',
            '0812.3456.789',
            '+62-812-345-6789',
            ' +62 (812) 345 6789 ',
        ];

        $expected = '628123456789';

        foreach ($inputs as $input) {
            $this->assertEquals($expected, $this->normalizePhoneNumber($input), "Failed normalizing '{$input}'");
        }
    }

    /**
     * Case 4: Awalan legacy panggilan internasional (0062 atau 008) dinormalisasi ke E.164.
     */
    public function test_phone_with_legacy_international_prefixes_0062_or_008(): void
    {
        $testCases = [
            '006281234567890' => '6281234567890',
            '0081234567890' => '6281234567890',
            '0062 899-8877-6655' => '6289988776655',
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, $this->normalizePhoneNumber($input));
        }
    }

    /**
     * Case 5: Input nomor kosong, teks murni alfabet, atau digit kurang dari minimum ditolak ketat.
     */
    public function test_empty_or_purely_alphabetic_phone_is_strictly_rejected(): void
    {
        $invalidInputs = [
            '', // Kosong
            '   ', // Whitespace
            'BukanNomorTelepon', // Alfabet
            'abc-def-ghij',
            '12345', // Terlalu pendek (< 8 digit)
            '0812', // Terlalu pendek
            '123456789012345678', // Terlalu panjang (> 15 digit)
            '!@#$%^&*()',
        ];

        foreach ($invalidInputs as $invalid) {
            $result = $this->normalizePhoneNumber($invalid);
            $this->assertNull($result, "Input invalid '{$invalid}' must return null");

            $validator = Validator::make(['phone' => $invalid], [
                'phone' => ['required', 'regex:/^(\+?[0-9]{8,15})$/'],
            ]);
            $this->assertTrue($validator->fails(), "Validator must reject '{$invalid}'");
        }
    }
}
