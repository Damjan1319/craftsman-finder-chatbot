<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Services\Bot\AboutContent;
use App\Services\Bot\BotCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AppController extends Controller
{
    public function index(BotCatalog $catalog): View
    {
        return view('app.home', [
            'categories' => $catalog->categoriesWithCounts(),
        ]);
    }

    public function category(Category $category, BotCatalog $catalog): View|RedirectResponse
    {
        if (! $category->is_active) {
            return redirect()->route('app.home');
        }

        $cities = $catalog->citiesWithCountsForCategory($category->id);

        if ($cities->isEmpty()) {
            return redirect()
                ->route('app.home')
                ->with('info', "Trenutno nema aktivnih majstora za kategoriju {$category->name}.");
        }

        return view('app.cities', [
            'category' => $category,
            'cities' => $cities,
        ]);
    }

    public function search(Category $category, string $city): View|RedirectResponse
    {
        if (! $category->is_active) {
            return redirect()->route('app.home');
        }

        $city = urldecode($city);

        $craftsmen = Craftsman::query()
            ->with(['category', 'serviceCities'])
            ->forCategoryAndCity($category->id, $city)
            ->get();

        if ($craftsmen->isEmpty()) {
            return redirect()
                ->route('app.category', $category)
                ->with('info', "Nema majstora za {$category->name} u gradu {$city}.");
        }

        return view('app.craftsmen', [
            'category' => $category,
            'city' => $city,
            'craftsmen' => $craftsmen,
            'recommended' => $craftsmen->where('is_premium', true)->values(),
            'others' => $craftsmen->where('is_premium', false)->values(),
        ]);
    }

    public function about(): View
    {
        return view('app.about', [
            'aboutText' => Setting::get('about_text', AboutContent::DEFAULT_TEXT),
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
            'craftsmanEmail' => AboutContent::CRAFTSMAN_EMAIL,
        ]);
    }
}
