<?php

namespace App\Services\Meta;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\InstagramUser;
use App\Models\MessengerUser;
use App\Models\Setting;
use App\Models\UsageEvent;
use App\Services\Analytics\UsageTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MetaMessagingHandler
{
    public function __construct(
        private readonly MetaApiClient $api,
        private readonly MetaPayloadBuilder $payload,
        private readonly MetaMessageFormatter $messages,
        private readonly UsageTracker $tracker,
    ) {}

    public function handleWebhook(array $body): void
    {
        $object = $body['object'] ?? null;

        $platform = match ($object) {
            'page' => 'messenger',
            'instagram' => 'instagram',
            default => null,
        };

        if ($platform === null) {
            return;
        }

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $this->handleMessagingEvent($event, $platform);
            }
        }
    }

    private function handleMessagingEvent(array $event, string $platform): void
    {
        $senderId = (string) ($event['sender']['id'] ?? '');

        if ($senderId === '') {
            return;
        }

        if (isset($event['postback'])) {
            $this->handlePayload($senderId, (string) ($event['postback']['payload'] ?? ''), $platform);

            return;
        }

        if (! isset($event['message'])) {
            return;
        }

        if (isset($event['message']['is_echo']) && $event['message']['is_echo']) {
            return;
        }

        if (isset($event['message']['quick_reply']['payload'])) {
            $this->handlePayload($senderId, (string) $event['message']['quick_reply']['payload'], $platform);

            return;
        }

        $text = trim((string) ($event['message']['text'] ?? ''));

        if ($text === '') {
            return;
        }

        $this->handleText($senderId, $text, $platform);
    }

    private function handleText(string $senderId, string $text, string $platform): void
    {
        $normalized = mb_strtolower($text);

        if (in_array($normalized, ['start', '/start', 'početak', 'pocetak', 'menu'], true)) {
            $user = $this->touchUser($senderId, $platform);
            $this->logStart($platform, $senderId, $user);
            $this->showMainMenu($senderId, $platform);

            return;
        }

        $this->touchUser($senderId, $platform);

        if (in_array($text, [MetaPayloadBuilder::BTN_SERVICE, 'Izaberi majstora', 'Tražim majstora'], true)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_HOME], true)) {
            $this->showMainMenu($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_ABOUT, 'Pomoć', 'Pomoc'], true)) {
            $this->showAbout($senderId, $platform);

            return;
        }

        $this->showMainMenu($senderId, $platform);
    }

    private function handlePayload(string $senderId, string $payload, string $platform): void
    {
        $this->touchUser($senderId, $platform);

        if (str_starts_with($payload, 'act:')) {
            match (substr($payload, 4)) {
                'find' => $this->showCategories($senderId, $platform),
                'about' => $this->showAbout($senderId, $platform),
                'main' => $this->showMainMenu($senderId, $platform),
                default => $this->showMainMenu($senderId, $platform),
            };

            return;
        }

        if (str_starts_with($payload, 'cat:')) {
            $this->showCities($senderId, substr($payload, 4), $platform);

            return;
        }

        if (str_starts_with($payload, 'city:')) {
            $parsed = $this->payload->parseCityCallback($payload);

            if ($parsed !== null) {
                $this->showCraftsmen($senderId, $parsed['slug'], $parsed['city'], $platform);
            }

            return;
        }

        $this->showMainMenu($senderId, $platform);
    }

    private function showMainMenu(string $senderId, string $platform): void
    {
        $welcome = $platform === 'instagram'
            ? config('instagram.welcome_message')
            : config('messenger.welcome_message');

        $this->api->sendButtonTemplate(
            $senderId,
            $this->messages->home($welcome),
            $this->payload->mainMenuButtons(),
            $platform,
        );
    }

    private function showCategories(string $senderId, string $platform): void
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
            $this->api->sendButtonTemplate(
                $senderId,
                $this->messages->emptyCategories(),
                $this->payload->backButton(),
                $platform,
            );

            return;
        }

        $user = $this->touchUser($senderId, $platform);

        $this->tracker->log(
            $this->platformConstant($platform),
            UsageEvent::EVENT_VIEW_CATEGORY,
            $this->externalUserId($user, $platform),
            $user->source,
        );

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'data' => $this->payload->categoryCallback($category->slug),
        ])->all();

        $this->api->sendText(
            $senderId,
            $this->messages->categories($categories->pluck('name')->all()),
            $platform,
            $this->payload->quickReplies($options),
        );
    }

    private function showCities(string $senderId, string $slug, string $platform): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            $this->showCategories($senderId, $platform);

            return;
        }

        $cities = Craftsman::query()
            ->where('category_id', $category->id)
            ->active()
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        if ($cities->isEmpty()) {
            $this->api->sendButtonTemplate(
                $senderId,
                $this->messages->emptyCities($category->name),
                $this->payload->backButton(),
                $platform,
            );

            return;
        }

        $user = $this->touchUser($senderId, $platform);

        $this->tracker->log(
            $this->platformConstant($platform),
            UsageEvent::EVENT_VIEW_CITY,
            $this->externalUserId($user, $platform),
            $user->source,
            ['category' => $category->slug],
        );

        $options = $cities->map(fn (string $city) => [
            'label' => $city,
            'data' => $this->payload->cityCallback($category->slug, $city),
        ])->all();

        $this->api->sendText(
            $senderId,
            $this->messages->cities($category->name),
            $platform,
            $this->payload->quickReplies($options),
        );
    }

    private function showCraftsmen(string $senderId, string $slug, string $city, string $platform): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null || blank($city)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        /** @var Collection<int, Craftsman> $craftsmen */
        $craftsmen = Craftsman::query()
            ->forCategoryAndCity($category->id, $city)
            ->get();

        if ($craftsmen->isEmpty()) {
            $this->api->sendButtonTemplate(
                $senderId,
                $this->messages->emptyCraftsmen($category->name, $city),
                $this->payload->backButton(),
                $platform,
            );

            return;
        }

        $user = $this->touchUser($senderId, $platform);

        $this->tracker->log(
            $this->platformConstant($platform),
            UsageEvent::EVENT_VIEW_CRAFTSMEN,
            $this->externalUserId($user, $platform),
            $user->source,
            ['category' => $category->slug, 'city' => $city],
        );

        $elements = [];

        foreach ($craftsmen as $craftsman) {
            $buttons = [
                $this->payload->phoneNumberButton('Pozovi', $craftsman->phone),
            ];

            if (filled($craftsman->viber_id)) {
                $buttons[] = $this->payload->webUrlButton(
                    'Viber',
                    'https://viber.com/chat?number='.urlencode(ltrim($craftsman->viber_id, '+')),
                );
            }

            $elements[] = [
                'title' => $craftsman->name,
                'subtitle' => $this->messages->craftsmanCard($craftsman, $craftsman->is_premium),
                'buttons' => array_slice($buttons, 0, 3),
            ];
        }

        if (! $this->api->sendGenericTemplate($senderId, $elements, $platform)) {
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCraftsmen($category->name, $city),
                $platform,
                $this->payload->quickReplies([], true),
            );

            return;
        }

        $this->api->sendButtonTemplate(
            $senderId,
            'Izaberite sledeću akciju:',
            $this->payload->craftsmenFooterButtons(),
            $platform,
        );
    }

    private function showAbout(string $senderId, string $platform): void
    {
        $this->api->sendButtonTemplate(
            $senderId,
            $this->messages->about(
                Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.'),
                Setting::get('contact_phone'),
                Setting::get('contact_email'),
            ),
            $this->payload->backButton(),
            $platform,
        );
    }

    private function touchUser(string $senderId, string $platform): MessengerUser|InstagramUser
    {
        return match ($platform) {
            'instagram' => InstagramUser::touchFromInstagram($senderId),
            default => MessengerUser::touchFromMessenger($senderId),
        };
    }

    private function logStart(string $platform, string $senderId, Model $user): void
    {
        $this->tracker->log(
            $this->platformConstant($platform),
            UsageEvent::EVENT_START,
            $this->externalUserId($user, $platform),
            $user->source,
        );
    }

    private function platformConstant(string $platform): string
    {
        return match ($platform) {
            'instagram' => UsageEvent::PLATFORM_INSTAGRAM,
            default => UsageEvent::PLATFORM_MESSENGER,
        };
    }

    private function externalUserId(MessengerUser|InstagramUser $user, string $platform): int
    {
        $id = match ($platform) {
            'instagram' => $user->igsid,
            default => $user->psid,
        };

        return (int) $id;
    }
}
