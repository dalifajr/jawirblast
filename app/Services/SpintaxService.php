<?php

namespace App\Services;

use App\Models\Contact;

class SpintaxService
{
    protected int $maxIterations = 50;

    /**
     * Memproses template pesan dengan menggantikan variabel dinamis kontak
     * dan mengurai Spintax bersarang (nested) secara acak.
     */
    public function parse(string $template, array|Contact $variables = []): string
    {
        $normalizedVars = $this->normalizeVariables($variables);

        // 1. Substitusi variabel dinamis ({name}, {phone}, custom_fields)
        $text = $this->replaceVariables($template, $normalizedVars);

        // 2. Resolusi Spintax bertingkat secara iteratif
        return $this->resolveSpintax($text);
    }

    /**
     * Mengurai sintaks Spintax bersarang (pola /\{([^{}]+)\}/) dari kurung terdalam
     * dengan circuit breaker perlindungan dari loop tak terhingga.
     */
    public function resolveSpintax(string $text): string
    {
        $pattern = '/\{([^{}]+)\}/';
        $iteration = 0;

        while (preg_match($pattern, $text) && $iteration < $this->maxIterations) {
            $iteration++;
            $text = preg_replace_callback($pattern, function ($matches) {
                $content = $matches[1];

                // Jika memuat pipa pemisah '|', pilih satu varian secara acak
                if (str_contains($content, '|')) {
                    $options = explode('|', $content);
                    return $options[array_rand($options)];
                }

                // Jika tidak memuat pipa (misal: {Spesial}), kembalikan teks literal tanpa kurung
                return $content;
            }, $text);
        }

        return $text;
    }

    /**
     * Menggantikan tag dinamis seperti {name}, {nama}, {phone}, {nomor},
     * serta custom field JSON array milik data Contact.
     */
    public function replaceVariables(string $text, array $variables = []): string
    {
        // Standarisasi key variabel (lowercase)
        $vars = [];
        foreach ($variables as $key => $val) {
            $vars[strtolower((string) $key)] = is_scalar($val) || is_null($val) ? (string) ($val ?? '') : json_encode($val);
        }

        // Mapping sinonim standar
        if (isset($vars['name']) && !isset($vars['nama'])) {
            $vars['nama'] = $vars['name'];
        } elseif (isset($vars['nama']) && !isset($vars['name'])) {
            $vars['name'] = $vars['nama'];
        }

        if (isset($vars['phone']) && !isset($vars['nomor'])) {
            $vars['nomor'] = $vars['phone'];
        } elseif (isset($vars['nomor']) && !isset($vars['phone'])) {
            $vars['phone'] = $vars['nomor'];
        }

        // Ganti setiap variabel yang terdaftar
        foreach ($vars as $key => $val) {
            $text = preg_replace_callback('/\{' . preg_quote($key, '/') . '\}/i', fn () => (string) $val, $text);
        }

        // Sanitasi fallback aman untuk tag dasar yang belum terganti
        $defaultFallbacks = [
            'name' => '',
            'nama' => '',
            'phone' => '',
            'nomor' => '',
        ];
        foreach ($defaultFallbacks as $defKey => $defVal) {
            $text = preg_replace_callback('/\{' . preg_quote($defKey, '/') . '\}/i', fn () => (string) $defVal, $text);
        }

        return $text;
    }

    /**
     * Menghitung total variasi kombinatorik pesan yang dapat dihasilkan dari template.
     * Mengalikan pilihan sekuensial dan menjumlahkan alternatif pipa '|'.
     */
    public function countVariations(string $template): int
    {
        // Bersihkan template dari variabel dinamis untuk kalkulasi struktur spintax
        $text = preg_replace('/\{([a-zA-Z0-9_\-]+)\}/', '__LITERAL__', $template);

        $counts = [];
        $tokenIdx = 0;
        $pattern = '/\{([^{}]+)\}/';
        $iteration = 0;

        while (preg_match($pattern, $text) && $iteration < $this->maxIterations) {
            $iteration++;
            $text = preg_replace_callback($pattern, function ($matches) use (&$counts, &$tokenIdx) {
                $block = $matches[1];
                $alternatives = explode('|', $block);
                $totalForBlock = 0;

                foreach ($alternatives as $alt) {
                    // Cari semua token penampung count di cabang alternatif ini
                    preg_match_all('/__SPINTAX_CNT_(\d+)__/', $alt, $foundTokens);
                    $altCount = 1;
                    if (!empty($foundTokens[1])) {
                        foreach ($foundTokens[1] as $idx) {
                            $altCount *= ($counts[(int) $idx] ?? 1);
                        }
                    }
                    $totalForBlock += $altCount;
                }

                $tokenIdx++;
                $counts[$tokenIdx] = max(1, $totalForBlock);
                return "__SPINTAX_CNT_{$tokenIdx}__";
            }, $text);
        }

        // Evaluasi sisa token di seluruh string utama
        preg_match_all('/__SPINTAX_CNT_(\d+)__/', $text, $remainingTokens);
        $totalVariations = 1;
        if (!empty($remainingTokens[1])) {
            foreach ($remainingTokens[1] as $idx) {
                $totalVariations *= ($counts[(int) $idx] ?? 1);
                if ($totalVariations > 1000000) {
                    return 1000000;
                }
            }
        }

        return (int) min(1000000, max(1, $totalVariations));
    }

    /**
     * Menghasilkan daftar sampel pratinjau pesan unik untuk simulator UI.
     */
    public function previewSamples(string $template, array|Contact $variables = [], int $count = 5): array
    {
        $samples = [];
        $maxAttempts = $count * 10;
        $attempts = 0;

        while (count($samples) < $count && $attempts < $maxAttempts) {
            $attempts++;
            $sample = $this->parse($template, $variables);
            if (!in_array($sample, $samples, true)) {
                $samples[] = $sample;
            }
        }

        // Jika variasi unik sedikit, penuhi array hingga count jika perlu
        while (count($samples) < $count && count($samples) > 0) {
            $samples[] = $samples[array_rand($samples)];
        }

        return $samples;
    }

    /**
     * Menormalisasi input $variables menjadi array asosiatif seragam.
     */
    protected function normalizeVariables(array|Contact $variables): array
    {
        if ($variables instanceof Contact) {
            $data = [
                'name' => $variables->name ?? '',
                'nama' => $variables->name ?? '',
                'phone' => $variables->phone_number ?? $variables->raw_phone ?? '',
                'nomor' => $variables->phone_number ?? $variables->raw_phone ?? '',
            ];

            if (is_array($variables->custom_fields)) {
                foreach ($variables->custom_fields as $cfKey => $cfVal) {
                    $data[$cfKey] = $cfVal;
                }
            }

            return $data;
        }

        return $variables;
    }
}
