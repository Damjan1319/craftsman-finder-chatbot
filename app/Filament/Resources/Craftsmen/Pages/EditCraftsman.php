<?php

namespace App\Filament\Resources\Craftsmen\Pages;

use App\Filament\Resources\Craftsmen\CraftsmanResource;
use App\Filament\Resources\Craftsmen\Pages\Concerns\SyncsCraftsmanServiceCities;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCraftsman extends EditRecord
{
    use SyncsCraftsmanServiceCities;

    protected static string $resource = CraftsmanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return CraftsmanResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Majstor je uspešno sačuvan')
            ->success();
    }

    protected function afterSave(): void
    {
        $this->syncCraftsmanServiceCities();
    }
}
