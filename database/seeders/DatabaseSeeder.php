<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ],
        );

        Setting::set('about_text', 'Majstori Bot povezuje vas sa proverenim zanatlijama u vašem gradu. Brzo, jednostavno, pouzdano.');
        Setting::set('contact_phone', '+381601234567');
        Setting::set('contact_email', 'info@majstori.rs');

        $categories = [
            ['name' => 'Električar', 'slug' => 'elektricar', 'sort_order' => 1],
            ['name' => 'Vodoinstalater', 'slug' => 'vodoinstalater', 'sort_order' => 2],
            ['name' => 'Keramičar', 'slug' => 'keramicar', 'sort_order' => 3],
            ['name' => 'Moler', 'slug' => 'moler', 'sort_order' => 4],
            ['name' => 'Klimatizer', 'slug' => 'klimatizer', 'sort_order' => 5],
        ];

        foreach ($categories as $categoryData) {
            Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                array_merge($categoryData, ['is_active' => true]),
            );
        }

        $elektricar = Category::query()->where('slug', 'elektricar')->firstOrFail();
        $vodoinstalater = Category::query()->where('slug', 'vodoinstalater')->firstOrFail();
        $keramicar = Category::query()->where('slug', 'keramicar')->firstOrFail();

        $craftsmen = [
            [
                'category_id' => $elektricar->id,
                'name' => 'Milan Elektro',
                'phone' => '+381641112233',
                'viber_id' => '+381641112233',
                'bio' => 'Električne instalacije, ugradnja osigurača, popravka kvarova. Radnim danima 08-18h.',
                'city' => 'Beograd',
                'status' => 'active',
                'is_premium' => true,
                'sort_order' => 1,
                'subscription_expires_at' => now()->addMonths(3),
            ],
            [
                'category_id' => $elektricar->id,
                'name' => 'Struja Plus',
                'phone' => '+381642223344',
                'viber_id' => null,
                'bio' => 'Hitne intervencije 24/7. Beograd i okolina.',
                'city' => 'Beograd',
                'status' => 'active',
                'is_premium' => false,
                'sort_order' => 2,
                'subscription_expires_at' => now()->addMonth(),
            ],
            [
                'category_id' => $elektricar->id,
                'name' => 'Elektro Ub',
                'phone' => '+381643334455',
                'viber_id' => '+381643334455',
                'bio' => 'Kućne i poslovne instalacije. Ub i Valjevo.',
                'city' => 'Ub',
                'status' => 'active',
                'is_premium' => true,
                'sort_order' => 1,
                'subscription_expires_at' => now()->addMonths(2),
            ],
            [
                'category_id' => $vodoinstalater->id,
                'name' => 'Aqua Servis',
                'phone' => '+381644445566',
                'viber_id' => '+381644445566',
                'bio' => 'Popravka curenja, zamena sifona, ugradnja bojlera.',
                'city' => 'Beograd',
                'status' => 'active',
                'is_premium' => false,
                'sort_order' => 1,
                'subscription_expires_at' => now()->addMonths(1),
            ],
            [
                'category_id' => $vodoinstalater->id,
                'name' => 'Voda Master Ub',
                'phone' => '+381655556677',
                'viber_id' => null,
                'bio' => 'Vodovodne i kanalizacione instalacije u Ub-u.',
                'city' => 'Ub',
                'status' => 'active',
                'is_premium' => false,
                'sort_order' => 1,
                'subscription_expires_at' => now()->addWeeks(2),
            ],
            [
                'category_id' => $keramicar->id,
                'name' => 'Pločice Pro',
                'phone' => '+381666667788',
                'viber_id' => '+381666667788',
                'bio' => 'Postavljanje keramike, fugovanje, renoviranje kupatila.',
                'city' => 'Novi Sad',
                'status' => 'active',
                'is_premium' => true,
                'sort_order' => 1,
                'subscription_expires_at' => now()->addMonths(6),
            ],
            [
                'category_id' => $elektricar->id,
                'name' => 'Istekla Pretplata DOO',
                'phone' => '+381677778899',
                'viber_id' => null,
                'bio' => 'Test majstor sa isteklom pretplatom — ne bi trebalo da se vidi u botu.',
                'city' => 'Beograd',
                'status' => 'active',
                'is_premium' => false,
                'sort_order' => 99,
                'subscription_expires_at' => now()->subDay(),
            ],
        ];

        foreach ($craftsmen as $craftsmanData) {
            Craftsman::query()->updateOrCreate(
                [
                    'name' => $craftsmanData['name'],
                    'phone' => $craftsmanData['phone'],
                ],
                $craftsmanData,
            );
        }
    }
}
