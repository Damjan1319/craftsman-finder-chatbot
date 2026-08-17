<?php

namespace App\Services\Meta;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\InstagramUser;
use App\Models\MessengerUser;
use App\Models\Setting;
use App\Models\UsageEvent;
use App\Services\Analytics\UsageTracker;
use App\Services\Bot\BotCatalog;
use App\Services\Search\CraftsmanSearchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MetaMessagingHandler
{
    public function __construct(
        private readonly MetaApiClient $api,
        private readonly MetaPayloadBuilder $payload,
        private readonly MetaMessageFormatter $messages,
        private readonly UsageTracker $tracker,
        private readonly CraftsmanSearchService $search,
        private readonly BotCatalog $catalog,
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

        $this->catalog->categoriesWithCounts();

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                try {
                    $this->handleMessagingEvent($event, $platform);
                } catch (\Throwable $exception) {
                    Log::error('Meta messaging event error', [
                        'platform' => $platform,
                        'message' => $exception->getMessage(),
                        'event' => $event,
                    ]);

                    $senderId = (string) ($event['sender']['id'] ?? '');

                    if ($senderId !== '') {
                        $this->api->sendText(
                            $senderId,
                            $this->messages->welcomePrompt(),
                            $platform,
                        );
                    }
                }
            }
        }
    }

    private function handleMessagingEvent(array $event, string $platform): void
    {
        $senderId = (string) ($event['sender']['id'] ?? '');

        if ($senderId === '') {
            return;
        }

        $this->api->sendTypingOn($senderId, $platform);

        $user = $this->touchUser($senderId, $platform);
        $isNewUser = $user->wasRecentlyCreated;

        if (isset($event['reaction'])) {
            Log::info('Meta reaction received', ['platform' => $platform, 'reaction' => $event['reaction']]);
            $this->welcomeUser($senderId, $platform, $user, $isNewUser);

            return;
        }

        if (isset($event['optin'])) {
            $this->welcomeUser($senderId, $platform, $user, $isNewUser);

            return;
        }

        if (isset($event['referral'])) {
            $this->welcomeUser($senderId, $platform, $user, $isNewUser);

            return;
        }

        if (isset($event['postback'])) {
            $payload = trim((string) ($event['postback']['payload'] ?? ''));

            if ($payload === '') {
                $payload = trim((string) ($event['postback']['title'] ?? ''));
            }

            Log::info('Meta postback received', ['platform' => $platform, 'payload' => $payload]);

            if ($payload === '') {
                $this->welcomeUser($senderId, $platform, $user, $isNewUser);

                return;
            }

            if ($this->isGetStartedPayload($payload)) {
                $this->welcomeUser($senderId, $platform, $user, $isNewUser);

                return;
            }

            $this->handlePayload($senderId, $payload, $platform);

            return;
        }

        if (! isset($event['message'])) {
            return;
        }

        if (isset($event['message']['is_echo']) && $event['message']['is_echo']) {
            return;
        }

        if (isset($event['message']['quick_reply']['payload'])) {
            $payload = (string) $event['message']['quick_reply']['payload'];

            Log::info('Meta quick reply received', ['platform' => $platform, 'payload' => $payload]);

            $this->handlePayload($senderId, $payload, $platform);

            return;
        }

        if ($this->isStickerOrLikeMessage($event['message'])) {
            Log::info('Meta sticker/like received', ['platform' => $platform]);
            $this->welcomeUser($senderId, $platform, $user, $isNewUser);

            return;
        }

        $text = trim((string) ($event['message']['text'] ?? ''));

        if ($text === '') {
            $this->welcomeUser($senderId, $platform, $user, $isNewUser);

            return;
        }

        Log::info('Meta message received', ['platform' => $platform, 'text' => $text]);

        if ($isNewUser || $this->isWelcomeIntent($text)) {
            if ($isNewUser) {
                $this->logStart($platform, $user);
            }

            if ($this->tryHandleSearch($senderId, $text, $platform)) {
                return;
            }

            $this->welcomeUser($senderId, $platform, $user, false);

            return;
        }

        $this->handleText($senderId, $text, $platform);
    }

    private function handleText(string $senderId, string $text, string $platform): void
    {
        if ($this->isWelcomeIntent($text)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_HOME, MetaPayloadBuilder::BTN_NEW_SEARCH], true)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_ABOUT, 'Pomoć', 'Pomoc'], true)) {
            $this->showAbout($senderId, $platform);

            return;
        }

        if ($this->tryHandleSearch($senderId, $text, $platform)) {
            return;
        }

        if ($this->tryShowCraftsmenFromPending($senderId, $platform, $text)) {
            return;
        }

        $category = $this->findCategoryByName($text);

        if ($category !== null) {
            $this->showCities($senderId, $category->slug, $platform);

            return;
        }

        $this->showCategories($senderId, $platform, $this->messages->notUnderstood());
    }

    private function tryHandleSearch(string $senderId, string $text, string $platform): bool
    {
        $parsed = $this->search->parse($text);

        if ($parsed === null) {
            $city = $this->search->resolveCityOnly($text);

            if ($city !== null) {
                $this->showCategoriesForCity($senderId, $city, $platform);

                return true;
            }

            return false;
        }

        if ($parsed->isComplete()) {
            $this->showCraftsmen($senderId, $parsed->category->slug, $parsed->city, $platform);

            return true;
        }

        if ($parsed->category !== null) {
            $this->showCities($senderId, $parsed->category->slug, $platform);

            return true;
        }

        if ($parsed->city !== null) {
            $this->showCategoriesForCity($senderId, $parsed->city, $platform);

            return true;
        }

        return false;
    }

    private function showCategoriesForCity(string $senderId, string $city, string $platform): void
    {
        $categories = $this->search->categoriesInCity($city);

        if ($categories->isEmpty()) {
            $this->sendHomeButton($senderId, $platform, $this->messages->emptyCity($city));

            return;
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'data' => $this->payload->categoryInCityCallback($category->slug, $city),
        ])->all();

        $this->showOptionButtons(
            $senderId,
            $platform,
            $this->messages->categoriesForCity($city, $categories->count()),
            $options,
        );
    }

    private function handlePayload(string $senderId, string $payload, string $platform): void
    {
        if ($this->isGetStartedPayload($payload) || $this->isServiceRequest($payload)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        if (str_starts_with($payload, 'act:')) {
            $action = substr($payload, 4);

            if (str_starts_with($action, 'cities:')) {
                $this->showCities($senderId, substr($action, 7), $platform);

                return;
            }

            match ($action) {
                'find', 'main' => $this->showCategories($senderId, $platform),
                'about' => $this->showAbout($senderId, $platform),
                default => $this->showCategories($senderId, $platform),
            };

            return;
        }

        if (str_starts_with($payload, 'catcity:')) {
            $parsed = $this->payload->parseCategoryInCityCallback($payload);

            if ($parsed !== null) {
                $this->showCraftsmen($senderId, $parsed['slug'], $parsed['city'], $platform);
            } else {
                $this->showCategories($senderId, $platform);
            }

            return;
        }

        if (str_starts_with($payload, 'cat:')) {
            $slug = trim(substr($payload, 4));

            if ($slug !== '') {
                Log::info('Meta category selected', ['slug' => $slug]);
                $this->showCities($senderId, $slug, $platform);
            } else {
                $this->showCategories($senderId, $platform);
            }

            return;
        }

        if (str_starts_with($payload, 'pick:')) {
            $city = $this->payload->parsePickCityCallback($payload);
            $slug = $this->pendingCategorySlug($senderId, $platform);

            if ($city !== null && filled($slug)) {
                Log::info('Meta city selected', ['slug' => $slug, 'city' => $city]);
                $this->showCraftsmen($senderId, $slug, $city, $platform);

                return;
            }

            $this->showCategories($senderId, $platform);

            return;
        }

        if (str_starts_with($payload, 'city:')) {
            $parsed = $this->payload->parseCityCallback($payload);

            if ($parsed !== null) {
                $this->showCraftsmen($senderId, $parsed['slug'], $parsed['city'], $platform);
            } elseif (! $this->tryShowCraftsmenFromPending($senderId, $platform, $payload)) {
                $this->showCategories($senderId, $platform);
            }

            return;
        }

        if ($this->tryShowCraftsmenFromPending($senderId, $platform, $payload)) {
            return;
        }

        $category = $this->findCategoryByName($payload);

        if ($category !== null) {
            $this->showCities($senderId, $category->slug, $platform);

            return;
        }

        $this->showCategories($senderId, $platform);
    }

    private function welcomeUser(string $senderId, string $platform, MessengerUser|InstagramUser $user, bool $isNewUser): void
    {
        if ($isNewUser) {
            $this->logStart($platform, $user);
        }

        $this->showCategories($senderId, $platform, $this->messages->welcomePrompt());
    }

    /** @return Collection<int, Category> */
    private function availableCategories(): Collection
    {
        return $this->catalog->categoriesWithCounts();
    }

    private function findCategoryByName(string $text): ?Category
    {
        $normalized = mb_strtolower(trim($text));

        return $this->availableCategories()->first(
            fn (Category $category) => mb_strtolower($category->name) === $normalized,
        );
    }

    private function showCategories(string $senderId, string $platform, ?string $message = null): void
    {
        $this->clearCategoryChoice($senderId, $platform);

        $categories = $this->availableCategories();

        if ($categories->isEmpty()) {
            $this->sendHomeButton($senderId, $platform, $this->messages->emptyCategories());

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

        $this->showOptionButtons(
            $senderId,
            $platform,
            $message ?? $this->messages->welcomePrompt(),
            $options,
            includeBack: false,
        );
    }

    private function showCities(string $senderId, string $slug, string $platform): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            $this->showCategories($senderId, $platform);

            return;
        }

        $this->rememberCategoryChoice($senderId, $platform, $slug);

        $cities = $this->catalog->citiesForCategory($category->id);

        if ($cities->isEmpty()) {
            $this->sendHomeButton($senderId, $platform, $this->messages->emptyCities($category->name));

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

        $this->showOptionButtons(
            $senderId,
            $platform,
            $this->messages->cities($category->name, $cities->count()),
            $options,
        );
    }

    /**
     * @param  array<int, array{label: string, data: string}>  $options
     */
    private function showOptionButtons(
        string $senderId,
        string $platform,
        string $title,
        array $options,
        bool $includeBack = true,
    ): void {
        if ($options === []) {
            $this->api->sendText($senderId, $title, $platform);

            return;
        }

        // Sve opcije u jednoj poruci (quick replies) — najpouzdanije u Messengeru.
        if (count($options) <= ($includeBack ? 12 : 13)) {
            $this->api->sendText(
                $senderId,
                $title,
                $platform,
                $this->payload->quickReplies($options, $includeBack),
            );

            return;
        }

        // Više od 13: karusel kartica (do 10 u jednoj poruci).
        $elements = array_map(fn (array $option): array => [
            'title' => mb_strimwidth($option['label'], 0, 80, '…'),
            'subtitle' => 'Kliknite za izbor',
            'buttons' => [
                $this->payload->postbackButton($option['label'], $option['data']),
            ],
        ], $options);

        $this->api->sendText($senderId, $title, $platform);

        foreach (array_chunk($elements, 10) as $chunk) {
            $this->api->sendGenericTemplate($senderId, $chunk, $platform);
        }

        if ($includeBack) {
            $this->api->sendText(
                $senderId,
                $this->messages->footerPrompt(),
                $platform,
                $this->payload->quickReplies([], true),
            );
        }
    }

    private function sendHomeButton(string $senderId, string $platform, string $text): void
    {
        $this->api->sendButtonTemplate(
            $senderId,
            $text,
            $this->payload->homeButton(),
            $platform,
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
            $this->sendHomeButton($senderId, $platform, $this->messages->emptyCraftsmen($category->name, $city));

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
        $count = $craftsmen->count();

        foreach ($craftsmen as $index => $craftsman) {
            $buttons = [
                $this->payload->phoneNumberButton('Pozovi', $craftsman->phone),
            ];

            if (filled($craftsman->viber_id)) {
                $buttons[] = $this->payload->webUrlButton(
                    'Viber',
                    'https://viber.com/chat?number='.urlencode(ltrim($craftsman->viber_id, '+')),
                );
            }

            $subtitle = $this->messages->craftsmanCard($craftsman, $craftsman->is_premium);

            if ($index === 0) {
                $subtitle = "Pronađeno {$count} ".($count === 1 ? 'majstor' : 'majstora').".\n\n{$subtitle}";
            }

            $elements[] = [
                'title' => $craftsman->is_premium ? "Preporučeno: {$craftsman->name}" : $craftsman->name,
                'subtitle' => $subtitle,
                'buttons' => array_slice($buttons, 0, 3),
            ];
        }

        if (! $this->api->sendGenericTemplate($senderId, $elements, $platform)) {
            $this->sendHomeButton($senderId, $platform, $this->messages->emptyCraftsmen($category->name, $city));

            return;
        }

        $this->api->sendText(
            $senderId,
            $this->messages->footerPrompt(),
            $platform,
            $this->payload->afterSearchQuickReplies($category->slug),
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
            $this->payload->homeButton(),
            $platform,
        );
    }

    private function isStickerOrLikeMessage(array $message): bool
    {
        if (isset($message['sticker_id'])) {
            return true;
        }

        foreach ($message['attachments'] ?? [] as $attachment) {
            $type = (string) ($attachment['type'] ?? '');

            if (in_array($type, ['image', 'like', 'fallback'], true)) {
                return true;
            }

            if (isset($attachment['payload']['sticker_id'])) {
                return true;
            }
        }

        return false;
    }

    private function isWelcomeIntent(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return false;
        }

        if ($this->isGetStartedPayload($text) || $this->isServiceRequest($text)) {
            return true;
        }

        $exactPhrases = [
            'start', '/start', 'početak', 'pocetak', 'menu',
            'hi', 'hello', 'hey', 'cao', 'ćao', 'zdravo', 'dobro',
            'like', '👍', '👍🏻', '❤', '❤️', '💙', '🙂', '😊',
            'majstor', 'usluga', 'treba mi majstor', 'trebam majstora',
        ];

        foreach ($exactPhrases as $phrase) {
            if ($normalized === $phrase) {
                return true;
            }
        }

        $containsPhrases = [
            'treba mi majstor', 'trebam majstora', 'treba mi majstora',
            'tražim majstora', 'trazim majstora', 'nadji majstora', 'nađi majstora',
            'nadjite majstora', 'nađite majstora', 'potrebna usluga', 'potrebna mi je usluga',
            'treba mi usluga', 'trebam uslugu',
        ];

        foreach ($containsPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function isGetStartedPayload(string $payload): bool
    {
        return in_array(strtoupper($payload), ['GET_STARTED', 'GET STARTED'], true)
            || $payload === 'act:find';
    }

    private function isServiceRequest(string $text): bool
    {
        return in_array($text, [
            MetaPayloadBuilder::BTN_SERVICE,
            'Izaberi majstora',
            'Tražim majstora',
            'Treba mi majstor',
            'Treba mi majstora',
        ], true);
    }

    private function touchUser(string $senderId, string $platform): MessengerUser|InstagramUser
    {
        return match ($platform) {
            'instagram' => InstagramUser::touchFromInstagram($senderId),
            default => MessengerUser::touchFromMessenger($senderId),
        };
    }

    private function logStart(string $platform, Model $user): void
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

    private function rememberCategoryChoice(string $senderId, string $platform, string $slug): void
    {
        $user = $this->touchUser($senderId, $platform);
        $user->rememberCategory($slug);
    }

    private function pendingCategorySlug(string $senderId, string $platform): ?string
    {
        $slug = $this->touchUser($senderId, $platform)->pending_category_slug;

        return filled($slug) ? (string) $slug : null;
    }

    private function clearCategoryChoice(string $senderId, string $platform): void
    {
        $this->touchUser($senderId, $platform)->clearCategory();
    }

    private function tryShowCraftsmenFromPending(string $senderId, string $platform, string $cityLabel): bool
    {
        $slug = $this->pendingCategorySlug($senderId, $platform);

        if ($slug === null) {
            return false;
        }

        $city = $this->resolveCityInCategory($slug, $cityLabel);

        if ($city === null) {
            return false;
        }

        $this->showCraftsmen($senderId, $slug, $city, $platform);

        return true;
    }

    private function resolveCityInCategory(string $slug, string $input): ?string
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            return null;
        }

        $normalizedInput = mb_strtolower(trim($input));

        return $this->catalog->citiesForCategory($category->id)->first(
            fn (string $city) => mb_strtolower($city) === $normalizedInput,
        );
    }
}
