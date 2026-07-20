<?php

namespace App\Services\Viber;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Models\ViberUser;
use Illuminate\Support\Collection;

class ViberWebhookHandler
{
    public function __construct(
        private readonly ViberMessageBuilder $messages,
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

        return $this->messages->text(
            config('viber.welcome_message'),
            $this->messages->mainKeyboard(),
        );
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
        return $this->messages->text(
            'Izaberite opciju:',
            $this->messages->mainKeyboard(),
        );
    }

    private function showCategories(): array
    {
        $categories = Category::query()
            ->active()
            ->withCount(['craftsmen as active_craftsmen_count' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->active_craftsmen_count > 0)
            ->values();

        if ($categories->isEmpty()) {
            return $this->messages->text(
                'Trenutno nema dostupnih kategorija. Pokušajte kasnije.',
                $this->messages->mainKeyboard(),
            );
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->labelWithCount(),
            'tracking' => [
                'action' => 'category',
                'slug' => $category->slug,
            ],
        ])->all();

        return $this->messages->text(
            'Izaberite kategoriju majstora:',
            $this->messages->optionsKeyboard($options),
        );
    }

    private function showCities(string $slug): array
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            return $this->showCategories();
        }

        $cities = Craftsman::query()
            ->where('category_id', $category->id)
            ->active()
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        if ($cities->isEmpty()) {
            return $this->messages->text(
                "Trenutno nema aktivnih majstora za kategoriju {$category->name}.",
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
            "Izaberite grad za kategoriju {$category->name}:",
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
                "Nema aktivnih majstora za {$category->name} u gradu {$city}.",
                $this->messages->backKeyboard(),
            );
        }

        return $this->messages->richMedia(
            $this->messages->craftsmenCarousel($craftsmen),
            $this->messages->backKeyboard(),
        );
    }

    private function showAbout(): array
    {
        $about = Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.');
        $phone = Setting::get('contact_phone');
        $email = Setting::get('contact_email');

        $lines = [$about];

        if (filled($phone)) {
            $lines[] = "Telefon: {$phone}";
        }

        if (filled($email)) {
            $lines[] = "Email: {$email}";
        }

        return $this->messages->text(
            implode("\n\n", $lines),
            $this->messages->mainKeyboard(),
        );
    }

    private function handleFallbackText(string $text): array
    {
        return match ($text) {
            'Tražim majstora' => $this->showCategories(),
            'O nama / Kontakt' => $this->showAbout(),
            '← Nazad' => $this->showMainMenu(),
            default => $this->messages->text(
                'Nisam razumeo. Koristite dugmad ispod.',
                $this->messages->mainKeyboard(),
            ),
        };
    }
}
