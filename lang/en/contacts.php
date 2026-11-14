<?php

return [
    'title' => 'Contact Management',
    'subtitle' => 'Manage WhatsApp recipient database and contact groups.',
    'add_contact' => 'Add Contact',
    'edit_contact' => 'Edit Contact',
    'delete_contact' => 'Delete Contact',
    'batch_delete' => 'Delete Selected',
    'import_contacts' => 'Import Contacts',
    'export_contacts' => 'Export Contacts',

    // Fields
    'name' => 'Contact Name',
    'phone_number' => 'WhatsApp Number',
    'raw_phone' => 'Raw Input Number',
    'group' => 'Group',
    'select_group' => 'Select Contact Group',
    'all_groups' => 'All Groups',
    'status' => 'Contact Status',
    'status_active' => 'Active',
    'status_invalid' => 'Invalid',
    'status_unsubscribed' => 'Unsubscribed',

    // E.164 Normalization
    'normalization_notice' => 'Numbers are automatically normalized to E.164 international format (e.g., 0812... converts to 62812...).',
    'invalid_phone_format' => 'Invalid WhatsApp phone number format.',

    // Contact Groups
    'groups_title' => 'Contact Groups',
    'create_group' => 'Create Group',
    'group_name' => 'Group Name',
    'group_slug' => 'Group Slug',
    'total_members' => 'Total Members',

    // Import & Export
    'import_modal_title' => 'Import Contacts Database',
    'import_file_label' => 'Choose File (Excel .xlsx, .csv, or .txt)',
    'import_template_download' => 'Download Sample Import Template',
    'import_instructions' => 'Ensure the first column contains Name and the second column contains Phone Number.',
    'import_processing' => 'Processing contacts import stream...',
    'import_success' => 'Successfully imported :count new contacts.',
    'import_duplicates' => ':count duplicate numbers skipped.',
    'export_all' => 'Export All Contacts',

    // Alerts
    'created_success' => 'New contact created successfully.',
    'updated_success' => 'Contact updated successfully.',
    'deleted_success' => 'Contact removed successfully.',
    'confirm_delete' => 'Are you sure you want to delete this contact?',
    'no_contacts' => 'No contacts found in database.',
];
