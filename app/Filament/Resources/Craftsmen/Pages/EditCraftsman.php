<?php

namespace App\Filament\Resources\Craftsmen\Pages;

use App\Filament\Resources\Craftsmen\CraftsmanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCraftsman extends EditRecord
{
    protected static string $resource = CraftsmanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
