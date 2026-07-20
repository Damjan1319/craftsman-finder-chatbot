<?php

namespace App\Filament\Resources\ViberUsers\Pages;

use App\Filament\Resources\ViberUsers\ViberUserResource;
use Filament\Resources\Pages\ListRecords;

class ListViberUsers extends ListRecords
{
    protected static string $resource = ViberUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
