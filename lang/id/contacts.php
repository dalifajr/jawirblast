<?php

return [
    'title' => 'Manajemen Kontak',
    'subtitle' => 'Kelola database nomor WhatsApp pelanggan dan grup penerima.',
    'add_contact' => 'Tambah Kontak',
    'edit_contact' => 'Edit Kontak',
    'delete_contact' => 'Hapus Kontak',
    'batch_delete' => 'Hapus Terpilih',
    'import_contacts' => 'Impor Kontak',
    'export_contacts' => 'Ekspor Kontak',

    // Fields
    'name' => 'Nama Kontak',
    'phone_number' => 'Nomor WhatsApp',
    'raw_phone' => 'Nomor Asli',
    'group' => 'Grup',
    'select_group' => 'Pilih Grup Kontak',
    'all_groups' => 'Semua Grup',
    'status' => 'Status Kontak',
    'status_active' => 'Aktif',
    'status_invalid' => 'Tidak Valid',
    'status_unsubscribed' => 'Berhenti Langganan',

    // Normalisasi E.164
    'normalization_notice' => 'Nomor otomatis dinormalisasi ke format internasional E.164 (contoh: 0812... menjadi 62812...).',
    'invalid_phone_format' => 'Format nomor WhatsApp tidak valid.',

    // Grup Kontak
    'groups_title' => 'Grup Kontak',
    'create_group' => 'Buat Grup Baru',
    'group_name' => 'Nama Grup',
    'group_slug' => 'Slug Grup',
    'total_members' => 'Jumlah Anggota',

    // Impor & Ekspor
    'import_modal_title' => 'Impor Database Kontak',
    'import_file_label' => 'Pilih File (Excel .xlsx, .csv, atau .txt)',
    'import_template_download' => 'Unduh Contoh Template Impor',
    'import_instructions' => 'Pastikan kolom pertama berisi Nama dan kolom kedua berisi Nomor WhatsApp.',
    'import_processing' => 'Sedang memproses impor kontak...',
    'import_success' => 'Berhasil mengimpor :count kontak baru.',
    'import_duplicates' => ':count kontak duplikat dilewati.',
    'export_all' => 'Ekspor Semua Kontak',

    // Notifikasi
    'created_success' => 'Kontak baru berhasil disimpan.',
    'updated_success' => 'Data kontak berhasil diperbarui.',
    'deleted_success' => 'Kontak berhasil dihapus.',
    'confirm_delete' => 'Apakah Anda yakin ingin menghapus kontak ini?',
    'no_contacts' => 'Belum ada kontak terdaftar dalam database.',
];
