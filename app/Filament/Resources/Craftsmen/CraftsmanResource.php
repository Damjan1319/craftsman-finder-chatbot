<?php

namespace App\Filament\Resources\Craftsmen;

use App\Filament\Resources\Craftsmen\Pages\CreateCraftsman;
use App\Filament\Resources\Craftsmen\Pages\EditCraftsman;
use App\Filament\Resources\Craftsmen\Pages\ListCraftsmen;
use App\Filament\Resources\Craftsmen\Schemas\CraftsmanForm;
use App\Filament\Resources\Craftsmen\Tables\CraftsmenTable;
use App\Models\Craftsman;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CraftsmanResource extends Resource
{
    protected static ?string $model = Craftsman::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Majstori';

    protected static ?string $modelLabel = 'majstor';

    protected static ?string $pluralModelLabel = 'Majstori';

    protected static string|UnitEnum|null $navigationGroup = 'Majstori';

    public static function form(Schema $schema): Schema
    {
        return CraftsmanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CraftsmenTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCraftsmen::route('/'),
            'create' => CreateCraftsman::route('/create'),
            'edit' => EditCraftsman::route('/{record}/edit'),
        ];
    }
}
