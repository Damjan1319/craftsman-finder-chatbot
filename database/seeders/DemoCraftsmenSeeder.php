<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Craftsman;
use Illuminate\Database\Seeder;

/**
 * Opciono — samo za lokalno punjenje demo podataka.
 * Pokreni: php artisan db:seed --class=DemoCraftsmenSeeder
 */
class DemoCraftsmenSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Električar', 'slug' => 'elektricar', 'sort_order' => 1],
            ['name' => 'Vodoinstalater', 'slug' => 'vodoinstalater', 'sort_order' => 2],
            ['name' => 'Keramičar', 'slug' => 'keramicar', 'sort_order' => 3],
            ['name' => 'Moler', 'slug' => 'moler', 'sort_order' => 4],
            ['name' => 'Klimatizer', 'slug' => 'klimatizer', 'sort_order' => 5],
            ['name' => 'Stolar', 'slug' => 'stolar', 'sort_order' => 6],
            ['name' => 'Bravar', 'slug' => 'bravar', 'sort_order' => 7],
            ['name' => 'Automehaničar', 'slug' => 'automehanicar', 'sort_order' => 8],
            ['name' => 'Servis bele tehnike', 'slug' => 'servis-bele-tehnike', 'sort_order' => 9],
            ['name' => 'Fasader', 'slug' => 'fasader', 'sort_order' => 10],
            ['name' => 'Gipsar', 'slug' => 'gipsar', 'sort_order' => 11],
            ['name' => 'Zidar', 'slug' => 'zidar', 'sort_order' => 12],
            ['name' => 'Krovopokrivač', 'slug' => 'krovopokrivac', 'sort_order' => 13],
            ['name' => 'IT servis', 'slug' => 'it-servis', 'sort_order' => 14],
            ['name' => 'Čistač', 'slug' => 'cistac', 'sort_order' => 15],
            ['name' => 'Selidbe', 'slug' => 'selidbe', 'sort_order' => 16],
            ['name' => 'Auto limar', 'slug' => 'auto-limar', 'sort_order' => 17],
            ['name' => 'Staklar', 'slug' => 'staklar', 'sort_order' => 18],
            ['name' => 'Baštovan', 'slug' => 'bastovan', 'sort_order' => 19],
            ['name' => 'Podopolagač', 'slug' => 'podopolagac', 'sort_order' => 20],
        ];

        foreach ($categories as $categoryData) {
            Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                array_merge($categoryData, ['is_active' => true]),
            );
        }

        $validSlugs = array_column($categories, 'slug');

        Category::query()
            ->whereNotIn('slug', $validSlugs)
            ->update(['is_active' => false]);

        $cities = [
            'Beograd', 'Novi Sad', 'Niš', 'Kragujevac', 'Subotica', 'Zrenjanin',
            'Pančevo', 'Čačak', 'Kraljevo', 'Smederevo', 'Leskovac', 'Valjevo',
            'Vršac', 'Šabac', 'Užice', 'Sombor', 'Požarevac', 'Pirot', 'Zaječar',
            'Kikinda', 'Sremska Mitrovica', 'Jagodina', 'Vranje', 'Bor', 'Prokuplje',
            'Loznica', 'Ub',
        ];

        $prefixes = [
            'elektricar' => ['Elektro', 'Struja', 'Volt', 'Power'],
            'vodoinstalater' => ['Aqua', 'Voda', 'Hydro', 'Pipe'],
            'keramicar' => ['Keramika', 'Pločice', 'Tile', 'Fuga'],
            'moler' => ['Boja', 'Color', 'Moler', 'Paint'],
            'klimatizer' => ['Klima', 'Air', 'Cool', 'Termo'],
            'stolar' => ['Stolar', 'Drvo', 'Wood', 'Joinery'],
            'bravar' => ['Brava', 'Metal', 'Ograda', 'Steel'],
            'automehanicar' => ['Auto', 'Motor', 'Drive', 'Garage'],
            'servis-bele-tehnike' => ['Tehnika', 'Servis', 'Home', 'Fix'],
            'fasader' => ['Fasada', 'Demit', 'Termo', 'Facade'],
            'gipsar' => ['Gips', 'Dry', 'Wall', 'Plafon'],
            'zidar' => ['Zidar', 'Gradnja', 'Build', 'Mix'],
            'krovopokrivac' => ['Krov', 'Roof', 'Cover', 'Tile'],
            'it-servis' => ['IT', 'Code', 'Tech', 'Net'],
            'cistac' => ['Clean', 'Čisto', 'Fresh', 'Dom'],
            'selidbe' => ['Selidbe', 'Move', 'Trans', 'Logistika'],
            'auto-limar' => ['Limar', 'AutoFix', 'Body', 'Repair'],
            'staklar' => ['Staklo', 'Glass', 'Prozor', 'Mirror'],
            'bastovan' => ['Bašta', 'Green', 'Vrt', 'Garden'],
            'podopolagac' => ['Pod', 'Parket', 'Floor', 'Laminat'],
        ];

        $bios = [
            'Brzi dolazak i pouzdan rad. Radnim danima 08-18h.',
            'Hitne intervencije po dogovoru. Garancija na rad.',
            'Iskusan majstor sa preko 10 godina prakse.',
            'Kućni i poslovni objekti. Besplatna procena.',
            'Kvalitetan materijal i fer cene.',
            'Preporučeni majstor — veliki broj zadovoljnih klijenata.',
        ];

        $phoneBase = 641000000;

        foreach ($categories as $index => $categoryData) {
            $category = Category::query()->where('slug', $categoryData['slug'])->firstOrFail();
            $slug = $categoryData['slug'];
            $nameParts = $prefixes[$slug] ?? ['Majstor', 'Pro', 'Servis', 'Plus'];

            $cityOffset = $index % count($cities);
            $selectedCities = [];

            for ($i = 0; $i < 6; $i++) {
                $selectedCities[] = $cities[($cityOffset + $i * 3) % count($cities)];
            }

            $selectedCities = array_values(array_unique($selectedCities));
            $craftsmanIndex = 0;

            foreach ($selectedCities as $cityIndex => $city) {
                $count = $cityIndex === 0 ? 2 : 1;

                for ($j = 0; $j < $count; $j++) {
                    $craftsmanIndex++;
                    $namePart = $nameParts[($craftsmanIndex + $j) % count($nameParts)];
                    $phone = '+381'.($phoneBase + ($index * 10) + $craftsmanIndex);
                    $isPremium = ($craftsmanIndex + $index) % 4 === 0;

                    Craftsman::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'name' => "{$namePart} {$city}",
                            'city' => $city,
                        ],
                        [
                            'phone' => $phone,
                            'viber_id' => $isPremium ? $phone : null,
                            'bio' => $bios[($craftsmanIndex + $index) % count($bios)],
                            'status' => 'active',
                            'is_premium' => $isPremium,
                            'sort_order' => $craftsmanIndex,
                            'subscription_expires_at' => now()->addMonths(($craftsmanIndex % 6) + 1),
                        ],
                    );
                }
            }
        }

        Craftsman::query()->updateOrCreate(
            ['name' => 'Istekla Pretplata DOO', 'phone' => '+381677778899'],
            [
                'category_id' => Category::query()->where('slug', 'elektricar')->value('id'),
                'viber_id' => null,
                'bio' => 'Test majstor sa isteklom pretplatom — ne bi trebalo da se vidi u botu.',
                'city' => 'Beograd',
                'status' => 'active',
                'is_premium' => false,
                'sort_order' => 99,
                'subscription_expires_at' => now()->subDay(),
            ],
        );

        $featured = [
            ['slug' => 'elektricar', 'city' => 'Novi Sad', 'name' => 'NS Elektro Tim', 'phone' => '+381621112233'],
            ['slug' => 'elektricar', 'city' => 'Novi Sad', 'name' => 'Volt Novi Sad', 'phone' => '+381621223344'],
            ['slug' => 'vodoinstalater', 'city' => 'Beograd', 'name' => 'Aqua Beograd 24h', 'phone' => '+381621334455'],
            ['slug' => 'it-servis', 'city' => 'Novi Sad', 'name' => 'Code NS', 'phone' => '+381621445566'],
            ['slug' => 'moler', 'city' => 'Niš', 'name' => 'Boja Niš', 'phone' => '+381621556677'],
            ['slug' => 'klimatizer', 'city' => 'Kragujevac', 'name' => 'Klima KG', 'phone' => '+381621667788'],
        ];

        foreach ($featured as $item) {
            $categoryId = Category::query()->where('slug', $item['slug'])->value('id');

            Craftsman::query()->updateOrCreate(
                ['phone' => $item['phone']],
                [
                    'category_id' => $categoryId,
                    'name' => $item['name'],
                    'viber_id' => $item['phone'],
                    'bio' => 'Preporučeni majstor — brz odgovor i proverene reference.',
                    'city' => $item['city'],
                    'status' => 'active',
                    'is_premium' => true,
                    'sort_order' => 0,
                    'subscription_expires_at' => now()->addMonths(6),
                ],
            );
        }
    }
}
