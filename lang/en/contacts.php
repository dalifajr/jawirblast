<?php

return [
    'title' => 'Contact Management',
    'subtitle' => 'Manage recipient databases, customer WhatsApp numbers, and audience segments.',
    'add_contact' => 'Add Contact',
    'edit_contact' => 'Edit Contact',
    'delete_contact' => 'Delete Contact',
    'batch_delete' => 'Delete Selected',
    'import_contacts' => 'Import Contacts',
    'export_contacts' => 'Export Contacts',

    // Fields
    'name' => 'Contact Name',
    'phone_number' => 'WhatsApp Number',
    'raw_phone' => 'Original Number',
    'group' => 'Group',
    'select_group' => 'Select Contact Group',
    'all_groups' => 'All Groups',
    'all_status' => 'All Statuses',
    'status' => 'Contact Status',
    'status_active' => 'Active',
    'status_invalid' => 'Invalid',
    'status_unsubscribed' => 'Unsubscribed',
    'search_placeholder' => 'Search name or WhatsApp number...',

    // Summary Statistics
    'stat_total' => 'Total Contacts',
    'stat_valid' => 'Valid E.164 Contacts (Active)',
    'stat_invalid' => 'Invalid Numbers',
    'stat_unsubscribed' => 'Unsubscribed',

    // E.164 Normalization
    'normalization_notice' => 'Numbers are normalized to international E.164 format automatically (e.g., 0812... becomes 62812...).',
    'invalid_phone_format' => 'Invalid WhatsApp phone number format.',

    // Contact Groups
    'groups_title' => 'Contact Groups',
    'create_group' => 'Create New Group',
    'back_to_contacts' => 'Back to Contacts',
    'save_group' => 'Save Group',
    'group_name' => 'Group Name',
    'group_name_placeholder' => 'e.g., VIP Customers, Webinar Leads',
    'group_slug' => 'Group Slug',
    'total_members' => 'Total Members',
    'members_count' => ':count Members',
    'no_group_assigned' => 'No Group Assigned',
    'assign_to_group' => 'Assign to Group (Optional)',
    'confirm_delete_group' => 'Are you sure you want to delete this group? Contacts inside will not be deleted.',
    'no_groups' => 'No contact groups found. Create a new group above.',

    // Import & Export
    'import_modal_title' => 'Import Contact Database',
    'import_file_label' => 'Select File (Excel .xlsx, .csv, or .txt)',
    'import_template_download' => 'Download Sample Import Template',
    'import_instructions' => 'Ensure the first column contains Names and the second contains WhatsApp Numbers.',
    'import_processing' => 'Processing contact import...',
    'import_success' => 'Successfully imported :count new contacts.',
    'import_duplicates' => ':count duplicate contacts skipped.',
    'export_all' => 'Export All Contacts',
    'start_import' => 'Start Import',
    'update_btn' => 'Update',

    // Notifications
    'created_success' => 'New contact saved successfully.',
    'updated_success' => 'Contact updated successfully.',
    'deleted_success' => 'Contact deleted successfully.',
    'confirm_delete' => 'Are you sure you want to delete this contact?',
    'confirm_batch_delete' => 'Are you sure you want to delete selected contacts?',
    'no_contacts' => 'No contacts found in the database.',
];
