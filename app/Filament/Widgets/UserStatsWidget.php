<?php

namespace App\Filament\Widgets;

use App\Models\TelegramUser;
use App\Models\UsageEvent;
use App\Models\ViberUser;
use App\Services\Analytics\MarketingStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = app(MarketingStats::class)->summary();

        return [
            Stat::make('Ukupno korisnika', (string) $summary['total_users'])
                ->description("Telegram {$summary['telegram_total']} · Viber {$summary['viber_total']}")
                ->color('primary'),
            Stat::make('Aktivni (30 dana)', (string) $summary['active_30_days'])
                ->description('Koristili bot u poslednjih mesec dana')
                ->color('success'),
            Stat::make('Pretrage (30 dana)', (string) $summary['searches_30_days'])
                ->description('Broj pretraga majstora')
                ->color('info'),
        ];
    }
}
