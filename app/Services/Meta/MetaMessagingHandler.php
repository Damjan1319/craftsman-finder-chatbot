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

        $user = $this->touchUser($senderId, $platform);
        $isNewUser = $user->wasRecentlyCreated;

        if (isset($event['postback'])) {
            $payload = trim((string) ($event['postback']['payload'] ?? ''));

            Log::info('Meta postback received', ['platform' => $platform, 'payload' => $payload]);

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

        $text = trim((string) ($event['message']['text'] ?? ''));

        if ($text === '') {
            return;
        }

        Log::info('Meta message received', ['platform' => $platform, 'text' => $text]);

        if ($isNewUser) {
            $this->logStart($platform, $user);

            if ($this->isServiceRequest($text)) {
                $this->showCategories($senderId, $platform);

                return;
            }

            $this->showMainMenu($senderId, $platform);

            return;
        }

        $this->handleText($senderId, $text, $platform);
    }

    private function handleText(string $senderId, string $text, string $platform): void
    {
        if ($this->isGetStartedPayload($text) || in_array(mb_strtolower($text), ['start', '/start', 'početak', 'pocetak', 'menu', 'hi', 'hello', 'hey', 'cao', 'ćao', 'zdravo'], true)) {
            $this->showMainMenu($senderId, $platform);

            return;
        }

        if ($this->isServiceRequest($text)) {
            $this->showCategories($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_HOME, MetaPayloadBuilder::BTN_NEW_SEARCH], true)) {
            if ($text === MetaPayloadBuilder::BTN_NEW_SEARCH) {
                $this->showCategories($senderId, $platform);

                return;
            }

            $this->showMainMenu($senderId, $platform);

            return;
        }

        if (in_array($text, [MetaPayloadBuilder::BTN_ABOUT, 'Pomoć', 'Pomoc'], true)) {
            $this->showAbout($senderId, $platform);

            return;
        }

        $category = $this->findCategoryByName($text);

        if ($category !== null) {
            $this->showCities($senderId, $category->slug, $platform);

            return;
        }

        if ($this->tryHandleSearch($senderId, $text, $platform)) {
            return;
        }

        $this->api->sendText(
            $senderId,
            $this->messages->notUnderstood(),
            $platform,
            $this->payload->quickReplies([], true),
        );
    }

    private function tryHandleSearch(string $senderId, string $text, string $platform): bool
    {
        $parsed = $this->search->parse($text);

        if ($parsed === null) {
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
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCity($city),
                $platform,
                $this->payload->quickReplies([], true),
            );

            return;
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'data' => $this->payload->categoryCallback($category->slug),
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

        $category = $this->findCategoryByName($payload);

        if ($category !== null) {
            $this->showCities($senderId, $category->slug, $platform);

            return;
        }

        $this->showMainMenu($senderId, $platform);
    }

    private function welcomeUser(string $senderId, string $platform, MessengerUser|InstagramUser $user, bool $isNewUser): void
    {
        if ($isNewUser) {
            $this->logStart($platform, $user);
        }

        $this->showMainMenu($senderId, $platform);
    }

    private function showMainMenu(string $senderId, string $platform): void
    {
        $welcome = $platform === 'instagram'
            ? config('instagram.welcome_message')
            : config('messenger.welcome_message');

        $categories = $this->availableCategories();

        if ($categories->isEmpty()) {
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCategories(),
                $platform,
                $this->payload->quickReplies([], true),
            );

            return;
        }

        $options = [
            ['label' => MetaPayloadBuilder::BTN_SERVICE, 'data' => 'act:find'],
            ['label' => MetaPayloadBuilder::BTN_ABOUT, 'data' => 'act:about'],
        ];

        $this->showOptionButtons(
            $senderId,
            $platform,
            $this->messages->home($welcome, $categories->count()),
            $options,
        );
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

    private function showCategories(string $senderId, string $platform): void
    {
        $categories = $this->availableCategories();

        if ($categories->isEmpty()) {
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCategories(),
                $platform,
                $this->payload->quickReplies([], true),
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

        $this->showOptionButtons(
            $senderId,
            $platform,
            $this->messages->categories($categories->count()),
            $options,
        );
    }

    private function showCities(string $senderId, string $slug, string $platform): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            $this->showCategories($senderId, $platform);

            return;
        }

        $cities = $this->catalog->citiesForCategory($category->id);

        if ($cities->isEmpty()) {
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCities($category->name),
                $platform,
                $this->payload->quickReplies([], true),
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
    private function showOptionButtons(string $senderId, string $platform, string $title, array $options): void
    {
        $chunks = array_chunk($options, 12);
        $lastIndex = count($chunks) - 1;
        $messages = [];

        foreach ($chunks as $index => $chunk) {
            $message = [
                'text' => $index === 0 ? $title : $this->messages->moreOptions(),
            ];

            $replies = $this->payload->quickReplies($chunk, $index === $lastIndex);

            if ($replies !== []) {
                $message['quick_replies'] = $replies;
            }

            $messages[] = $message;
        }

        $this->api->sendMany($senderId, $messages, $platform);
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
            $this->api->sendText(
                $senderId,
                $this->messages->emptyCraftsmen($category->name, $city),
                $platform,
                $this->payload->quickReplies([], true),
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
                'title' => $craftsman->is_premium ? "⭐ {$craftsman->name}" : $craftsman->name,
                'subtitle' => $subtitle,
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
        }
    }

    private function showAbout(string $senderId, string $platform): void
    {
        $this->api->sendText(
            $senderId,
            $this->messages->about(
                Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.'),
                Setting::get('contact_phone'),
                Setting::get('contact_email'),
            ),
            $platform,
            $this->payload->quickReplies([], true),
        );
    }

    private function isGetStartedPayload(string $payload): bool
    {
        return in_array(strtoupper($payload), ['GET_STARTED', 'GET STARTED'], true);
    }

    private function isServiceRequest(string $text): bool
    {
        return in_array($text, [MetaPayloadBuilder::BTN_SERVICE, 'Izaberi majstora', 'Tražim majstora'], true);
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
}
