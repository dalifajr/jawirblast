<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportExportController extends Controller
{
    /**
     * Impor kontak massal dari file CSV, TXT, atau XLSX/XLS
     * dengan chunk processing (500 baris per batch) dan deduplikasi.
     */
    public function import(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'group_id' => ['nullable', 'integer'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();
        $userId = auth()->id() ?? 1;
        $groupId = $request->filled('group_id') ? (int) $request->input('group_id') : null;

        // Otorisasi IDOR: Verifikasi kepemilikan grup kontak oleh user yang login
        if ($groupId !== null) {
            $groupValid = DB::table('contact_groups')->where('id', $groupId)->where('user_id', $userId)->exists();
            if (!$groupValid) {
                $groupId = null;
            }
        }

        $stats = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];

        $chunkSize = 500;
        $currentChunk = [];

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                // Pemrosesan berkas Excel via PhpSpreadsheet
                $readerType = ($extension === 'xlsx') ? 'Xlsx' : 'Xls';
                $reader = IOFactory::createReader($readerType);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filePath);
                $sheet = $spreadsheet->getActiveSheet();

                $header = null;
                $headerCols = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = trim((string) $cell->getValue());
                    }

                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    if ($header === null) {
                        $isHeader = $this->isHeaderRow($rowData);
                        if ($isHeader) {
                            $header = $rowData;
                            $headerCols = $this->mapHeaderColumns($rowData);
                            continue;
                        } else {
                            $header = [];
                            $headerCols = ['name' => 0, 'phone' => 1, 'extra' => []];
                        }
                    }

                    $record = $this->extractContactData($rowData, $headerCols);
                    if ($record !== null) {
                        $currentChunk[] = $record;
                        $stats['total']++;

                        if (count($currentChunk) >= $chunkSize) {
                            $this->processChunk($currentChunk, $userId, $groupId, $stats);
                            $currentChunk = [];
                        }
                    }
                }
            } else {
                // Pemrosesan native file CSV / TXT
                $handle = fopen($filePath, 'r');
                if ($handle === false) {
                    return redirect()->back()->with('error', 'Gagal membaca file impor.');
                }

                // Deteksi baris pertama dan pemisah (delimiter)
                $firstLine = fgets($handle);
                rewind($handle);

                $delimiter = ',';
                if ($firstLine !== false) {
                    if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                        $delimiter = ';';
                    } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                        $delimiter = "\t";
                    }
                }

                $firstRow = fgetcsv($handle, 0, $delimiter);
                $header = null;
                $headerCols = ['name' => 0, 'phone' => 1, 'extra' => []];

                if ($firstRow !== false) {
                    if ($this->isHeaderRow($firstRow)) {
                        $header = $firstRow;
                        $headerCols = $this->mapHeaderColumns($firstRow);
                    } else {
                        // Baris pertama merupakan data
                        $record = $this->extractContactData($firstRow, $headerCols);
                        if ($record !== null) {
                            $currentChunk[] = $record;
                            $stats['total']++;
                        }
                    }
                }

                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if (empty($row) || (count($row) === 1 && trim($row[0] ?? '') === '')) {
                        continue;
                    }

                    $record = $this->extractContactData($row, $headerCols);
                    if ($record !== null) {
                        $currentChunk[] = $record;
                        $stats['total']++;

                        if (count($currentChunk) >= $chunkSize) {
                            $this->processChunk($currentChunk, $userId, $groupId, $stats);
                            $currentChunk = [];
                        }
                    }
                }

                fclose($handle);
            }

            // Proses sisa chunk terakhir
            if (!empty($currentChunk)) {
                $this->processChunk($currentChunk, $userId, $groupId, $stats);
            }
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax() || !$request->hasHeader('Referer')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berkas rusak atau format tidak valid',
                ], 422);
            }

            return redirect()->back()->with('error', 'Berkas rusak atau format tidak valid.');
        }

        $importedCount = $stats['imported'] + $stats['updated'];

        if ($request->wantsJson() || $request->ajax() || !$request->hasHeader('Referer')) {
            return response()->json([
                'success' => true,
                'imported_count' => $importedCount,
                'stats' => $stats,
            ], 200);
        }

        return redirect()->route('contacts.index')->with(
            'success',
            "Impor berhasil: {$stats['imported']} kontak baru ditambahkan, {$stats['updated']} diperbarui, {$stats['invalid']} invalid."
        );
    }

    /**
     * Memproses batch chunk kontak ke database dengan deduplikasi.
     */
    protected function processChunk(array $chunk, int $userId, ?int $groupId, array &$stats): void
    {
        $validPhones = [];
        $preparedRows = [];

        foreach ($chunk as $item) {
            $rawPhone = mb_substr((string) ($item['phone'] ?? ''), 0, 35);
            $name = (string) ($item['name'] ?? '');
            $customFields = $item['custom_fields'] ?? null;

            $parsed = PhoneNumberNormalizer::parse($rawPhone);

            if ($parsed['isValid']) {
                $cleanPhone = mb_substr($parsed['formatted'], 0, 25);
                $status = 'active';
                $validPhones[] = $cleanPhone;
            } else {
                $digitsOnly = preg_replace('/[^0-9]/', '', $rawPhone);
                $cleanPhone = mb_substr($digitsOnly ?: 'INVALID', 0, 25);
                $status = 'invalid';
                $stats['invalid']++;
            }

            $preparedRows[] = [
                'name' => $name,
                'raw_phone' => $rawPhone,
                'clean_phone' => $cleanPhone,
                'custom_fields' => $customFields,
                'status' => $status,
                'is_valid' => $parsed['isValid'],
            ];
        }

        // Cari kontak valid yang sudah ada untuk user ini
        $existing = [];
        if (!empty($validPhones)) {
            $existing = DB::table('contacts')
                ->where('user_id', $userId)
                ->whereIn('phone_number', array_unique($validPhones))
                ->get(['id', 'phone_number'])
                ->keyBy('phone_number')
                ->all();
        }

        $pivotInserts = [];
        $now = now();

        foreach ($preparedRows as $row) {
            $cleanPhone = mb_substr($row['clean_phone'], 0, 25);
            $rawPhone = mb_substr($row['raw_phone'], 0, 35);
            $customJson = !empty($row['custom_fields']) ? json_encode($row['custom_fields']) : null;

            if ($row['is_valid'] && isset($existing[$cleanPhone])) {
                // Kontak sudah ada: update data (deduplikasi)
                $contactId = $existing[$cleanPhone]->id;
                DB::table('contacts')->where('id', $contactId)->update([
                    'name' => $row['name'],
                    'raw_phone' => $rawPhone,
                    'custom_fields' => $customJson,
                    'status' => 'active',
                    'updated_at' => $now,
                ]);
                $stats['updated']++;
                $stats['duplicates']++;
            } else {
                // Kontak baru
                $contactId = DB::table('contacts')->insertGetId([
                    'user_id' => $userId,
                    'name' => $row['name'],
                    'phone_number' => $cleanPhone,
                    'raw_phone' => $rawPhone,
                    'custom_fields' => $customJson,
                    'status' => $row['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($row['is_valid']) {
                    $stats['imported']++;
                    // Catat ke daftar existing agar tidak dobel insert dalam chunk yang sama
                    $existing[$cleanPhone] = (object) ['id' => $contactId, 'phone_number' => $cleanPhone];
                }
            }

            if ($groupId !== null && $contactId) {
                $pivotInserts[] = [
                    'contact_id' => $contactId,
                    'group_id' => $groupId,
                ];
            }
        }

        if (!empty($pivotInserts)) {
            DB::table('contact_group_pivot')->insertOrIgnore($pivotInserts);
        }
    }

    /**
     * Cek apakah baris pertama merupakan header.
     */
    protected function isHeaderRow(array $row): bool
    {
        $headerWords = ['name', 'nama', 'phone', 'telepon', 'nomor', 'mobile', 'hp', 'no_hp', 'status', 'group', 'grup', 'email'];
        foreach ($row as $col) {
            $normalized = strtolower(trim((string) $col));
            if (in_array($normalized, $headerWords)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Petakan indeks kolom header.
     */
    protected function mapHeaderColumns(array $headerRow): array
    {
        $nameIndex = 0;
        $phoneIndex = 1;
        $extraCols = [];

        foreach ($headerRow as $idx => $col) {
            $lower = strtolower(trim((string) $col));
            if (in_array($lower, ['name', 'nama', 'full_name', 'fullname', 'nama_lengkap'])) {
                $nameIndex = $idx;
            } elseif (in_array($lower, ['phone', 'telepon', 'nomor', 'no_hp', 'nohp', 'mobile', 'whatsapp', 'phone_number'])) {
                $phoneIndex = $idx;
            } else {
                $extraCols[$idx] = trim((string) $col);
            }
        }

        return [
            'name' => $nameIndex,
            'phone' => $phoneIndex,
            'extra' => $extraCols,
        ];
    }

    /**
     * Ekstrak data kontak dari baris data.
     */
    protected function extractContactData(array $row, array $headerCols): ?array
    {
        $nameIdx = $headerCols['name'];
        $phoneIdx = $headerCols['phone'];

        $name = isset($row[$nameIdx]) ? trim((string) $row[$nameIdx]) : '';
        $phone = isset($row[$phoneIdx]) ? trim((string) $row[$phoneIdx]) : '';

        // Jika hanya 1 kolom terisi dan menyerupai nomor telepon
        if ($phone === '' && count($row) === 1 && $name !== '') {
            $phone = $name;
            $name = 'Contact ' . substr($phone, -4);
        }

        if ($name === '' && $phone === '') {
            return null;
        }

        if ($name === '') {
            $name = 'Contact ' . substr($phone, -4);
        }

        $customFields = null;
        if (!empty($headerCols['extra'])) {
            foreach ($headerCols['extra'] as $colIdx => $colName) {
                if (isset($row[$colIdx]) && trim((string) $row[$colIdx]) !== '') {
                    $customFields[$colName] = trim((string) $row[$colIdx]);
                }
            }
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'custom_fields' => $customFields,
        ];
    }

    /**
     * Ekspor kontak ke format CSV streaming (StreamedResponse) dengan penggunaan memori konstan (<16MB).
     */
    public function export(Request $request): StreamedResponse
    {
        $userId = auth()->id() ?? 1;

        $query = DB::table('contacts')->where('user_id', $userId);

        if ($request->filled('group_id')) {
            $groupId = (int) $request->input('group_id');
            $query->join('contact_group_pivot', 'contacts.id', '=', 'contact_group_pivot.contact_id')
                  ->where('contact_group_pivot.group_id', $groupId)
                  ->select('contacts.*');
        }

        if ($request->filled('status')) {
            $query->where('contacts.status', $request->input('status'));
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts_export.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new class(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Tulis UTF-8 BOM di awal stream untuk kompatibilitas Microsoft Excel di Windows
            fwrite($handle, "\xEF\xBB\xBF");
            fputs($handle, "Name,Phone Number,Status\n");

            $formulaChars = ['=', '+', '-', '@', "\t", "\r"];

            foreach ($query->cursor() as $contact) {
                $rawName = (string) $contact->name;
                $firstChar = mb_substr($rawName, 0, 1);
                $safeName = in_array($firstChar, $formulaChars, true) ? "'" . $rawName : $rawName;

                fputcsv($handle, [$safeName, $contact->phone_number, $contact->status]);
            }
            fclose($handle);
        }, 200, $headers) extends StreamedResponse {
            public function getContent(): string|false
            {
                ob_start();
                $this->streamed = false;
                $this->sendContent();
                return (string) ob_get_clean();
            }
        };
    }

    /**
     * Unduh berkas template CSV contoh impor kontak.
     */
    public function template(): Response
    {
        $csvData = "Name,Phone Number,Note\n";
        $csvData .= "Budi Rahardjo,081234567891,VIP Customer\n";
        $csvData .= "Dewi Sartika,081234567892,Pelanggan Prioritas\n";
        $csvData .= "Ahmad Dahlan,+6281234567893,Member Baru\n";

        return response($csvData, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts_template.csv"',
        ]);
    }
}
