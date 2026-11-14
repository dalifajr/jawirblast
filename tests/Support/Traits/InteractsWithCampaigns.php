<?php

namespace Tests\Support\Traits;

use Illuminate\Support\Collection;
use Tests\Support\Helpers\CampaignTestHelper;

trait InteractsWithCampaigns
{
    protected ?CampaignTestHelper $campHelper = null;

    protected function camp(): CampaignTestHelper
    {
        if ($this->campHelper === null) {
            $this->campHelper = new CampaignTestHelper($this);
        }
        return $this->campHelper;
    }

    protected function seedValidContacts(mixed $user, int $count = 5): Collection
    {
        return $this->camp()->seedValidContacts($user, $count);
    }

    protected function seedMixedContacts(mixed $user): Collection
    {
        return $this->camp()->seedMixedContacts($user);
    }

    protected function seedContactGroup(mixed $user, string $name, Collection $contacts): mixed
    {
        return $this->camp()->seedContactGroup($user, $name, $contacts);
    }

    protected function createCampaignWithContacts(
        mixed $user,
        int $contactCount = 5,
        array $attributes = []
    ): mixed {
        $contacts = $this->seedValidContacts($user, $contactCount);
        return $this->camp()->createCampaign($user, $contacts, $attributes);
    }

    protected function processCampaignQueue(mixed $campaign): void
    {
        $this->camp()->processCampaignQueue($campaign);
    }
}
