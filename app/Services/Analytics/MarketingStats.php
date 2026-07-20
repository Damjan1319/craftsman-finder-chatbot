<?php

namespace App\Services\Analytics;

use App\Models\TelegramUser;
use App\Models\UsageEvent;
use App\Models\ViberUser;
use Illuminate\Support\Collection;

class MarketingStats
{
    public function summary(): array
    {
        $telegramTotal = TelegramUser::query()->count();
        $viberTotal = ViberUser::query()->count();
        $totalUsers = $telegramTotal + $viberTotal;

        $telegramActive30 = TelegramUser::query()
            ->where('last_interaction', '>=', now()->subDays(30))
            ->count();
        $viberActive30 = ViberUser::query()
            ->where('last_interaction', '>=', now()->subDays(30))
            ->count();
        $active30 = $telegramActive30 + $viberActive30;

        $telegramNew30 = TelegramUser::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $viberNew30 = ViberUser::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $new30 = $telegramNew30 + $viberNew30;

        $searches30 = UsageEvent::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('event', [
                UsageEvent::EVENT_VIEW_CATEGORY,
                UsageEvent::EVENT_VIEW_CITY,
                UsageEvent::EVENT_VIEW_CRAFTSMEN,
            ])
            ->count();

        return [
            'total_users' => $totalUsers,
            'telegram_total' => $telegramTotal,
            'viber_total' => $viberTotal,
            'active_30_days' => $active30,
            'new_30_days' => $new30,
            'searches_30_days' => $searches30,
        ];
    }

    /**
     * @return Collection<int, object{source: string, users: int}>
     */
    public function usersBySource(): Collection
    {
        $telegram = TelegramUser::query()
            ->selectRaw("COALESCE(NULLIF(source, ''), 'organicki') as source_label, COUNT(*) as total")
            ->groupBy('source_label')
            ->pluck('total', 'source_label');

        $viber = ViberUser::query()
            ->selectRaw("COALESCE(NULLIF(source, ''), 'organicki') as source_label, COUNT(*) as total")
            ->groupBy('source_label')
            ->pluck('total', 'source_label');

        return $telegram->keys()
            ->merge($viber->keys())
            ->unique()
            ->map(fn (string $source) => (object) [
                'source' => $source,
                'users' => ($telegram[$source] ?? 0) + ($viber[$source] ?? 0),
            ])
            ->sortByDesc('users')
            ->values();
    }

    public function botLink(?string $source = null): string
    {
        $username = config('telegram.bot_username');

        if (blank($username)) {
            return 'https://t.me/';
        }

        $url = "https://t.me/{$username}";

        if (filled($source)) {
            $url .= '?start='.urlencode($source);
        }

        return $url;
    }

    public function webAppUrl(): ?string
    {
        $url = config('telegram.web_app_url');

        if (blank($url) || ! str_starts_with($url, 'https://')) {
            return null;
        }

        return rtrim($url, '/');
    }
}
