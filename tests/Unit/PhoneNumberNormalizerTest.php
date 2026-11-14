<?php

namespace Tests\Unit;

use App\Services\PhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    /**
     * Uji normalisasi format nomor lokal Indonesia (08... -> 628...).
     */
    public function test_normalizes_local_indonesian_08_prefix(): void
    {
        $input = '081234567890';
        $normalized = PhoneNumberNormalizer::normalize($input);

        $this->assertEquals('6281234567890', $normalized);
    }

    /**
     * Uji normalisasi awalan 8 tanpa 0 untuk format Indonesia.
     */
    public function test_normalizes_indonesian_number_starting_with_8(): void
    {
        $input = '81234567890';
        $normalized = PhoneNumberNormalizer::normalize($input);

        $this->assertEquals('6281234567890', $normalized);
    }

    /**
     * Uji pembersihan karakter format: tanda plus, spasi, tanda kurung, titik, dan strip.
     */
    public function test_strips_formatting_characters(): void
    {
        $variations = [
            '+62 812-3456-7890' => '6281234567890',
            '(0812) 345-6789' => '628123456789',
            '0812.3456.789' => '628123456789',
            '+62 (812) 345 6789' => '628123456789',
            '  +62-812-3456-7890  ' => '6281234567890',
        ];

        foreach ($variations as $raw => $expected) {
            $this->assertEquals($expected, PhoneNumberNormalizer::normalize($raw), "Failed on: {$raw}");
        }
    }

    /**
     * Uji penanganan awalan legacy panggilan internasional (0062 dan 008).
     */
    public function test_handles_legacy_international_prefixes(): void
    {
        $this->assertEquals('6281234567890', PhoneNumberNormalizer::normalize('006281234567890'));
        $this->assertEquals('6281234567890', PhoneNumberNormalizer::normalize('0081234567890'));
        $this->assertEquals('6289988776655', PhoneNumberNormalizer::normalize('0062 899-8877-6655'));
    }

    /**
     * Uji nomor internasional di luar Indonesia (AS, Inggris, China, Malaysia, Singapura, Jepang).
     */
    public function test_normalizes_international_phone_numbers(): void
    {
        $testCases = [
            '+1 (555) 234-5678' => '15552345678',   // USA
            '+44 7911 123456' => '447911123456',    // UK
            '+86 138 0013 8000' => '8613800138000', // China
            '+60 12-345 6789' => '60123456789',     // Malaysia
            '+65 9123 4567' => '6591234567',        // Singapore
            '+81 90-1234-5678' => '819012345678',   // Japan
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, PhoneNumberNormalizer::normalize($input), "Failed normalizing: {$input}");
        }
    }

    /**
     * Uji penolakan input invalid (kosong, alfabet, karakter simbol murni, angka nol berulang, panjang di luar 8-15).
     */
    public function test_rejects_invalid_phone_numbers(): void
    {
        $invalidInputs = [
            '',
            '   ',
            'BukanNomorTelepon',
            'abc-def-ghij',
            '0812abcdef',
            '###---@@@',
            '000000',
            '12345',               // < 8 digit
            '0812',                // < 8 digit
            '123456789012345678',  // > 15 digit
            '!@#$%^&*()',
        ];

        foreach ($invalidInputs as $invalid) {
            $this->assertNull(PhoneNumberNormalizer::normalize($invalid), "Input should be rejected: '{$invalid}'");
            $this->assertFalse(PhoneNumberNormalizer::isValid($invalid), "isValid should return false for: '{$invalid}'");
        }
    }

    /**
     * Uji pengembalian struktur data method parse().
     */
    public function test_parse_returns_expected_structure(): void
    {
        $validResult = PhoneNumberNormalizer::parse('0812-3456-7890');
        $this->assertTrue($validResult['isValid']);
        $this->assertEquals('6281234567890', $validResult['formatted']);
        $this->assertEquals('0812-3456-7890', $validResult['raw']);
        $this->assertNull($validResult['error']);

        $invalidResult = PhoneNumberNormalizer::parse('BukanNomor');
        $this->assertFalse($invalidResult['isValid']);
        $this->assertEquals('', $invalidResult['formatted']);
        $this->assertEquals('BukanNomor', $invalidResult['raw']);
        $this->assertNotNull($invalidResult['error']);
    }

    /**
     * Uji pembersihan spasi Unicode seperti Non-Breaking Space (\u{00A0}) dan Zero-Width Space (\u{200B}).
     */
    public function test_strips_unicode_whitespace_characters(): void
    {
        // Non-Breaking Space (\u{00A0})
        $nbspPhone = "+62\u{00A0}812\u{00A0}3456\u{00A0}7890";
        $this->assertEquals('6281234567890', PhoneNumberNormalizer::normalize($nbspPhone));

        // Zero-Width Space (\u{200B})
        $zwspPhone = "+62\u{200B}812\u{200B}3456\u{200B}7890";
        $this->assertEquals('6281234567890', PhoneNumberNormalizer::normalize($zwspPhone));

        // Kombinasi NBSP, ZWSP, dan karakter pemisah
        $mixedPhone = "\u{00A0}+62 (812)\u{200B} 345-6789\u{00A0}";
        $this->assertEquals('628123456789', PhoneNumberNormalizer::normalize($mixedPhone));
    }

    /**
     * Uji normalisasi nomor dengan awalan trunk Indonesia 6208... (misal +62 (0812)... atau +6208...).
     */
    public function test_normalizes_indonesian_6208_trunk_prefix(): void
    {
        $testCases = [
            '+62081234567890' => '6281234567890',
            '+62 (0812) 345-6789' => '628123456789',
            '62081234567890' => '6281234567890',
            '+62-0813-9999-0000' => '6281399990000',
            '0062081234567890' => '6281234567890',
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, PhoneNumberNormalizer::normalize($input), "Failed normalizing 6208: {$input}");
        }
    }
}
