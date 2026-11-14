<?php

return [
    'title' => 'Broadcast Campaigns',
    'subtitle' => 'Dispatch bulk WhatsApp campaigns with staggered anti-freeze pacing.',
    'create_campaign' => 'Create New Campaign',
    'campaign_detail' => 'Campaign Details',
    'campaign_title' => 'Campaign Title',
    'campaign_title_placeholder' => 'e.g., Weekend Special Promo',

    // Target Audience
    'target_recipients' => 'Target Audience',
    'all_contacts' => 'All Database Contacts (:count contacts)',
    'filter_by_group' => 'Filter by Contact Group',
    'by_group' => 'By Contact Group',
    'by_contacts' => 'Select Specific Contacts',
    'total_selected' => ':count recipients selected',

    // Message Template & Spintax
    'message_template' => 'Message Template',
    'message_placeholder' => 'Compose your broadcast text here...',
    'variables_hint' => 'Available tags: {name} for recipient name, {phone} for phone number.',
    'spintax_hint' => 'Use Spintax for text randomization: {Hello|Hi|Greetings} {Friend|Customer}.',
    'spintax_hint_long' => 'Use curly brackets with pipes like {Hello|Hi|Greetings} to dynamically vary message contents and evade WhatsApp spam filters.',
    'spintax_test' => 'Preview Spintax Randomization',
    'media_attachment' => 'Media Attachment (Optional: Image JPG/PNG)',
    'spintax_calculator' => 'Spintax Calculator',
    'estimated_variations' => 'Estimated Unique Variations',
    'message_ready' => 'Message Ready',
    'preview_random_sample' => 'Preview Random Variation',

    // WhatsApp Session & Warning
    'wa_not_connected' => 'WhatsApp Session Not Connected',
    'connect_wa_btn' => 'Connect WhatsApp',

    // Anti-Freeze Pacing Settings
    'pacing_title' => 'Pacing & Anti-Freeze Queue Delay',
    'min_delay' => 'Minimum Delay (seconds)',
    'max_delay' => 'Maximum Delay (seconds)',
    'delay_help' => 'Messages are scheduled with randomized delays via native worker queues to prevent freezing and ban triggers.',
    'estimated_time' => 'Estimated Dispatch Time: ~:time',

    // Actions & Controls
    'start_broadcast' => 'Start Broadcast',
    'pause_broadcast' => 'Pause Broadcast',
    'resume_broadcast' => 'Resume Broadcast',
    'cancel_broadcast' => 'Cancel Campaign',
    'save_draft' => 'Save as Draft',
    'send_now' => 'Send Broadcast Now',
    'all_status' => 'All Statuses',
    'filter' => 'Filter',
    'search_placeholder' => 'Search campaign name...',

    // Summary Statistics
    'total_campaigns' => 'Total Campaigns',
    'messages_sent' => 'Messages Sent',
    'messages_delivered' => 'Messages Delivered',
    'messages_read' => 'Messages Read',
    'messages_failed' => 'Failed Messages',

    // Status
    'status_draft' => 'Draft',
    'status_scheduled' => 'Scheduled',
    'status_processing' => 'Processing',
    'status_completed' => 'Completed',
    'status_paused' => 'Paused',
    'status_cancelled' => 'Cancelled',
    'status_failed' => 'Failed',

    // Progress
    'progress_title' => 'Dispatch Progress',
    'processed_count' => ':sent of :total messages processed (:percent%)',
    'confirm_start' => 'Start broadcasting to :count contacts now?',
];
