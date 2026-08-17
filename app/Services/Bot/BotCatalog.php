<?php

namespace App\Services\Bot;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\CraftsmanServiceCity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BotCatalog
{
    private const TTL_SECONDS = 300;

    /** @return Collection<int, Category> */
    public function categoriesWithCounts(): Collection
    {
        return Cache::remember('bot.catalog.categories', self::TTL_SECONDS, function () {
            return Category::query()
                ->active()
                ->withCount(['craftsmen as active_craftsmen_count' => fn ($query) => $query->active()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->filter(fn (Category $category) => $category->active_craftsmen_count > 0)
                ->values();
        });
    }

    /** @return Collection<int, string> */
    public function citiesForCategory(int $categoryId): Collection
    {
        return Cache::remember("bot.catalog.cities.{$categoryId}", self::TTL_SECONDS, function () use ($categoryId) {
            return $this->resolveCityNamesForCategory($categoryId);
        });
    }

    /** @return Collection<int, object{city: string, craftsmen_count: int}> */
    public function citiesWithCountsForCategory(int $categoryId): Collection
    {
        return Cache::remember("bot.catalog.city_counts.{$categoryId}", self::TTL_SECONDS, function () use ($categoryId) {
            /** @var Collection<int, Craftsman> $craftsmen */
            $craftsmen = Craftsman::query()
                ->with('serviceCities')
                ->where('category_id', $categoryId)
                ->active()
                ->get();

            return $this->resolveCityNamesForCategory($categoryId)->map(fn (string $city) => (object) [
                'city' => $city,
                'craftsmen_count' => $craftsmen->filter(fn (Craftsman $craftsman) => $craftsman->servesCity($city))->count(),
            ]);
        });
    }

    /** @return list<string> */
    public function knownCities(): array
    {
        return Cache::remember('bot.catalog.known_cities', self::TTL_SECONDS, function () {
            $baseCities = Craftsman::query()
                ->active()
                ->distinct()
                ->orderBy('city')
                ->pluck('city');

            $serviceCities = $this->allServiceCityNames();

            return $baseCities
                ->merge($serviceCities)
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }

    public static function flush(): void
    {
        Cache::forget('bot.catalog.categories');
        Cache::forget('bot.catalog.known_cities');
        Cache::forget('admin.craftsman_cities');

        Category::query()->pluck('id')->each(function (int $id): void {
            Cache::forget("bot.catalog.cities.{$id}");
            Cache::forget("bot.catalog.city_counts.{$id}");
        });
    }

    /** @return Collection<int, string> */
    private function resolveCityNamesForCategory(int $categoryId): Collection
    {
        $baseCities = Craftsman::query()
            ->where('category_id', $categoryId)
            ->active()
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        $serviceCities = $this->serviceCityNamesForCategory($categoryId);

        return $baseCities
            ->merge($serviceCities)
            ->unique()
            ->sort()
            ->values();
    }

    /** @return Collection<int, string> */
    private function serviceCityNamesForCategory(int $categoryId): Collection
    {
        if (! $this->hasServiceCitiesTable()) {
            return collect();
        }

        return CraftsmanServiceCity::query()
            ->whereHas('craftsman', fn ($query) => $query
                ->where('category_id', $categoryId)
                ->active())
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    /** @return Collection<int, string> */
    private function allServiceCityNames(): Collection
    {
        if (! $this->hasServiceCitiesTable()) {
            return collect();
        }

        return CraftsmanServiceCity::query()
            ->whereHas('craftsman', fn ($query) => $query->active())
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    private function hasServiceCitiesTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasTable('craftsman_service_cities');
        }

        return $exists;
    }
}
