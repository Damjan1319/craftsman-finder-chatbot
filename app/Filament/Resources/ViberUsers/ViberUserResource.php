<?php

namespace App\Filament\Resources\ViberUsers;

use App\Filament\Resources\ViberUsers\Pages\ListViberUsers;
use App\Filament\Resources\ViberUsers\Tables\ViberUsersTable;
use App\Models\ViberUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ViberUserResource extends Resource
{
    protected static ?string $model = ViberUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Viber korisnici';

    protected static ?string $modelLabel = 'Viber korisnik';

    protected static ?string $pluralModelLabel = 'Viber korisnici';

    protected static string|UnitEnum|null $navigationGroup = 'Korisnici';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return ViberUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListViberUsers::route('/'),
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
