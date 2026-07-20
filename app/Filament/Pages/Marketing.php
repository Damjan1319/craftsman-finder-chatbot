<?php

namespace App\Filament\Pages;

use App\Services\Analytics\MarketingStats;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Marketing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Marketing statistika';

    protected static ?string $title = 'Marketing statistika';

    protected static string|UnitEnum|null $navigationGroup = 'Korisnici';

    protected static ?int $navigationSort = 0;

    public string $campaignSource = 'reklama';

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\MarketingStatsOverview::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        $stats = app(MarketingStats::class);
        $summary = $stats->summary();
        $sources = $stats->usersBySource();
        $webAppUrl = $stats->webAppUrl();
        $botUsername = config('telegram.bot_username') ?: 'bot';

        $sourceEntries = $sources->isEmpty()
            ? [
                TextEntry::make('sources_empty')
                    ->hiddenLabel()
                    ->state('Još nema podataka. Pošalji link sa ?start= kodom u reklami.'),
            ]
            : $sources->map(fn (object $row): TextEntry => TextEntry::make('source_'.$row->source)
                ->label($row->source)
                ->state("{$row->users} korisnika")
                ->badge()
            )->all();

        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Link za korisnike')
                            ->description("Korisnik klikne link — otvara chat sa botom. Ne mora da kuca @{$botUsername}.")
                            ->schema([
                                TextEntry::make('bot_link')
                                    ->label('Glavni link')
                                    ->state($stats->botLink())
                                    ->copyable()
                                    ->copyMessage('Link kopiran'),
                                TextInput::make('campaignSource')
                                    ->label('Kod za reklamu (prati izvor)')
                                    ->placeholder('npr. fb_reklama, radio_ub, majstor_milan')
                                    ->live(onBlur: false),
                                TextEntry::make('campaign_link')
                                    ->label('Link za reklamu')
                                    ->state(fn (): string => $stats->botLink($this->campaignSource ?: 'reklama'))
                                    ->copyable()
                                    ->copyMessage('Link kopiran'),
                            ]),
                        Section::make('Mini aplikacija u Telegramu')
                            ->description('Posle prvog otvaranja, korisnik vidi dugme ispod chata — otvara celu aplikaciju.')
                            ->schema([
                                TextEntry::make('web_app_status')
                                    ->label('Status')
                                    ->state($webAppUrl ? 'Aktivno' : 'Nije podešeno')
                                    ->badge()
                                    ->color($webAppUrl ? 'success' : 'warning'),
                                TextEntry::make('web_app_url')
                                    ->label('URL aplikacije')
                                    ->state($webAppUrl ?? 'Postavi TELEGRAM_WEB_APP_URL=https://tvoj-domen.com u .env')
                                    ->copyable(filled($webAppUrl))
                                    ->visible(filled($webAppUrl)),
                            ]),
                    ]),
                Section::make('Korisnici po izvoru')
                    ->description('Korisno kada neko plati reklamu — tačno vidite koliko ljudi je došlo preko njih.')
                    ->schema($sourceEntries),
                Callout::make('Kako reći reklamodavcu')
                    ->description("Imamo {$summary['total_users']} registrovanih korisnika, od toga {$summary['active_30_days']} aktivnih u poslednjih 30 dana. Svaka reklama dobija poseban link — tačno merimo koliko ljudi je došlo preko njih.")
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->color('info'),
            ]);
    }
}
