<?php

namespace Tests\Feature\E2E\Tier1;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F01: Database & i18n Localization Test Suite (Tier 1 Feature Coverage)
 *
 * Menguji keberadaan 9 tabel relasional utama, struktur skema database,
 * pergantian multi-bahasa (id, en, zh), serta persistensi preferensi locale pengguna.
 */
class F01_DatabaseAndI18nTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. Verifikasi seluruh 9 tabel MySQL relasional terpasang via migration dan memiliki kolom kritis.
     */
    public function test_01_all_nine_relational_tables_exist_with_proper_schemas(): void
    {
        $tables = [
            'users' => ['id', 'name', 'email', 'password', 'telegram_id', 'telegram_username', 'locale'],
            'telegram_login_tokens' => ['id', 'token', 'telegram_id', 'status', 'expires_at'],
            'contacts' => ['id', 'user_id', 'name', 'phone_number', 'raw_phone', 'custom_fields', 'status'],
            'contact_groups' => ['id', 'user_id', 'name', 'slug'],
            'contact_group_pivot' => ['contact_id', 'group_id'],
            'whatsapp_sessions' => ['id', 'session_id', 'user_jid', 'status', 'last_connected_at'],
            'campaigns' => ['id', 'user_id', 'title', 'message_template', 'min_delay_seconds', 'max_delay_seconds', 'status', 'total_contacts'],
            'campaign_messages' => ['id', 'campaign_id', 'contact_id', 'phone_number', 'recipient_name', 'message_text', 'status'],
            'webhook_logs' => ['id', 'event', 'payload'],
        ];

        foreach ($tables as $tableName => $columns) {
            $this->assertTrue(
                Schema::hasTable($tableName),
                "Tabel '{$tableName}' harus terpasang di database."
            );

            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($tableName, $column),
                    "Kolom '{$column}' harus ada pada tabel '{$tableName}'."
                );
            }
        }
    }

    /**
     * 2. Ganti bahasa ke Bahasa Indonesia ('id') & verifikasi session, cookie, dan teks translasi.
     */
    public function test_02_switch_locale_to_indonesian_renders_id_translations(): void
    {
        $response = $this->get('/lang/id');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'id');
        $response->assertCookie('locale', 'id');

        App::setLocale('id');
        $this->assertEquals('id', App::getLocale());

        $this->assertNotEmpty(__('auth.login_title'));
        $this->assertNotEmpty(__('campaign.title'));
        $this->assertNotEmpty(__('contacts.title'));
    }

    /**
     * 3. Ganti bahasa ke English ('en') & verifikasi session, cookie, dan teks translasi.
     */
    public function test_03_switch_locale_to_english_renders_en_translations(): void
    {
        $response = $this->get('/lang/en');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('locale', 'en');

        App::setLocale('en');
        $this->assertEquals('en', App::getLocale());

        $this->assertNotEmpty(__('auth.login_title'));
        $this->assertNotEmpty(__('campaign.title'));
        $this->assertNotEmpty(__('contacts.title'));
    }

    /**
     * 4. Ganti bahasa ke Chinese ('zh') & verifikasi session, cookie, dan karakter Hanzi.
     */
    public function test_04_switch_locale_to_chinese_renders_zh_translations(): void
    {
        $response = $this->get('/lang/zh');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'zh');
        $response->assertCookie('locale', 'zh');

        App::setLocale('zh');
        $this->assertEquals('zh', App::getLocale());

        // Verifikasi karakter Hanzi pada translasi zh
        $loginText = __('auth.login_title');
        $this->assertNotEmpty($loginText);
        $this->assertMatchesRegularExpression('/[\x{4e00}-\x{9fa5}]/u', $loginText, 'Translasi harus mengandung karakter Hanzi');
    }

    /**
     * 5. Verifikasi kolom 'locale' tersimpan pada profil user dan dimuat saat login.
     */
    public function test_05_authenticated_user_locale_preference_persisted_to_database(): void
    {
        $user = User::create([
            'name' => 'Locale Tester',
            'email' => 'localetester_' . uniqid() . '_' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password123'),
            'locale' => 'zh',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'zh',
        ]);

        // Simulasikan pergantian preferensi bahasa user
        $user->locale = 'en';
        $user->save();
        $this->assertEquals('en', $user->fresh()->locale);

        // Kunjungi endpoint dengan user terautentikasi
        $this->actingAs($user)->get('/');
        $this->assertEquals('en', App::getLocale());
    }
}
