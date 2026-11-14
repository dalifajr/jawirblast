<?php

namespace Tests\Support\Traits;

use Tests\Support\Helpers\WhatsAppTestHelper;

trait InteractsWithWhatsApp
{
    protected ?WhatsAppTestHelper $waHelper = null;

    protected function wa(): WhatsAppTestHelper
    {
        if ($this->waHelper === null) {
            $this->waHelper = new WhatsAppTestHelper($this);
        }
        return $this->waHelper;
    }

    protected function withConnectedWhatsAppSession(
        string $sessionId = 'default',
        string $userJid = '628123456789@s.whatsapp.net'
    ): mixed {
        return $this->wa()->setupConnectedSession($sessionId, $userJid);
    }

    protected function withDisconnectedWhatsAppSession(string $sessionId = 'default'): mixed
    {
        return $this->wa()->setupDisconnectedSession($sessionId);
    }

    protected function simulateFullTickCycle(mixed $message, bool $includeRead = true): void
    {
        $this->wa()->simulateFullTickCycle($message, $includeRead);
    }

    protected function assertMessageStatus(mixed $message, string $expectedStatus): void
    {
        $this->wa()->assertMessageStatus($message, $expectedStatus);
    }

    protected function assertCampaignCounters(
        mixed $campaign,
        int $sent,
        int $delivered,
        int $read,
        int $failed
    ): void {
        $this->wa()->assertCampaignCounters($campaign, $sent, $delivered, $read, $failed);
    }
}
