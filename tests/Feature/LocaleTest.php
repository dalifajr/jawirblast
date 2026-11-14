<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_locale_falls_back_to_indonesian(): void
    {
        // Ketika header Accept-Language dari browser bukan bahasa yang didukung (misal bahasa Prancis/Jerman)
        $response = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/');
        $response->assertStatus(200);
        $this->assertEquals('id', App::getLocale());
    }

    public function test_locale_resolves_indonesian_from_accept_language(): void
    {
        $response = $this->withHeader('Accept-Language', 'id-ID,id;q=0.9')->get('/');
        $response->assertStatus(200);
        $this->assertEquals('id', App::getLocale());
    }

    public function test_switch_locale_to_english(): void
    {
        $response = $this->get('/lang/en');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('locale', 'en');
    }

    public function test_switch_locale_to_mandarin(): void
    {
        $response = $this->get('/lang/zh');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'zh');
        $response->assertCookie('locale', 'zh');
    }

    public function test_switch_invalid_locale_fallbacks_to_id(): void
    {
        $response = $this->get('/lang/unsupported_lang');

        $response->assertStatus(302);
        $response->assertSessionHas('locale', 'id');
        $response->assertCookie('locale', 'id');
    }

    public function test_set_locale_middleware_applies_session_locale(): void
    {
        $response = $this->withSession(['locale' => 'zh'])->get('/');

        $response->assertStatus(200);
        $this->assertEquals('zh', App::getLocale());
    }

    public function test_switch_locale_persists_to_authenticated_user(): void
    {
        $user = User::factory()->create([
            'locale' => 'id',
        ]);

        $response = $this->actingAs($user)->get('/lang/en');

        $response->assertStatus(302);
        $this->assertEquals('en', $user->fresh()->locale);
    }
}
