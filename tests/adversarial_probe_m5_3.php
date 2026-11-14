<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\SpintaxService;
use App\Models\Contact;

$service = new SpintaxService();
$failures = [];
$totalTests = 0;

function assertCondition(bool $cond, string $desc, string &$failureMsg = null) {
    global $totalTests, $failures;
    $totalTests++;
    if (!$cond) {
        $msg = "FAILED: $desc" . ($failureMsg ? " -> $failureMsg" : "");
        $failures[] = $msg;
        echo "[X] $msg\n";
    } else {
        echo "[OK] $desc\n";
    }
}

echo "=== STARTING ADVERSARIAL STRESS TEST FOR SPINTAX SERVICE (ITERATION 2) ===\n\n";

// =========================================================================
// SECTION 1: REGRESSION SUITE - CURRENCY & BACKREFERENCE REPLACEMENT
// =========================================================================
echo "--- SECTION 1: Currency & Regex Backreferences ---\n";

$currencyCases = [
    ['promo' => '$100', 'expected' => '$100'],
    ['promo' => '$50', 'expected' => '$50'],
    ['promo' => '$25', 'expected' => '$25'],
    ['promo' => '$1,000.00', 'expected' => '$1,000.00'],
    ['promo' => '$0', 'expected' => '$0'],
    ['promo' => '$99.99', 'expected' => '$99.99'],
    ['promo' => '\1', 'expected' => '\1'],
    ['promo' => '\2-SPECIAL', 'expected' => '\2-SPECIAL'],
    ['promo' => '\0', 'expected' => '\0'],
    ['promo' => '\$100', 'expected' => '\$100'],
    ['promo' => '$$$100', 'expected' => '$$$100'],
    ['promo' => '$1$2$3', 'expected' => '$1$2$3'],
    ['promo' => '\1\2\3', 'expected' => '\1\2\3'],
    ['promo' => '${foo}', 'expected' => '${foo}'],
    ['promo' => '$007', 'expected' => '$007'],
    ['promo' => '$999', 'expected' => '$999'],
];

foreach ($currencyCases as $c) {
    $res = $service->parse('Bonus: {promo}', ['promo' => $c['promo']]);
    $expected = 'Bonus: ' . $c['expected'];
    $err = "Got: '$res', Expected: '$expected'";
    assertCondition($res === $expected, "Single var '{$c['promo']}' substitution", $err);
}

// Multi-variable simultaneous currency & backreference test
$multiTemplate = 'Halo {name}, hadiah {hadiah1}, cashback {hadiah2}, kupon {hadiah3}, diskon {hadiah4}, kode {kode1}, token {kode2}!';
$multiVars = [
    'name' => 'Dzulfikri',
    'hadiah1' => '$100',
    'hadiah2' => '$50',
    'hadiah3' => '$25',
    'hadiah4' => '$1,000',
    'kode1' => '\1',
    'kode2' => '\2-SPECIAL',
];
$multiExpected = 'Halo Dzulfikri, hadiah $100, cashback $50, kupon $25, diskon $1,000, kode \1, token \2-SPECIAL!';
$multiRes = $service->parse($multiTemplate, $multiVars);
$err = "Got: '$multiRes', Expected: '$multiExpected'";
assertCondition($multiRes === $multiExpected, "Multi-variable simultaneous currency & backreference substitution", $err);

// Currency variables embedded inside spintax blocks
$nestedVarTemplate = '{Klaim {promo}|Ambil {promo}} untuk {name}!';
for ($i = 0; $i < 10; $i++) {
    $res = $service->parse($nestedVarTemplate, ['name' => 'Budi', 'promo' => '$100']);
    $valid = ($res === 'Klaim $100 untuk Budi!' || $res === 'Ambil $100 untuk Budi!');
    if (!$valid) {
        assertCondition(false, "Currency inside spintax iteration $i", "Got invalid: '$res'");
        break;
    }
}
assertCondition(true, "10 iterations of currency variable inside spintax choice");

// Multiple spintax choices with distinct currency variables
$choiceTemplate = '{Pilihan 1: {val1}|Pilihan 2: {val2}}';
$choiceVars = ['val1' => '$50', 'val2' => '$100'];
for ($i = 0; $i < 10; $i++) {
    $res = $service->parse($choiceTemplate, $choiceVars);
    $valid = ($res === 'Pilihan 1: $50' || $res === 'Pilihan 2: $100');
    if (!$valid) {
        assertCondition(false, "Multiple spintax choices with currency variables iteration $i", "Got invalid: '$res'");
        break;
    }
}
assertCondition(true, "10 iterations of multiple spintax choices with currency vars");


// =========================================================================
// SECTION 2: ADVERSARIAL VARIABLE VALUES (PIPES, BRACES, ARRAYS, SPECIAL)
// =========================================================================
echo "\n--- SECTION 2: Adversarial Variable Payloads ---\n";

// Variable containing pipe '|'
// Note: If variable is replaced first, does resolveSpintax treat '|' as spintax alternative?
// Let's test what happens when variable value contains '|' outside of spintax:
$pipeVarTemplate = 'Jabatan: {title}';
$pipeVarRes = $service->parse($pipeVarTemplate, ['title' => 'CEO | Founder']);
assertCondition($pipeVarRes === 'Jabatan: CEO | Founder', "Variable containing pipe '|' outside spintax");

// Variable containing JSON / array
$arrVarTemplate = 'Data: {details}';
$arrVarRes = $service->parse($arrVarTemplate, ['details' => ['tier' => 'gold', 'pts' => 500]]);
assertCondition(str_contains($arrVarRes, '{"tier":"gold","pts":500}'), "Variable with array encoded as JSON string");

// Variable containing null or empty string
$nullVarTemplate = 'Info: [{empty}] and [{null}]';
$nullVarRes = $service->parse($nullVarTemplate, ['empty' => '', 'null' => null]);
assertCondition($nullVarRes === 'Info: [] and []', "Variable with empty and null values");

// Case insensitivity of variable replacement: {Name}, {NAME}, {nAmE}
$caseTemplate = '{NAME} - {Name} - {name} - {PHONE} - {Nomor}';
$caseRes = $service->parse($caseTemplate, ['name' => 'Alice', 'phone' => '0812345']);
assertCondition($caseRes === 'Alice - Alice - Alice - 0812345 - 0812345', "Case insensitivity of variable tags");

// Unmatched default variables fallback
$unmatchedTemplate = 'Halo {name}, {nama}, {phone}, {nomor}, dan {unknown_tag}';
$unmatchedRes = $service->parse($unmatchedTemplate, []);
assertCondition($unmatchedRes === 'Halo , , , , dan {unknown_tag}', "Default fallbacks replaced with empty string, unknown untouched");


// =========================================================================
// SECTION 3: DEEP NESTING & CIRCUIT BREAKER
// =========================================================================
echo "\n--- SECTION 3: Deep Nesting & Circuit Breaker ---\n";

// 5-level nesting
$nest5 = '{L1_{a|b}|{L2_{c|d}|{L3_{e|f}|{L4_{g|h}|{L5_{i|j}}}}}}';
$res5 = $service->parse($nest5);
assertCondition(!str_contains($res5, '{') && !str_contains($res5, '}'), "5-level nesting fully resolved without brackets", "Got: $res5");

// 10-level nesting
$nest10 = 'A';
for ($i = 10; $i >= 1; $i--) {
    $nest10 = "{lv{$i}_X|" . $nest10 . "}";
}
$t0 = microtime(true);
$res10 = $service->parse($nest10);
$dur10 = (microtime(true) - $t0) * 1000;
assertCondition(!str_contains($res10, '{') && !str_contains($res10, '}'), "10-level nesting resolved (took " . sprintf("%.3f", $dur10) . " ms)", "Got: $res10");

// 25-level nesting
$nest25 = 'FINAL';
for ($i = 25; $i >= 1; $i--) {
    $nest25 = "{lv{$i}|" . $nest25 . "}";
}
$t0 = microtime(true);
$res25 = $service->parse($nest25);
$dur25 = (microtime(true) - $t0) * 1000;
assertCondition(!str_contains($res25, '{') && !str_contains($res25, '}'), "25-level nesting resolved (took " . sprintf("%.3f", $dur25) . " ms)", "Got: $res25");

// 60-level nesting (exceeds maxIterations = 50)
$nest60 = 'CORE';
for ($i = 60; $i >= 1; $i--) {
    $nest60 = "{N{$i}|" . $nest60 . "}";
}
$t0 = microtime(true);
$res60 = $service->parse($nest60);
$dur60 = (microtime(true) - $t0) * 1000;
// After 50 iterations, exactly 10 levels of outermost brackets should remain, circuit breaker stops cleanly!
$remainingOpen = substr_count($res60, '{');
assertCondition($remainingOpen > 0 && $dur60 < 50.0, "Circuit breaker stops at 50 iterations safely in " . sprintf("%.3f", $dur60) . " ms (remaining braces: $remainingOpen)");


// =========================================================================
// SECTION 4: UNBALANCED, MALFORMED & ADVERSARIAL BRACKETS
// =========================================================================
echo "\n--- SECTION 4: Malformed Brackets & Syntax Anomalies ---\n";

$malformedCases = [
    'Unclosed single' => '{Halo|Hai',
    'Unopened single' => 'Halo|Hai}',
    'Empty braces' => '{}',
    'Multiple empty braces' => '{}{}{}',
    'Inverted braces' => '}{',
    'Inverted with content' => '}Halo|Hai{',
    'Triple nested unbalanced' => '{{{Halo|Hai}',
    'Double closing' => '{Halo|Hai}}',
    'Empty choices' => '{|}',
    'Multiple empty choices' => '{|||}',
    'Just pipe' => '|',
    'Pipes without braces' => 'Halo | Dunia | Kita',
    'Braces with no pipe' => '{HanyaSatu}',
    'Whitespace only choices' => '{   |   |   }',
    'Nested empty braces' => '{{}}',
    'Emoji spintax' => '{🔥 Promo Mantap|⚡ Diskon Kilat|🎉 Hadiah Heboh}',
    'Newlines inside spintax' => "{\nBaris 1\n|\nBaris 2\n}",
    'Complex malformed combined' => '{{A|B} C {D|E}} {F|G',
];

foreach ($malformedCases as $desc => $malTemplate) {
    try {
        $t0 = microtime(true);
        $res = $service->parse($malTemplate);
        $dur = (microtime(true) - $t0) * 1000;
        assertCondition(is_string($res) && $dur < 50.0, "Malformed bracket handling: $desc (took " . sprintf("%.3f", $dur) . " ms)");
    } catch (\Throwable $e) {
        assertCondition(false, "Malformed bracket threw exception: $desc", $e->getMessage());
    }
}


// =========================================================================
// SECTION 5: HUGE STRINGS & LATENCY STRESS TEST
// =========================================================================
echo "\n--- SECTION 5: Huge Payload & Latency Stress Test ---\n";

// 500 spintax blocks (~10KB)
$blocks500 = [];
for ($i = 0; $i < 500; $i++) {
    $blocks500[] = "{opt_{$i}_A|opt_{$i}_B|opt_{$i}_C}";
}
$hugeTemplate500 = implode(' ', $blocks500);

$t0 = microtime(true);
$hugeRes500 = $service->parse($hugeTemplate500);
$hugeDur500 = (microtime(true) - $t0) * 1000;
assertCondition($hugeDur500 < 100.0, "500 Spintax blocks resolved in " . sprintf("%.3f", $hugeDur500) . " ms (< 100ms threshold)");
assertCondition(!str_contains($hugeRes500, '{'), "500 Spintax blocks fully resolved with 0 leftover braces");

// 2,000 spintax blocks (~40KB)
$blocks2000 = [];
for ($i = 0; $i < 2000; $i++) {
    $blocks2000[] = "{opt_{$i}_A|opt_{$i}_B}";
}
$hugeTemplate2000 = implode(' ', $blocks2000);

$t0 = microtime(true);
$hugeRes2000 = $service->parse($hugeTemplate2000);
$hugeDur2000 = (microtime(true) - $t0) * 1000;
assertCondition($hugeDur2000 < 100.0, "2,000 Spintax blocks resolved in " . sprintf("%.3f", $hugeDur2000) . " ms (< 100ms threshold)");

// Benchmark 1,000 runs of standard marketing template
$benchTemplate = '{Halo|Hai|Selamat {pagi|siang|sore}} {name}, nikmati promo {diskon} spesial hanya untuk Anda di {toko}!';
$benchVars = [
    'name' => 'Budi Santoso',
    'diskon' => '$50',
    'toko' => 'Jawir Mart',
];
$latencies = [];
for ($i = 0; $i < 1000; $i++) {
    $t0 = microtime(true);
    $bRes = $service->parse($benchTemplate, $benchVars);
    $latencies[] = (microtime(true) - $t0) * 1000;
}
sort($latencies);
$avgLat = array_sum($latencies) / count($latencies);
$p50 = $latencies[(int)(0.50 * count($latencies))];
$p95 = $latencies[(int)(0.95 * count($latencies))];
$p99 = $latencies[(int)(0.99 * count($latencies))];
$maxLat = max($latencies);

echo "Latency Benchmark (1,000 calls):\n";
echo "  Average: " . sprintf("%.4f", $avgLat) . " ms\n";
echo "  P50:     " . sprintf("%.4f", $p50) . " ms\n";
echo "  P95:     " . sprintf("%.4f", $p95) . " ms\n";
echo "  P99:     " . sprintf("%.4f", $p99) . " ms\n";
echo "  Max:     " . sprintf("%.4f", $maxLat) . " ms\n";

assertCondition($avgLat < 1.0, "Average latency < 1.0 ms (Actual: " . sprintf("%.4f", $avgLat) . " ms)");
assertCondition($p99 < 10.0, "P99 latency < 10.0 ms (Actual: " . sprintf("%.4f", $p99) . " ms)");
assertCondition($maxLat < 100.0, "Max latency < 100.0 ms (Actual: " . sprintf("%.4f", $maxLat) . " ms)");


// =========================================================================
// SECTION 6: COUNTVARIATIONS & COMBINATORIAL EXPLOSION CAPPING
// =========================================================================
echo "\n--- SECTION 6: countVariations & Combinatorial Math ---\n";

// Simple combinations: 3 x 2 = 6
$cnt1 = $service->countVariations('{A|B|C} {1|2}');
assertCondition($cnt1 === 6, "Simple countVariations (3 x 2 = 6, got $cnt1)");

// Nested combinations: 1 + (2 x 1) = 3
$cnt2 = $service->countVariations('{A|{B|C}}');
assertCondition($cnt2 === 3, "Nested countVariations (1 + 2 = 3, got $cnt2)");

// Combinatorial explosion: 30 blocks of 5 options -> 5^30 > 9.3 x 10^20
// Must cap safely at 1,000,000 without negative integer overflow or error
$expBlocks = [];
for ($i = 0; $i < 30; $i++) {
    $expBlocks[] = '{1|2|3|4|5}';
}
$expTemplate = implode(' ', $expBlocks);
$t0 = microtime(true);
$cntExp = $service->countVariations($expTemplate);
$durExp = (microtime(true) - $t0) * 1000;
assertCondition($cntExp === 1000000, "Combinatorial explosion capped at 1,000,000 (got $cntExp in " . sprintf("%.3f", $durExp) . " ms)");

// Dynamic tags should be ignored in variation count
$cntTags = $service->countVariations('{name} {phone} {custom_var} {A|B}');
assertCondition($cntTags === 2, "Dynamic tags treated as literal in countVariations (got $cntTags, expected 2)");


// =========================================================================
// SECTION 7: PREVIEWSAMPLES METHOD
// =========================================================================
echo "\n--- SECTION 7: previewSamples Method ---\n";

$samples = $service->previewSamples('{Halo|Hai|Pagi} {name}', ['name' => 'John'], 3);
assertCondition(count($samples) === 3, "previewSamples returns requested count of 3");
foreach ($samples as $s) {
    assertCondition(str_starts_with($s, 'Halo John') || str_starts_with($s, 'Hai John') || str_starts_with($s, 'Pagi John'), "Sample content valid: '$s'");
}


// =========================================================================
// SECTION 8: CONTACT MODEL INTEGRATION WITH CURRENCY & BACKREFERENCES
// =========================================================================
echo "\n--- SECTION 8: Contact Model Integration ---\n";

$contact = new Contact([
    'name' => 'Siti Rahma',
    'phone_number' => '6281299988877',
    'custom_fields' => [
        'voucher' => '$1,000.00',
        'diskon' => '$50',
        'admin_fee' => '$10',
        'kode_promo' => '\2-SPECIAL',
        'tier' => 'Platinum',
    ],
]);

$contactTemplate = 'Yth. {name} ({phone}), voucher Anda {voucher}, diskon {diskon}, admin {admin_fee}, kode {kode_promo}, tier {tier}!';
$contactRes = $service->parse($contactTemplate, $contact);
$contactExpected = 'Yth. Siti Rahma (6281299988877), voucher $1,000.00, diskon $50, admin $10, kode \2-SPECIAL, tier Platinum!';

assertCondition($contactRes === $contactExpected, "Contact model custom_fields with currency & backreferences", "Got: '$contactRes', Expected: '$contactExpected'");

// =========================================================================
// SUMMARY
// =========================================================================
echo "\n=======================================================\n";
echo "TOTAL TESTS: $totalTests\n";
echo "FAILURES:    " . count($failures) . "\n";
if (count($failures) > 0) {
    echo "VERDICT: FAIL\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
} else {
    echo "VERDICT: APPROVE (ALL TESTS PASSED EMPIRICALLY)\n";
}
echo "=======================================================\n";

exit(count($failures) === 0 ? 0 : 1);
