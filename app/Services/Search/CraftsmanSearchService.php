<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Craftsman;
use App\Services\Bot\BotCatalog;
use App\Services\Bot\BotCopy;
use Illuminate\Support\Collection;

class CraftsmanSearchService
{
    public function __construct(
        private readonly BotCatalog $catalog,
    ) {}

    /** @var array<string, list<string>> */
    private const CATEGORY_ALIASES = [
        'elektricar' => ['elektricar', 'elektro', 'struja', 'el'],
        'vodoinstalater' => ['vodoinstalater', 'vodoinstalacija', 'vodovod', 'voda'],
        'keramicar' => ['keramicar', 'keramika', 'plocice', 'plocica', 'fugovanje'],
        'moler' => ['moler', 'farbar', 'farbanje', 'krečenje', 'krecenje'],
        'klimatizer' => ['klimatizer', 'klima', 'klimatizacija', 'klima servis'],
        'stolar' => ['stolar', 'stolarija', 'namestaj'],
        'bravar' => ['bravar', 'bravarija', 'kapije', 'ograde'],
        'automehanicar' => ['automehanicar', 'mehanicar', 'auto servis', 'autoservis'],
        'servis-bele-tehnike' => ['servis bele tehnike', 'bela tehnika', 'ves masina', 'frizider'],
        'fasader' => ['fasader', 'fasada', 'demit'],
        'gipsar' => ['gipsar', 'gips', 'suva gradnja'],
        'zidar' => ['zidar', 'zidanje', 'gradjevina'],
        'krovopokrivac' => ['krovopokrivac', 'krov', 'krovopokrivanje'],
        'it-servis' => ['it servis', 'programer', 'racunari', 'racunar', 'laptop'],
        'cistac' => ['cistac', 'ciscenje', 'cistacica', 'ciscenje stana'],
        'selidbe' => ['selidbe', 'selidba', 'transport'],
        'auto-limar' => ['auto limar', 'limar', 'limarija'],
        'staklar' => ['staklar', 'staklo', 'prozori'],
        'bastovan' => ['bastovan', 'vrt', 'odrzavanje dvorista'],
        'podopolagac' => ['podopolagac', 'parket', 'laminat', 'podovi'],
    ];

    /** @var array<string, string> */
    private const CITY_ALIASES = [
        'beograd' => 'Beograd',
        'bg' => 'Beograd',
        'novi sad' => 'Novi Sad',
        'novisad' => 'Novi Sad',
        'ns' => 'Novi Sad',
        'nis' => 'Niš',
        'kragujevac' => 'Kragujevac',
        'kg' => 'Kragujevac',
        'subotica' => 'Subotica',
        'zrenjanin' => 'Zrenjanin',
        'pancevo' => 'Pančevo',
        'cacak' => 'Čačak',
        'kraljevo' => 'Kraljevo',
        'smederevo' => 'Smederevo',
        'leskovac' => 'Leskovac',
        'valjevo' => 'Valjevo',
        'vršac' => 'Vršac',
        'vrsac' => 'Vršac',
        'sabac' => 'Šabac',
        'uzice' => 'Užice',
        'sombor' => 'Sombor',
        'pozarevac' => 'Požarevac',
        'pirot' => 'Pirot',
        'zajecar' => 'Zaječar',
        'kikinda' => 'Kikinda',
        'sremska mitrovica' => 'Sremska Mitrovica',
        'jagodina' => 'Jagodina',
        'vranje' => 'Vranje',
        'bor' => 'Bor',
        'prokuplje' => 'Prokuplje',
        'loznica' => 'Loznica',
        'ub' => 'Ub',
    ];

    public function parse(string $query): ?SearchQuery
    {
        $normalized = $this->normalize($query);

        if ($normalized === '') {
            return null;
        }

        $category = $this->findCategory($normalized);
        $city = $this->findCity($normalized);

        if ($category === null && $city === null) {
            return null;
        }

        return new SearchQuery($category, $city);
    }

    /** @return Collection<int, Craftsman> */
    public function search(string $query): Collection
    {
        $parsed = $this->parse($query);

        if ($parsed === null || ! $parsed->isComplete()) {
            return collect();
        }

        return Craftsman::query()
            ->forCategoryAndCity($parsed->category->id, $parsed->city)
            ->get();
    }

    /** @return Collection<int, Category> */
    public function categoriesInCity(string $city): Collection
    {
        $categoryIds = Craftsman::query()
            ->active()
            ->where('city', $city)
            ->distinct()
            ->pluck('category_id');

        return Category::query()
            ->active()
            ->whereIn('id', $categoryIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return list<string> */
    public function knownCities(): array
    {
        return $this->catalog->knownCities();
    }

    public function searchHint(): string
    {
        return app(BotCopy::class)->searchHint();
    }

    private function findCategory(string $normalizedQuery): ?Category
    {
        $categories = $this->catalog->categoriesWithCounts();

        $candidates = [];

        foreach ($categories as $category) {
            $terms = array_merge(
                [$this->normalize($category->name), $category->slug],
                self::CATEGORY_ALIASES[$category->slug] ?? [],
            );

            foreach ($terms as $term) {
                if ($term === '' || ! $this->containsTerm($normalizedQuery, $term)) {
                    continue;
                }

                $candidates[] = ['category' => $category, 'length' => mb_strlen($term)];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b) => $b['length'] <=> $a['length']);

        return $candidates[0]['category'];
    }

    private function findCity(string $normalizedQuery): ?string
    {
        $candidates = [];

        foreach ($this->cityTerms() as $term => $canonicalCity) {
            if ($this->containsTerm($normalizedQuery, $term)) {
                $candidates[] = ['city' => $canonicalCity, 'length' => mb_strlen($term)];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b) => $b['length'] <=> $a['length']);

        return $candidates[0]['city'];
    }

    /** @return array<string, string> */
    private function cityTerms(): array
    {
        $terms = self::CITY_ALIASES;

        foreach ($this->knownCities() as $city) {
            $terms[$this->normalize($city)] = $city;
        }

        uksort($terms, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        return $terms;
    }

    private function containsTerm(string $haystack, string $term): bool
    {
        if ($term === '') {
            return false;
        }

        if (str_contains($haystack, $term)) {
            return true;
        }

        $pattern = '/\b'.preg_quote($term, '/').'\b/u';

        return (bool) preg_match($pattern, $haystack);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return str_replace(
            ['č', 'ć', 'đ', 'š', 'ž'],
            ['c', 'c', 'd', 's', 'z'],
            $text,
        );
    }
}
