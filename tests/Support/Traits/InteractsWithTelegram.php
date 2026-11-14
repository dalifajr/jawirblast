<?php

namespace Tests\Support\Traits;

use Tests\Support\Helpers\TelegramTestHelper;

trait InteractsWithTelegram
{
    protected ?TelegramTestHelper $teleHelper = null;

    protected function tele(): TelegramTestHelper
    {
        if ($this->teleHelper === null) {
            $this->teleHelper = new TelegramTestHelper($this);
        }
        return $this->teleHelper;
    }

    protected function fakeTelegramApi(): void
    {
        $this->tele()->fakeTelegramApi();
    }

    protected function createTelegramLoginToken(mixed $user = null): mixed
    {
        return $this->tele()->generateLoginToken($user);
    }

    protected function simulateTelegramDeepLink(
        string $token,
        int $telegramId = 123456789,
        string $username = 'jawir_user',
        string $firstName = 'Jawir'
    ): void {
        $this->tele()->simulateDeepLinkStart($token, $telegramId, $username, $firstName);
    }

    protected function assertTelegramAdminNotificationSent(string $needle): void
    {
        $this->tele()->assertAdminNotificationSent($needle);
    }

    protected function assertTelegramNotificationCount(int $expectedCount): void
    {
        $this->tele()->assertNotificationCount($expectedCount);
    }
}
