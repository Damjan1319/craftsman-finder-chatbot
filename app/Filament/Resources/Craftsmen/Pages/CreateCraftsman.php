<?php

namespace App\Filament\Resources\Craftsmen\Pages;

use App\Filament\Resources\Craftsmen\CraftsmanResource;
use App\Filament\Resources\Craftsmen\Pages\Concerns\SyncsCraftsmanServiceCities;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCraftsman extends CreateRecord
{
    use SyncsCraftsmanServiceCities;

    protected static string $resource = CraftsmanResource::class;

    protected function getRedirectUrl(): string
    {
        return CraftsmanResource::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Majstor je uspešno dodat')
            ->success();
    }

    protected function afterCreate(): void
    {
        $this->syncCraftsmanServiceCities();
    }
}
