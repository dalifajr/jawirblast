<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C09: Multilingual Campaign Creation and Dispatch Flow
 * Alur pengujian:
 * 1. Pergantian preferensi bahasa pengguna ke Chinese (zh) dan English (en)
 * 2. Pembuatan kampanye dengan teks multi-byte Hanzi (UTF-8)
 * 3. Verifikasi integritas karakter teks di basis data tanpa korupsi encoding
 * 4. Eksekusi siaran dan pelacakan status
 */
class C09_MultilingualCampaignCreationAndDispatchTest extends Tier3TestCase
{
    public function test_multilingual_chinese_campaign_creation_and_dispatch(): void
    {
        $user = $this->createTestUser(['locale' => 'zh']);
        $this->actingAs($user);
        $this->withConnectedWhatsAppSession();

        // 1. Simulasikan pergantian bahasa ke Chinese melalui endpoint lokalisasi
        $resLang = $this->get('/lang/zh');
        $resLang->assertRedirect();
        $this->assertEquals('zh', session('locale'));

        // 2. Buat kontak dengan nama Hanzi
        $cid1 = $this->createContactRecord($user->id, '王伟 (Wang Wei)', '08128888001');
        $cid2 = $this->createContactRecord($user->id, '李娜 (Li Na)', '08128888002');

        $contacts = DB::table('contacts')->whereIn('id', [$cid1, $cid2])->get();

        // 3. Buat kampanye dengan judul dan isi pesan beraksara Hanzi
        $chineseTitle = '双十一促销特惠活动';
        $chineseTemplate = '尊敬的 {name}，双十一特惠已开启！专享优惠适用于您的手机号 {phone}。';

        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => $chineseTitle,
            'message_template' => $chineseTemplate,
            'status' => 'processing',
        ], $contacts->all());

        // 4. Verifikasi teks di database tersimpan utuh tanpa kerusakan karakter encoding UTF-8
        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals($chineseTitle, $campaign->title);

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(2, $messages);

        foreach ($messages as $msg) {
            $this->assertStringContainsString('双十一特惠已开启', $msg->message_text);
            $this->assertStringContainsString($msg->recipient_name, $msg->message_text);
            $this->assertStringContainsString($msg->phone_number, $msg->message_text);
        }

        // 5. Eksekusi pengiriman dan webhook
        foreach ($messages as $msg) {
            $waId = '3EB0ZH_' . strtoupper(Str::random(8));
            DB::table('campaign_messages')->where('id', $msg->id)->update([
                'baileys_msg_id' => $waId,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->postJson('/api/wa/webhook', [
                'event' => 'message.update',
                'wa_message_id' => $waId,
                'remote_jid' => "{$msg->phone_number}@s.whatsapp.net",
                'status' => 'READ',
                'status_code' => 4,
            ], ['X-Service-Key' => $this->serviceKey])->assertOk();
        }

        $freshCamp = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(2, $freshCamp->sent_count);
        $this->assertEquals(2, $freshCamp->read_count);
        $this->assertEquals('completed', $freshCamp->status);

        // 6. Beralih ke bahasa Inggris (en)
        $this->get('/lang/en')->assertRedirect();
        $this->assertEquals('en', session('locale'));
    }
}
