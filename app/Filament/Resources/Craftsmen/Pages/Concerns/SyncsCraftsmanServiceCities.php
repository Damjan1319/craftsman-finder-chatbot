<?php

namespace App\Filament\Resources\Craftsmen\Pages\Concerns;

trait SyncsCraftsmanServiceCities
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['extra_cities'] = $this->record->serviceCities()->pluck('city')->all();

        return $data;
    }

    protected function syncCraftsmanServiceCities(): void
    {
        $cities = collect($this->form->getState()['extra_cities'] ?? [])
            ->map(fn ($city) => trim((string) $city))
            ->filter()
            ->unique()
            ->values();

        $this->record->serviceCities()->whereNotIn('city', $cities)->delete();

        foreach ($cities as $city) {
            $this->record->serviceCities()->firstOrCreate(['city' => $city]);
        }
    }
}
