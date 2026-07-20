<?php

namespace App\Filament\Resources\TelegramUsers;

use App\Filament\Resources\TelegramUsers\Pages\ListTelegramUsers;
use App\Filament\Resources\TelegramUsers\Tables\TelegramUsersTable;
use App\Models\TelegramUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelegramUserResource extends Resource
{
    protected static ?string $model = TelegramUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Telegram korisnici';

    protected static ?string $modelLabel = 'Telegram korisnik';

    protected static ?string $pluralModelLabel = 'Telegram korisnici';

    protected static string|UnitEnum|null $navigationGroup = 'Korisnici';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return TelegramUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramUsers::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
