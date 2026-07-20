<?php

namespace App\Filament\Resources\Craftsmen\Pages;

use App\Filament\Resources\Craftsmen\CraftsmanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCraftsmen extends ListRecords
{
    protected static string $resource = CraftsmanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
