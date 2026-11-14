<?php

return [
    'title' => 'Log Pelacakan Pesan',
    'subtitle' => 'Pemantauan status pengiriman pesan secara real-time via Server-Sent Events (SSE).',

    // Status Pengiriman & Tanda Centang
    'status_pending' => 'Menunggu Antrean',
    'status_sent' => 'Terkirim (Centang 1)',
    'status_delivered' => 'Diterima (Centang 2)',
    'status_read' => 'Dibaca (Centang Biru)',
    'status_failed' => 'Gagal Terkirim',

    'badge_sent_desc' => 'Pesan telah dikirim ke server WhatsApp.',
    'badge_delivered_desc' => 'Pesan telah sampai di perangkat penerima.',
    'badge_read_desc' => 'Pesan telah dibuka dan dibaca oleh penerima.',
    'badge_failed_desc' => 'Pengiriman gagal karena nomor tidak aktif atau gangguan jaringan.',

    // Detail Kolom
    'col_recipient' => 'Penerima',
    'col_phone' => 'Nomor WhatsApp',
    'col_message' => 'Isi Pesan',
    'col_status' => 'Status Pengiriman',
    'col_sent_at' => 'Waktu Kirim',
    'col_delivered_at' => 'Waktu Diterima',
    'col_read_at' => 'Waktu Dibaca',
    'col_error' => 'Keterangan Error',

    // Real-Time & Live Sync
    'sse_connected' => 'Koneksi Live Stream Aktif',
    'sse_reconnecting' => 'Mencoba menghubungkan kembali aliran SSE...',
    'resend_failed_action' => 'Kirim Ulang Pesan Gagal',
    'resend_success' => 'Pesan telah dimasukkan kembali ke dalam antrean.',
];
