<?php

namespace Tests\Feature\E2E\Tier3;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C06: Spintax Personalization with Tracking Flow
 * Alur pengujian:
 * 1. Kampanye dengan pola Spintax dan variabel personalisasi {name} dan {phone}
 * 2. Distribusi teks berbeda per penerima tanpa kebocoran sintaks kurung kurawal
 * 3. Eksekusi pengiriman ke 10 nomor
 * 4. Pelacakan status pesan hingga seluruhnya terbaca (read)
 */
class C06_SpintaxPersonalizationWithTrackingTest extends Tier3TestCase
{
    public function test_spintax_resolution_and_message_tracking_cycle(): void
    {
        $user = $this->createTestUser();
        $this->withConnectedWhatsAppSession();

        $names = [
            'Ahmad', 'Budi', 'Charlie', 'Dewi', 'Endang',
            'Fajar', 'Gita', 'Hadi', 'Indra', 'Joko',
        ];

        $contacts = [];
        foreach ($names as $idx => $name) {
            $num = str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            $cid = $this->createContactRecord($user->id, $name, "08125555{$num}");
            $contacts[] = DB::table('contacts')->where('id', $cid)->first();
        }

        $template = '{Halo|Hai|Selamat Pagi} {name}, diskon eksklusif untuk {phone}!';
        $campaignId = $this->createCampaignRecord($user->id, [
            'title' => 'Kampanye Spintax Personalisasi',
            'message_template' => $template,
            'status' => 'processing',
        ]);

        // Generate teks per pesan menggunakan parser Spintax dan variabel
        foreach ($contacts as $contact) {
            $parsedText = $this->parseSpintax($template);
            $parsedText = str_replace(
                ['{name}', '{phone}'],
                [$contact->name, $contact->phone_number],
                $parsedText
            );

            DB::table('campaign_messages')->insert([
                'campaign_id' => $campaignId,
                'contact_id' => $contact->id,
                'phone_number' => $contact->phone_number,
                'recipient_name' => $contact->name,
                'message_text' => $parsedText,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $messages = DB::table('campaign_messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(10, $messages);

        $renderedSalutations = [];
        foreach ($messages as $msg) {
            // Verifikasi tidak ada sisa kurung kurawal atau variabel mentah
            $this->assertStringNotContainsString('{Halo|Hai|Selamat Pagi}', $msg->message_text);
            $this->assertStringNotContainsString('{name}', $msg->message_text);
            $this->assertStringNotContainsString('{phone}', $msg->message_text);

            // Verifikasi nama dan nomor penerima ada di teks
            $this->assertStringContainsString($msg->recipient_name, $msg->message_text);
            $this->assertStringContainsString($msg->phone_number, $msg->message_text);

            if (str_starts_with($msg->message_text, 'Halo')) {
                $renderedSalutations['Halo'] = true;
            } elseif (str_starts_with($msg->message_text, 'Hai')) {
                $renderedSalutations['Hai'] = true;
            } elseif (str_starts_with($msg->message_text, 'Selamat Pagi')) {
                $renderedSalutations['Selamat Pagi'] = true;
            }
        }

        // Minimal 2 variasi salam berbeda harus muncul dari 10 pesan
        $this->assertGreaterThanOrEqual(2, count($renderedSalutations), 'Spintax harus menghasilkan variasi greeting');

        // Simulasikan alur centang untuk seluruh 10 pesan
        foreach ($messages as $msg) {
            $waId = '3EB0SPIN' . strtoupper(Str::random(10));
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
        $this->assertEquals(10, $freshCamp->sent_count);
        $this->assertEquals(10, $freshCamp->read_count);
        $this->assertEquals('completed', $freshCamp->status);
    }
}
