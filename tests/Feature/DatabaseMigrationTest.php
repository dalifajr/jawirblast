<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    /**
     * Pastikan seluruh 9 tabel relasional utama ada di database.
     */
    public function test_all_9_relational_tables_exist(): void
    {
        $tables = [
            'users',
            'telegram_login_tokens',
            'contacts',
            'contact_groups',
            'contact_group_pivot',
            'whatsapp_sessions',
            'campaigns',
            'campaign_messages',
            'webhook_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} harus ada di database.");
        }
    }

    /**
     * Pastikan kolom-kolom kritis pada tabel users ada.
     */
    public function test_users_table_has_custom_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password', 'telegram_id', 'telegram_username', 'locale'
        ]));
    }

    /**
     * Pastikan kolom-kolom kritis pada tabel contacts ada.
     */
    public function test_contacts_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('contacts', [
            'id', 'user_id', 'name', 'phone_number', 'raw_phone', 'custom_fields', 'status'
        ]));
    }

    /**
     * Pastikan kolom-kolom kritis pada tabel campaigns ada.
     */
    public function test_campaigns_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('campaigns', [
            'id', 'user_id', 'title', 'message_template', 'media_path', 'status',
            'min_delay_seconds', 'max_delay_seconds', 'total_contacts', 'sent_count',
            'delivered_count', 'read_count', 'failed_count'
        ]));
    }

    /**
     * Pastikan kolom-kolom kritis pada tabel campaign_messages ada.
     */
    public function test_campaign_messages_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('campaign_messages', [
            'id', 'campaign_id', 'contact_id', 'phone_number', 'recipient_name',
            'message_text', 'baileys_msg_id', 'status', 'error_message',
            'sent_at', 'delivered_at', 'read_at'
        ]));
    }

    /**
     * Pastikan kolom-kolom kritis pada tabel whatsapp_sessions ada.
     */
    public function test_whatsapp_sessions_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('whatsapp_sessions', [
            'id', 'session_id', 'user_jid', 'status', 'last_connected_at'
        ]));
    }
}
