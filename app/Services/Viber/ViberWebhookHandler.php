<?php

namespace App\Services\Viber;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Models\ViberUser;
use App\Services\Bot\BotCopy;
use App\Services\Bot\BotCatalog;
use App\Services\Search\CraftsmanSearchService;
use Illuminate\Support\Collection;

class ViberWebhookHandler
{
    public function __construct(
        private readonly ViberMessageBuilder $messages,
        private readonly CraftsmanSearchService $search,
        private readonly BotCopy $copy,
        private readonly BotCatalog $catalog,
    ) {}

    public function handle(array $payload): array
    {
        return match ($payload['event'] ?? null) {
            'webhook' => ['status' => 0, 'status_message' => 'ok'],
            'conversation_started' => $this->handleConversationStarted($payload),
            'message' => $this->handleMessage($payload),
            'subscribed' => $this->handleConversationStarted($payload),
            default => ['status' => 0],
        };
    }

    private function handleConversationStarted(array $payload): array
    {
        if (isset($payload['user'])) {
            ViberUser::touchFromPayload($payload['user']);
        }

        return $this->showMainMenu();
    }

    private function handleMessage(array $payload): array
    {
        $sender = $payload['sender'] ?? [];
        ViberUser::touchFromPayload($sender);

        $action = $this->resolveAction($payload);

        return match ($action['action'] ?? null) {
            'find_craftsman' => $this->showCategories(),
            'about' => $this->showAbout(),
            'back_main' => $this->showMainMenu(),
            'category' => $this->showCities($action['slug'] ?? ''),
            'city' => $this->showCraftsmen($action['slug'] ?? '', $action['city'] ?? ''),
            default => $this->handleFallbackText($payload['message']['text'] ?? ''),
        };
    }

    private function resolveAction(array $payload): array
    {
        $tracking = data_get($payload, 'message.tracking_data');

        if (filled($tracking)) {
            $decoded = json_decode($tracking, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['action' => $payload['message']['text'] ?? ''];
    }

    private function showMainMenu(): array
    {
        $categoryCount = $this->catalog->categoriesWithCounts()->count();

        return $this->messages->text(
            $this->copy->home(config('viber.welcome_message'), $categoryCount),
            $this->messages->mainKeyboard(),
        );
    }

    private function showCategories(): array
    {
        $categories = $this->catalog->categoriesWithCounts();

        if ($categories->isEmpty()) {
            return $this->messages->text(
                $this->copy->emptyCategories(),
                $this->messages->mainKeyboard(),
            );
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'tracking' => [
                'action' => 'category',
                'slug' => $category->slug,
            ],
        ])->all();

        return $this->messages->text(
            $this->copy->categories($categories->count()),
            $this->messages->optionsKeyboard($options),
        );
    }

    private function showCities(string $slug): array
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            return $this->showCategories();
        }

        $cities = $this->catalog->citiesForCategory($category->id);

        if ($cities->isEmpty()) {
            return $this->messages->text(
                $this->copy->emptyCities($category->name),
                $this->messages->backKeyboard(),
            );
        }

        $options = $cities->map(fn (string $city) => [
            'label' => $city,
            'tracking' => [
                'action' => 'city',
                'slug' => $category->slug,
                'city' => $city,
            ],
        ])->all();

        return $this->messages->text(
            $this->copy->cities($category->name, $cities->count()),
            $this->messages->optionsKeyboard($options),
        );
    }

    private function showCraftsmen(string $slug, string $city): array
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null || blank($city)) {
            return $this->showCategories();
        }

        /** @var Collection<int, Craftsman> $craftsmen */
        $craftsmen = Craftsman::query()
            ->forCategoryAndCity($category->id, $city)
            ->get();

        if ($craftsmen->isEmpty()) {
            return $this->messages->text(
                $this->copy->emptyCraftsmen($category->name, $city),
                $this->messages->backKeyboard(),
            );
        }

        return $this->messages->richMedia(
            $this->messages->craftsmenCarousel(
                $craftsmen,
                $this->copy->craftsmen($category->name, $city, $craftsmen->count()),
            ),
            $this->messages->backKeyboard(),
        );
    }

    private function showAbout(): array
    {
        return $this->messages->text(
            $this->copy->about(
                Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.'),
                Setting::get('contact_phone'),
                Setting::get('contact_email'),
            ),
            $this->messages->mainKeyboard(),
        );
    }

    private function handleFallbackText(string $text): array
    {
        return match ($text) {
            'Tražim majstora', 'Pronađi majstora' => $this->showCategories(),
            'O nama / Kontakt', 'O nama' => $this->showAbout(),
            '← Nazad', 'Početak' => $this->showMainMenu(),
            default => $this->handleFreeText($text),
        };
    }

    private function handleFreeText(string $text): array
    {
        $parsed = $this->search->parse($text);

        if ($parsed !== null) {
            if ($parsed->isComplete()) {
                return $this->showCraftsmen($parsed->category->slug, $parsed->city);
            }

            if ($parsed->category !== null) {
                return $this->showCities($parsed->category->slug);
            }

            if ($parsed->city !== null) {
                return $this->showCategoriesForCity($parsed->city);
            }
        }

        return $this->messages->text(
            $this->copy->notUnderstood(),
            $this->messages->mainKeyboard(),
        );
    }

    private function showCategoriesForCity(string $city): array
    {
        $categories = $this->search->categoriesInCity($city);

        if ($categories->isEmpty()) {
            return $this->messages->text(
                $this->copy->emptyCity($city),
                $this->messages->backKeyboard(),
            );
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'tracking' => [
                'action' => 'category',
                'slug' => $category->slug,
            ],
        ])->all();

        return $this->messages->text(
            $this->copy->categoriesForCity($city, $categories->count()),
            $this->messages->optionsKeyboard($options),
        );
    }
}
