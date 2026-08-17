<?php

namespace App\Services\Analytics;

use App\Models\UsageEvent;
use Illuminate\Support\Facades\Log;

class UsageTracker
{
    public function log(
        string $platform,
        string $event,
        ?int $externalUserId = null,
        ?string $source = null,
        ?array $meta = null,
    ): void {
        try {
            UsageEvent::query()->create([
                'platform' => $platform,
                'external_user_id' => $externalUserId,
                'event' => $event,
                'source' => $source,
                'meta' => $meta,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('UsageTracker failed', [
                'platform' => $platform,
                'event' => $event,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
