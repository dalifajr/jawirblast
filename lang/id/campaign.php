<?php

return [
    'title' => 'Kampanye Broadcast',
    'subtitle' => 'Kirim pesan siaran massal dengan kontrol jeda anti-blokir.',
    'create_campaign' => 'Buat Kampanye Baru',
    'campaign_detail' => 'Detail Kampanye',
    'campaign_title' => 'Judul Kampanye',
    'campaign_title_placeholder' => 'Contoh: Promo Diskon Akhir Pekan',

    // Audiens
    'target_recipients' => 'Target Penerima',
    'all_contacts' => 'Seluruh Kontak Database (:count kontak)',
    'filter_by_group' => 'Pilih Berdasarkan Grup Kontak',
    'total_selected' => ':count kontak terpilih',

    // Template Pesan & Spintax
    'message_template' => 'Template Pesan Siaran',
    'message_placeholder' => 'Tulis pesan Anda di sini...',
    'variables_hint' => 'Variabel dinamis: {name} untuk nama penerima, {phone} untuk nomor telepon.',
    'spintax_hint' => 'Gunakan Spintax untuk variasi pesan: {Halo|Hai|Selamat Siang} {Kak|Bro|Gan}.',
    'spintax_test' => 'Uji Acak Spintax',
    'media_attachment' => 'Lampiran Media (Opsional: Gambar JPG/PNG)',

    // Anti-Freeze Pacing Settings
    'pacing_title' => 'Pengaturan Kecepatan & Delay (Anti-Freeze)',
    'min_delay' => 'Jeda Minimum (detik)',
    'max_delay' => 'Jeda Maksimum (detik)',
    'delay_help' => 'Sistem menggunakan antrean delay native per-pesan dengan variasi acak agar pengiriman aman dan tidak membebani prosesor.',
    'estimated_time' => 'Estimasi Durasi Pengiriman: ~:time',

    // Aksi & Kontrol
    'start_broadcast' => 'Mulai Pengiriman Broadcast',
    'pause_broadcast' => 'Jeda Sementara',
    'resume_broadcast' => 'Lanjutkan Pengiriman',
    'cancel_broadcast' => 'Batalkan Kampanye',

    // Status
    'status_draft' => 'Draf',
    'status_scheduled' => 'Terjadwal',
    'status_processing' => 'Sedang Mengirim',
    'status_completed' => 'Selesai',
    'status_paused' => 'Dijeda',
    'status_cancelled' => 'Dibatalkan',
    'status_failed' => 'Gagal',

    // Progres
    'progress_title' => 'Progres Pengiriman',
    'processed_count' => ':sent dari :total pesan telah diproses (:percent%)',
    'confirm_start' => 'Mulai broadcast ke :count kontak sekarang?',
];
