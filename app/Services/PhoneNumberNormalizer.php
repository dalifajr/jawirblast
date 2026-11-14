<?php

namespace App\Services;

class PhoneNumberNormalizer
{
    /**
     * Menormalkan nomor telepon ke format internasional E.164.
     * Mengembalikan string angka bersih (misal: "6281234567890") atau null jika tidak valid.
     */
    public static function normalize(string $phone, string $defaultCountryCode = '62'): ?string
    {
        $parsed = self::parse($phone, $defaultCountryCode);
        return $parsed['isValid'] ? $parsed['formatted'] : null;
    }

    /**
     * Mem-parsing nomor telepon dan mengembalikan array informasi lengkap:
     * ['isValid' => bool, 'formatted' => string, 'raw' => string, 'error' => ?string]
     */
    public static function parse(string $phone, string $defaultCountryCode = '62'): array
    {
        $raw = trim($phone);

        if ($raw === '') {
            return [
                'isValid' => false,
                'formatted' => '',
                'raw' => $phone,
                'error' => 'Phone number cannot be empty',
            ];
        }

        // Tolak jika mengandung karakter alfabet atau simbol tidak wajar selain format nomor
        if (preg_match('/[a-zA-Z]/', $raw)) {
            return [
                'isValid' => false,
                'formatted' => '',
                'raw' => $phone,
                'error' => 'Phone number cannot contain alphabetic characters',
            ];
        }

        $hadPlus = str_starts_with($raw, '+');

        // Hapus tanda kurung, spasi (termasuk spasi Unicode), titik, strip, dan tanda plus
        $cleaned = preg_replace('/[\s\p{Z}\p{C}\(\)\-\.]/u', '', $raw);
        if (str_starts_with($cleaned, '+')) {
            $cleaned = substr($cleaned, 1);
            $hadPlus = true;
        }

        // Tolak jika terdapat karakter non-digit setelah pembersihan
        if (!ctype_digit($cleaned)) {
            return [
                'isValid' => false,
                'formatted' => '',
                'raw' => $phone,
                'error' => 'Phone number contains invalid non-numeric symbols',
            ];
        }

        // Tolak nomor yang hanya terdiri dari angka nol berulang
        if (preg_match('/^0+$/', $cleaned)) {
            return [
                'isValid' => false,
                'formatted' => '',
                'raw' => $phone,
                'error' => 'Phone number cannot be all zeros',
            ];
        }

        // Tangani awalan legacy panggilan internasional '00' (0062 -> 62, 008 -> 628)
        if (str_starts_with($cleaned, '0062')) {
            $cleaned = '62' . substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '008')) {
            $cleaned = '628' . substr($cleaned, 3);
        }

        // Tangani awalan trunk Indonesia '6208...' -> '628...' (misal dari input +62 (0812)... atau +6208...)
        if (str_starts_with($cleaned, '6208')) {
            $cleaned = '628' . substr($cleaned, 4);
        }

        // Tangani awalan lokal Indonesia '08' -> '628'
        if (str_starts_with($cleaned, '08')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (!$hadPlus && str_starts_with($cleaned, '8') && $defaultCountryCode === '62') {
            // Awalan '8' tanpa plus untuk format lokal Indonesia (misal: 81234567890 -> 6281234567890)
            $cleaned = '628' . substr($cleaned, 1);
        }

        // Validasi panjang ITU-T E.164: minimal 8 digit dan maksimal 15 digit
        $len = strlen($cleaned);
        if ($len < 8 || $len > 15) {
            return [
                'isValid' => false,
                'formatted' => '',
                'raw' => $phone,
                'error' => "Phone number length ({$len}) must be between 8 and 15 digits",
            ];
        }

        return [
            'isValid' => true,
            'formatted' => $cleaned,
            'raw' => $phone,
            'error' => null,
        ];
    }

    /**
     * Memeriksa apakah nomor telepon valid format E.164.
     */
    public static function isValid(string $phone): bool
    {
        return self::normalize($phone) !== null;
    }
}
