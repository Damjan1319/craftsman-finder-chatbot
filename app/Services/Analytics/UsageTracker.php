<?php

namespace App\Services\Analytics;

use App\Models\UsageEvent;

class UsageTracker
{
    public function log(
        string $platform,
        string $event,
        ?int $externalUserId = null,
        ?string $source = null,
        ?array $meta = null,
    ): void {
        UsageEvent::query()->create([
            'platform' => $platform,
            'external_user_id' => $externalUserId,
            'event' => $event,
            'source' => $source,
            'meta' => $meta,
        ]);
    }
}
