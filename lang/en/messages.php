<?php

return [
    'title' => 'Message Delivery Tracking',
    'subtitle' => 'Real-time delivery state monitoring via Server-Sent Events (SSE).',

    // Status & Ticks
    'status_pending' => 'Pending Queue',
    'status_sent' => 'Sent (Single Tick)',
    'status_delivered' => 'Delivered (Double Tick)',
    'status_read' => 'Read (Blue Tick)',
    'status_failed' => 'Delivery Failed',

    'badge_sent_desc' => 'Message dispatched to WhatsApp network.',
    'badge_delivered_desc' => 'Message successfully delivered to recipient device.',
    'badge_read_desc' => 'Recipient opened and read the message.',
    'badge_failed_desc' => 'Message dispatch failed due to invalid number or socket error.',

    // Columns
    'col_recipient' => 'Recipient Name',
    'col_phone' => 'Phone Number',
    'col_message' => 'Message Content',
    'col_status' => 'Delivery Status',
    'col_sent_at' => 'Sent At',
    'col_delivered_at' => 'Delivered At',
    'col_read_at' => 'Read At',
    'col_error' => 'Error Reason',

    // Real-Time & Live Sync
    'sse_connected' => 'SSE Live Stream Active',
    'sse_reconnecting' => 'Reconnecting live stream...',
    'resend_failed_action' => 'Retry Failed Messages',
    'resend_success' => 'Failed messages re-queued successfully.',
];
