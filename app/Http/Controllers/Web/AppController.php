<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AppController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->active()
            ->withCount(['craftsmen as active_craftsmen_count' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->active_craftsmen_count > 0)
            ->values();

        return view('app.home', [
            'categories' => $categories,
        ]);
    }

    public function category(Category $category): View|RedirectResponse
    {
        if (! $category->is_active) {
            return redirect()->route('app.home');
        }

        $cities = Craftsman::query()
            ->where('category_id', $category->id)
            ->active()
            ->selectRaw('city, COUNT(*) as craftsmen_count')
            ->groupBy('city')
            ->orderBy('city')
            ->get();

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
            ->with('category')
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
            'aboutText' => Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.'),
            'contactPhone' => Setting::get('contact_phone'),
            'contactEmail' => Setting::get('contact_email'),
        ]);
    }
}
