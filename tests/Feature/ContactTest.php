<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Contact Tester',
            'email' => 'contact_test_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'id',
        ]);
    }

    /**
     * Tamu (unauthenticated) tidak dapat mengakses rute kontak.
     */
    public function test_guest_cannot_access_contacts(): void
    {
        $response = $this->get('/contacts');
        $response->assertRedirect('/login');

        $responseGroup = $this->get('/contacts/groups');
        $responseGroup->assertRedirect('/login');
    }

    /**
     * Pengguna terautentikasi dapat melihat daftar kontak dan melakukan pencarian.
     */
    public function test_authenticated_user_can_view_contacts_and_search(): void
    {
        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Target Search Alpha',
            'phone_number' => '62811112222',
            'raw_phone' => '0811112222',
            'status' => 'active',
        ]);

        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Target Search Beta',
            'phone_number' => '62833334444',
            'raw_phone' => '0833334444',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get('/contacts?search=Alpha');
        $response->assertStatus(200);
        $response->assertSee('Target Search Alpha');
        $response->assertDontSee('Target Search Beta');
    }

    /**
     * Simpan kontak baru menormalisasi nomor telepon dan mengembalikan status 201.
     */
    public function test_store_contact_normalizes_phone_number_and_returns_201(): void
    {
        $response = $this->actingAs($this->user)->postJson('/contacts', [
            'name' => 'Pelanggan Baru',
            'phone_number' => '0812-3456-7890',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('6281234567890', $response->json('phone_number'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Pelanggan Baru',
            'phone_number' => '6281234567890',
            'raw_phone' => '0812-3456-7890',
            'status' => 'active',
        ]);
    }

    /**
     * Kontak dapat dikaitkan dengan grup melalui tabel pivot.
     */
    public function test_contact_can_be_assigned_to_group(): void
    {
        $group = ContactGroup::create([
            'user_id' => $this->user->id,
            'name' => 'Grup VIP',
        ]);

        $response = $this->actingAs($this->user)->postJson('/contacts', [
            'name' => 'Member VIP 1',
            'phone_number' => '081987654321',
            'group_id' => $group->id,
        ]);

        $response->assertStatus(201);
        $contactId = $response->json('id');

        $this->assertDatabaseHas('contact_group_pivot', [
            'contact_id' => $contactId,
            'group_id' => $group->id,
        ]);
    }

    /**
     * Pembaruan data kontak dan custom_fields JSON.
     */
    public function test_update_contact_and_custom_fields(): void
    {
        $contact = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Nama Sebelum Update',
            'phone_number' => '6281211112222',
            'raw_phone' => '081211112222',
            'status' => 'active',
        ]);

        $customPayload = ['city' => 'Bandung', 'tier' => 'Gold'];

        $response = $this->actingAs($this->user)->putJson("/contacts/{$contact->id}", [
            'name' => 'Nama Setelah Update',
            'custom_fields' => $customPayload,
        ]);

        $response->assertStatus(200);

        $fresh = $contact->fresh();
        $this->assertEquals('Nama Setelah Update', $fresh->name);
        $this->assertEquals($customPayload, $fresh->custom_fields);
    }

    /**
     * Hapus kontak membersihkan baris pivot secara cascade.
     */
    public function test_destroy_contact_removes_pivot(): void
    {
        $group = ContactGroup::create([
            'user_id' => $this->user->id,
            'name' => 'Grup Hapus Uji',
        ]);

        $contact = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Kontak Uji Hapus',
            'phone_number' => '6281299990000',
            'raw_phone' => '081299990000',
            'status' => 'active',
        ]);

        $contact->groups()->attach($group->id);

        $response = $this->actingAs($this->user)->deleteJson("/contacts/{$contact->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('contact_group_pivot', ['contact_id' => $contact->id]);
    }

    /**
     * Batch delete menghapus banyak kontak sekaligus.
     */
    public function test_batch_destroy_contacts(): void
    {
        $c1 = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Batch 1',
            'phone_number' => '62811110001',
            'status' => 'active',
        ]);
        $c2 = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Batch 2',
            'phone_number' => '62811110002',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson('/contacts/batch-delete', [
            'ids' => [$c1->id, $c2->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('contacts', ['id' => $c1->id]);
        $this->assertDatabaseMissing('contacts', ['id' => $c2->id]);
    }

    /**
     * Manajemen grup kontak: buat grup dengan auto slug dan hapus grup.
     */
    public function test_contact_group_crud(): void
    {
        $response = $this->actingAs($this->user)->postJson('/contacts/groups', [
            'name' => 'Pelanggan Setia Toko',
        ]);

        $response->assertStatus(201);
        $groupId = $response->json('group.id');
        $this->assertEquals('pelanggan-setia-toko', $response->json('group.slug'));

        $this->assertDatabaseHas('contact_groups', [
            'id' => $groupId,
            'name' => 'Pelanggan Setia Toko',
            'slug' => 'pelanggan-setia-toko',
        ]);

        // Hapus grup
        $delResponse = $this->actingAs($this->user)->deleteJson("/contacts/groups/{$groupId}");
        $delResponse->assertStatus(200);
        $this->assertDatabaseMissing('contact_groups', ['id' => $groupId]);
    }

    /**
     * Impor kontak dari file CSV dan deduplikasi.
     */
    public function test_import_contacts_csv_and_deduplicate(): void
    {
        // Masukkan data awal
        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Nama Lama',
            'phone_number' => '6281299990001',
            'raw_phone' => '081299990001',
            'status' => 'active',
        ]);

        $csvContent = "name,phone,note\nNama Terupdate,081299990001,Pelanggan VIP\nKontak Baru,081299990002,Member Baru";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('imported_count'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Nama Terupdate',
            'phone_number' => '6281299990001',
        ]);

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Kontak Baru',
            'phone_number' => '6281299990002',
        ]);
    }

    /**
     * Impor kontak dari berkas Excel XLSX menggunakan PhpSpreadsheet.
     */
    public function test_import_contacts_from_excel_xlsx(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Nama');
        $sheet->setCellValue('B1', 'Nomor HP');
        $sheet->setCellValue('C1', 'Catatan');

        $sheet->setCellValue('A2', 'Klien Excel Satu');
        $sheet->setCellValue('B2', '081377778888');
        $sheet->setCellValue('C2', 'Dari Excel');

        $sheet->setCellValue('A3', 'Klien Excel Dua');
        $sheet->setCellValue('B3', '+62 813-9999-0000');
        $sheet->setCellValue('C3', 'Excel M3');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $file = new UploadedFile($tempPath, 'contacts.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('imported_count'));

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Klien Excel Satu',
            'phone_number' => '6281377778888',
        ]);

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'Klien Excel Dua',
            'phone_number' => '6281399990000',
        ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    /**
     * Validasi upload menolak file selain CSV, TXT, XLSX, XLS.
     */
    public function test_import_rejects_non_spreadsheet_files(): void
    {
        $pdf = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $pdf,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['file']);
    }

    /**
     * Ekspor kontak ke streaming CSV.
     */
    public function test_export_contacts_streamed_csv(): void
    {
        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Ekspor Test Person',
            'phone_number' => '6281355556666',
            'raw_phone' => '081355556666',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get('/contacts/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('contacts_export.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Name,Phone Number,Status', $content);
        $this->assertTrue(
            str_contains($content, 'Ekspor Test Person,6281355556666,active') || str_contains($content, '"Ekspor Test Person",6281355556666,active'),
            'Export CSV should contain the exported contact record'
        );
    }

    /**
     * Unduh template CSV impor.
     */
    public function test_download_import_template(): void
    {
        $response = $this->actingAs($this->user)->get('/contacts/template');

        $response->assertStatus(200);
        $this->assertStringContainsString('contacts_template.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Name,Phone Number', $response->getContent());
    }

    /**
     * IDOR: Pengguna tidak dapat mengaitkan grup milik pengguna lain saat membuat kontak (store).
     */
    public function test_idor_cross_tenant_contact_group_cannot_be_associated_on_store(): void
    {
        $otherUser = User::create([
            'name' => 'Other Tenant',
            'email' => 'other_user_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
        ]);

        $otherGroup = ContactGroup::create([
            'user_id' => $otherUser->id,
            'name' => 'Private Group Tenant Lain',
        ]);

        $response = $this->actingAs($this->user)->postJson('/contacts', [
            'name' => 'Kontak Uji IDOR Store',
            'phone_number' => '081299991111',
            'group_id' => $otherGroup->id,
        ]);

        $response->assertStatus(201);
        $contactId = $response->json('id');

        // Pastikan kontak dibuat tetapi TIDAK terhubung ke grup milik user lain di pivot
        $this->assertDatabaseMissing('contact_group_pivot', [
            'contact_id' => $contactId,
            'group_id' => $otherGroup->id,
        ]);
    }

    /**
     * IDOR: Pengguna tidak dapat mengaitkan grup milik pengguna lain saat memperbarui kontak (update).
     */
    public function test_idor_cross_tenant_contact_group_cannot_be_associated_on_update(): void
    {
        $otherUser = User::create([
            'name' => 'Other Tenant 2',
            'email' => 'other_user2_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
        ]);

        $otherGroup = ContactGroup::create([
            'user_id' => $otherUser->id,
            'name' => 'Private Group Tenant 2',
        ]);

        $contact = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Kontak Uji IDOR Update',
            'phone_number' => '6281299992222',
            'raw_phone' => '081299992222',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->putJson("/contacts/{$contact->id}", [
            'group_ids' => [$otherGroup->id],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('contact_group_pivot', [
            'contact_id' => $contact->id,
            'group_id' => $otherGroup->id,
        ]);
    }

    /**
     * IDOR: Pengguna tidak dapat mengaitkan grup milik pengguna lain saat impor berkas (import).
     */
    public function test_idor_cross_tenant_contact_group_cannot_be_associated_on_import(): void
    {
        $otherUser = User::create([
            'name' => 'Other Tenant 3',
            'email' => 'other_user3_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
        ]);

        $otherGroup = ContactGroup::create([
            'user_id' => $otherUser->id,
            'name' => 'Private Group Tenant 3',
        ]);

        $csvContent = "name,phone\nKontak Impor IDOR,081299993333";
        $file = UploadedFile::fake()->createWithContent('contacts_idor.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
            'group_id' => $otherGroup->id,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('user_id', $this->user->id)->where('phone_number', '6281299993333')->first();
        $this->assertNotNull($contact);

        $this->assertDatabaseMissing('contact_group_pivot', [
            'contact_id' => $contact->id,
            'group_id' => $otherGroup->id,
        ]);
    }

    /**
     * Ekspor CSV melakukan sanitasi CSV Formula Injection (DDE) dan auto-enquoting nama yang mengandung koma.
     */
    public function test_export_csv_sanitizes_formula_injection_and_enquotes_commas(): void
    {
        Contact::create([
            'user_id' => $this->user->id,
            'name' => '=CMD(\'calc\')',
            'phone_number' => '6281299994441',
            'raw_phone' => '081299994441',
            'status' => 'active',
        ]);

        Contact::create([
            'user_id' => $this->user->id,
            'name' => '+628123Formula',
            'phone_number' => '6281299994442',
            'raw_phone' => '081299994442',
            'status' => 'active',
        ]);

        Contact::create([
            'user_id' => $this->user->id,
            'name' => '-2*3Negative',
            'phone_number' => '6281299994443',
            'raw_phone' => '081299994443',
            'status' => 'active',
        ]);

        Contact::create([
            'user_id' => $this->user->id,
            'name' => '@SUM(A1:A5)',
            'phone_number' => '6281299994444',
            'raw_phone' => '081299994444',
            'status' => 'active',
        ]);

        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Budi, S.Kom',
            'phone_number' => '6281299994445',
            'raw_phone' => '081299994445',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->get('/contacts/export');
        $response->assertStatus(200);

        $content = $response->getContent();

        // Verifikasi UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Verifikasi sanitasi formula DDE dengan prefix kutip tunggal
        $this->assertStringContainsString("'=CMD('calc')", $content);
        $this->assertStringContainsString("'+628123Formula", $content);
        $this->assertStringContainsString("'-2*3Negative", $content);
        $this->assertStringContainsString("'@SUM(A1:A5)", $content);

        // Verifikasi nama yang mengandung koma diapit tanda petik ganda oleh fputcsv
        $this->assertStringContainsString('"Budi, S.Kom"', $content);
    }

    /**
     * Impor berkas corrupt/rusak menghasilkan JSON error 422 ramah tanpa crash 500.
     */
    public function test_import_corrupted_file_returns_422_friendly_error(): void
    {
        $corruptContent = "This is not a valid zip or excel archive at all!";
        $file = UploadedFile::fake()->createWithContent('corrupt.xlsx', $corruptContent);

        $response = $this->actingAs($this->user)->postJson('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Berkas rusak atau format tidak valid',
        ]);
    }

    /**
     * Impor nomor telepon sangat panjang terpotong dengan aman tanpa SQL truncation error.
     */
    public function test_import_truncates_long_raw_phone_without_sql_truncation_crash(): void
    {
        $veryLongPhone = '08123456789012345678901234567890123456789012345'; // 47 karakter
        $csvContent = "name,phone\nLong Phone Contact,{$veryLongPhone}";
        $file = UploadedFile::fake()->createWithContent('long_phone.csv', $csvContent);

        $response = $this->actingAs($this->user)->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('user_id', $this->user->id)->where('name', 'Long Phone Contact')->first();
        $this->assertNotNull($contact);
        $this->assertLessThanOrEqual(35, strlen($contact->raw_phone));
        $this->assertLessThanOrEqual(25, strlen($contact->phone_number));
    }

    /**
     * Validasi form kontak menolak nomor telepon lebih dari 35 karakter.
     */
    public function test_store_contact_validates_phone_max_35(): void
    {
        $tooLongPhone = str_repeat('9', 36);

        $response = $this->actingAs($this->user)->postJson('/contacts', [
            'name' => 'Kontak Terlalu Panjang',
            'phone_number' => $tooLongPhone,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone_number']);
    }

    /**
     * Parameter pencarian bertipe non-string (misal array) tidak menyebabkan fatal crash.
     */
    public function test_search_query_array_does_not_crash(): void
    {
        $response = $this->actingAs($this->user)->get('/contacts?search[]=bad_input');
        $response->assertStatus(200);
    }
}
