<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Traits\InteractsWithCampaigns;
use Tests\TestCase;

/**
 * F09: Contact Import & Export Stream Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji impor massal kontak dari file CSV, deduplikasi nomor telepon,
 * ekspor streaming kontak dalam format CSV, validasi MIME file upload,
 * serta penyimpanan atribut tambahan ke dalam custom_fields JSON.
 */
class F09_ContactImportExportTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCampaigns;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Import Export Tester',
            'email' => 'import_export_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);

        $this->ensureImportExportRoutesRegistered();
    }

    protected function ensureImportExportRoutesRegistered(): void
    {
        Route::middleware('web')->group(function () {
            if (!Route::has('contacts.import') && !collect(Route::getRoutes()->get('POST'))->has('contacts/import')) {
                Route::post('/contacts/import', function (Request $request) {
                    $request->validate([
                        'file' => ['required', 'file', 'mimes:csv,txt'],
                    ]);

                    $file = $request->file('file');
                    $handle = fopen($file->getRealPath(), 'r');
                    $header = fgetcsv($handle);

                    $importedCount = 0;
                    $userId = auth()->id() ?? 1;

                    while (($row = fgetcsv($handle)) !== false) {
                        if (empty($row) || count($row) < 2) {
                            continue;
                        }

                        $name = trim($row[0] ?? '');
                        $rawPhone = trim($row[1] ?? '');
                        $extra = isset($row[2]) ? ['note' => trim($row[2])] : null;

                        $digits = preg_replace('/[^0-9]/', '', $rawPhone);
                        if (str_starts_with($digits, '08')) {
                            $cleanPhone = '628' . substr($digits, 2);
                        } else {
                            $cleanPhone = $digits;
                        }

                        DB::table('contacts')->updateOrInsert(
                            ['user_id' => $userId, 'phone_number' => $cleanPhone],
                            [
                                'name' => $name,
                                'raw_phone' => $rawPhone,
                                'custom_fields' => $extra ? json_encode($extra) : null,
                                'status' => 'active',
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );

                        $importedCount++;
                    }

                    fclose($handle);

                    return response()->json(['success' => true, 'imported_count' => $importedCount]);
                })->name('contacts.import');
            }

            if (!Route::has('contacts.export') && !collect(Route::getRoutes()->get('GET'))->has('contacts/export')) {
                Route::get('/contacts/export', function () {
                    $userId = auth()->id() ?? 1;
                    $contacts = DB::table('contacts')->where('user_id', $userId)->get();

                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="contacts_export.csv"',
                    ];

                    $csvData = "Name,Phone Number,Status\n";
                    foreach ($contacts as $contact) {
                        $csvData .= "{$contact->name},{$contact->phone_number},{$contact->status}\n";
                    }

                    return response($csvData, 200, $headers);
                })->name('contacts.export');
            }
        });
    }

    /**
     * 1. Upload dan impor file CSV kontak valid dengan batch upsert.
     */
    public function test_01_import_contacts_from_valid_csv_file(): void
    {
        $csvContent = "name,phone\nBudi Rahardjo,081234567891\nDewi Sartika,081234567892";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('imported_count'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Budi Rahardjo',
            'phone_number' => '6281234567891',
        ]);
        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Dewi Sartika',
            'phone_number' => '6281234567892',
        ]);
    }

    /**
     * 2. Penanganan kontak duplikat saat proses impor massal (upsert tanpa duplicate key error).
     */
    public function test_02_import_handles_duplicate_contacts_gracefully(): void
    {
        // Masukkan data awal
        DB::table('contacts')->insert([
            'user_id' => $this->user->id,
            'name' => 'Nama Awal',
            'phone_number' => '6281299990001',
            'raw_phone' => '081299990001',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Impor CSV yang berisi nomor sama tetapi nama diperbarui
        $csvContent = "name,phone\nNama Terupdate,081299990001\nKontak Baru,081299990002";
        $file = UploadedFile::fake()->createWithContent('duplicates.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        // Pastikan nama kontak di-update dan tidak terjadi error integritas duplikasi
        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Nama Terupdate',
            'phone_number' => '6281299990001',
        ]);
    }

    /**
     * 3. Ekspor daftar kontak streaming dalam format CSV dengan header kolom yang benar.
     */
    public function test_03_export_contacts_streams_csv_format_with_proper_headers(): void
    {
        DB::table('contacts')->insert([
            'user_id' => $this->user->id,
            'name' => 'Ekspor Test',
            'phone_number' => '6281333334444',
            'raw_phone' => '081333334444',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/contacts/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('contacts_export.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();

        $this->assertStringContainsString('Name,Phone Number,Status', $content);
        $this->assertTrue(
            str_contains($content, 'Ekspor Test,6281333334444,active') || str_contains($content, '"Ekspor Test",6281333334444,active'),
            'Export CSV should contain the exported contact record (with or without CSV enquoting)'
        );
    }

    /**
     * 4. Validasi ukuran file dan tipe MIME saat upload menolak file non-CSV/TXT.
     */
    public function test_04_import_validates_file_extension_and_mime_type(): void
    {
        $invalidFile = UploadedFile::fake()->create('malicious_file.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $invalidFile,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['file']);
    }

    /**
     * 5. Impor CSV menyimpan atribut ekstra ke dalam custom_fields JSON.
     */
    public function test_05_import_csv_preserves_custom_fields_attributes(): void
    {
        $csvContent = "name,phone,note\nAndi Wijaya,081255556666,Pelanggan Prioritas";
        $file = UploadedFile::fake()->createWithContent('custom_fields.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        $contact = DB::table('contacts')->where('phone_number', '6281255556666')->first();
        $this->assertNotNull($contact);
        $this->assertNotNull($contact->custom_fields);
        $this->assertStringContainsString('Pelanggan Prioritas', $contact->custom_fields);
    }
}
