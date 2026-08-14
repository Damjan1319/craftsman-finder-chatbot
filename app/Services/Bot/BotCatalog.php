<?php

namespace App\Services\Bot;

use App\Models\Category;
use App\Models\Craftsman;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
            return Craftsman::query()
                ->where('category_id', $categoryId)
                ->active()
                ->select('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city');
        });
    }

    /** @return list<string> */
    public function knownCities(): array
    {
        return Cache::remember('bot.catalog.known_cities', self::TTL_SECONDS, function () {
            return Craftsman::query()
                ->active()
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->all();
        });
    }

    public static function flush(): void
    {
        Cache::forget('bot.catalog.categories');
        Cache::forget('bot.catalog.known_cities');

        Category::query()->pluck('id')->each(
            fn (int $id) => Cache::forget("bot.catalog.cities.{$id}"),
        );
    }
}
