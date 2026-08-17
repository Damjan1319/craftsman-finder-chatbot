<?php

namespace App\Services\Telegram;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Models\TelegramUser;
use App\Models\UsageEvent;
use App\Services\Analytics\UsageTracker;
use App\Services\Bot\BotCatalog;
use App\Services\Search\CraftsmanSearchService;
use Illuminate\Support\Collection;

class TelegramUpdateHandler
{
    public function __construct(
        private readonly TelegramApiClient $api,
        private readonly TelegramKeyboardBuilder $keyboard,
        private readonly TelegramMessageFormatter $messages,
        private readonly UsageTracker $tracker,
        private readonly CraftsmanSearchService $search,
        private readonly BotCatalog $catalog,
    ) {}

    public function handle(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);

            return;
        }

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (int) $message['chat']['id'];
        $from = $message['from'] ?? [];
        $text = trim($message['text'] ?? '');

        if (str_starts_with($text, '/start')) {
            $source = $this->parseStartPayload($text);
            $user = TelegramUser::touchFromTelegram($from, $source);

            $this->tracker->log(
                UsageEvent::PLATFORM_TELEGRAM,
                UsageEvent::EVENT_START,
                (int) $from['id'],
                $user->source,
            );

            $this->startFind($chatId, $user);

            return;
        }

        $user = TelegramUser::touchFromTelegram($from);
        $isNewUser = $user->wasRecentlyCreated;

        if ($isNewUser) {
            $this->tracker->log(
                UsageEvent::PLATFORM_TELEGRAM,
                UsageEvent::EVENT_START,
                (int) $from['id'],
                $user->source,
            );

            if ($this->tryHandleSearch($chatId, $text, $user)) {
                return;
            }

            $this->startFind($chatId, $user);

            return;
        }

        if (in_array($text, [TelegramKeyboardBuilder::BTN_SERVICE, 'Izaberi majstora', 'Tražim majstora'], true)) {
            $this->startFind($chatId, $user);

            return;
        }

        if (in_array($text, [TelegramKeyboardBuilder::BTN_HOME, '/menu', '/pocetak', '/start'], true)) {
            $this->startFind($chatId, $user);

            return;
        }

        if (in_array($text, [TelegramKeyboardBuilder::BTN_ABOUT, '/help', 'Pomoć'], true)) {
            $this->showAbout($chatId, $user);

            return;
        }

        if ($this->tryHandleSearch($chatId, $text, $user)) {
            return;
        }

        $this->showCategories($chatId, $user, null, $this->messages->notUnderstood());
    }

    private function tryHandleSearch(int $chatId, string $text, TelegramUser $user): bool
    {
        $parsed = $this->search->parse($text);

        if ($parsed === null) {
            $city = $this->search->resolveCityOnly($text);

            if ($city !== null) {
                $this->showCategoriesForCity($chatId, $city, $user);

                return true;
            }

            return false;
        }

        if ($parsed->isComplete()) {
            $this->showCraftsmen($chatId, $parsed->category->slug, $parsed->city, $user);

            return true;
        }

        if ($parsed->category !== null) {
            $this->showCities($chatId, $parsed->category->slug, $user);

            return true;
        }

        if ($parsed->city !== null) {
            $this->showCategoriesForCity($chatId, $parsed->city, $user);

            return true;
        }

        return false;
    }

    private function showCategoriesForCity(int $chatId, string $city, TelegramUser $user, ?int $messageId = null): void
    {
        $categories = $this->search->categoriesInCity($city);

        if ($categories->isEmpty()) {
            $this->replaceScreen(
                $chatId,
                $user,
                $messageId,
                $this->messages->emptyCity($city),
                $this->keyboard->backMenu(),
            );

            return;
        }

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'data' => $this->keyboard->categoryInCityCallback($category->slug, $city),
        ])->all();

        $this->replaceScreen(
            $chatId,
            $user,
            $messageId,
            $this->messages->categoriesForCity($city, $categories->count()),
            $this->keyboard->optionsMenu($options),
        );
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (int) $callbackQuery['message']['chat']['id'];
        $messageId = isset($callbackQuery['message']['message_id'])
            ? (int) $callbackQuery['message']['message_id']
            : null;
        $from = $callbackQuery['from'] ?? [];
        $user = TelegramUser::touchFromTelegram($from);

        if (str_starts_with($data, 'phone:')) {
            $craftsman = Craftsman::query()->find((int) substr($data, 6));

            if ($craftsman !== null) {
                $this->api->answerCallbackQuery(
                    $callbackQuery['id'],
                    "Pozovite: {$craftsman->phone}",
                    true,
                );
            } else {
                $this->api->answerCallbackQuery($callbackQuery['id']);
            }

            return;
        }

        $this->api->answerCallbackQuery($callbackQuery['id']);

        if (str_starts_with($data, 'act:')) {
            if ($this->shouldResetChat($user)) {
                $user->clearBotMessages($this->api, $chatId);
                $user->refresh();
                $messageId = null;
            }

            $action = substr($data, 4);

            if (str_starts_with($action, 'cities:')) {
                $this->showCities($chatId, substr($action, 7), $user, $messageId);

                return;
            }

            match ($action) {
                'find', 'main' => $this->showCategories($chatId, $user, $messageId),
                'about' => $this->showAbout($chatId, $user, $messageId),
                default => $this->showCategories($chatId, $user, $messageId),
            };

            return;
        }

        if (str_starts_with($data, 'catcity:')) {
            $parsed = $this->keyboard->parseCategoryInCityCallback($data);

            if ($parsed !== null) {
                $this->showCraftsmen($chatId, $parsed['slug'], $parsed['city'], $user);
            }

            return;
        }

        if (str_starts_with($data, 'cat:')) {
            $this->showCities($chatId, substr($data, 4), $user, $messageId);

            return;
        }

        if (str_starts_with($data, 'city:')) {
            $parsed = $this->keyboard->parseCityCallback($data);

            if ($parsed !== null) {
                $this->showCraftsmen($chatId, $parsed['slug'], $parsed['city'], $user);
            }

            return;
        }

        $this->showCategories($chatId, $user, $messageId);
    }

    private function startFind(int $chatId, TelegramUser $user): void
    {
        if ($this->shouldResetChat($user)) {
            $user->clearBotMessages($this->api, $chatId);
            $user->refresh();
        }

        $this->showCategories($chatId, $user);
    }

    private function replaceScreen(
        int $chatId,
        TelegramUser $user,
        ?int $messageId,
        string $text,
        ?array $replyMarkup = null,
        bool $resetChat = false,
    ): void {
        if ($resetChat) {
            $user->clearBotMessages($this->api, $chatId);
            $user->refresh();
            $messageId = null;
        }

        if ($messageId !== null && $this->api->editMessageText($chatId, $messageId, $text, $replyMarkup)) {
            $user->rememberBotMessages([$messageId]);

            return;
        }

        $user->clearBotMessages($this->api, $chatId);
        $user->refresh();

        $newMessageId = $this->api->sendMessage($chatId, $text, $replyMarkup);

        if ($newMessageId !== null) {
            $user->rememberBotMessages([$newMessageId]);
        }
    }

    private function showMainMenu(int $chatId, TelegramUser $user, ?int $messageId = null, bool $resetChat = false): void
    {
        $categoryCount = $this->catalog->categoriesWithCounts()->count();

        $text = $this->messages->home(config('telegram.welcome_message'), $categoryCount);

        if ($resetChat || $messageId === null) {
            $user->clearBotMessages($this->api, $chatId);
            $user->refresh();

            $welcomeId = $this->api->sendMessage(
                $chatId,
                $text,
                $this->keyboard->homeReplyKeyboard(),
            );

            if ($welcomeId !== null) {
                $user->rememberBotMessages([$welcomeId]);
            }

            return;
        }

        $this->replaceScreen(
            $chatId,
            $user,
            $messageId,
            $text,
            $this->keyboard->mainMenu(),
        );
    }

    private function showCategories(int $chatId, TelegramUser $user, ?int $messageId = null, ?string $message = null): void
    {
        $categories = $this->catalog->categoriesWithCounts();

        if ($categories->isEmpty()) {
            $this->replaceScreen(
                $chatId,
                $user,
                $messageId,
                $this->messages->emptyCategories(),
                $this->keyboard->backMenu(),
            );

            return;
        }

        $this->tracker->log(
            UsageEvent::PLATFORM_TELEGRAM,
            UsageEvent::EVENT_VIEW_CATEGORY,
            (int) $user->telegram_id,
            $user->source,
        );

        $options = $categories->map(fn (Category $category) => [
            'label' => $category->name,
            'data' => $this->keyboard->categoryCallback($category->slug),
        ])->all();

        $this->replaceScreen(
            $chatId,
            $user,
            $messageId,
            $message ?? $this->messages->categories($categories->count()),
            $this->keyboard->optionsMenu($options),
        );
    }

    private function showCities(int $chatId, string $slug, TelegramUser $user, ?int $messageId = null): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null) {
            $this->showCategories($chatId, $user, $messageId);

            return;
        }

        $cities = $this->catalog->citiesForCategory($category->id);

        if ($cities->isEmpty()) {
            $this->replaceScreen(
                $chatId,
                $user,
                $messageId,
                $this->messages->emptyCities($category->name),
                $this->keyboard->backMenu(),
            );

            return;
        }

        $this->tracker->log(
            UsageEvent::PLATFORM_TELEGRAM,
            UsageEvent::EVENT_VIEW_CITY,
            (int) $user->telegram_id,
            $user->source,
            ['category' => $category->slug],
        );

        $options = $cities->map(fn (string $city) => [
            'label' => $city,
            'data' => $this->keyboard->cityCallback($category->slug, $city),
        ])->all();

        $this->replaceScreen(
            $chatId,
            $user,
            $messageId,
            $this->messages->cities($category->name, $cities->count()),
            $this->keyboard->optionsMenu($options),
        );
    }

    private function showCraftsmen(int $chatId, string $slug, string $city, TelegramUser $user): void
    {
        $category = Category::query()->active()->where('slug', $slug)->first();

        if ($category === null || blank($city)) {
            $this->showCategories($chatId, $user);

            return;
        }

        /** @var Collection<int, Craftsman> $craftsmen */
        $craftsmen = Craftsman::query()
            ->forCategoryAndCity($category->id, $city)
            ->get();

        if ($craftsmen->isEmpty()) {
            $this->replaceScreen(
                $chatId,
                $user,
                null,
                $this->messages->emptyCraftsmen($category->name, $city),
                $this->keyboard->backMenu(),
            );

            return;
        }

        $this->tracker->log(
            UsageEvent::PLATFORM_TELEGRAM,
            UsageEvent::EVENT_VIEW_CRAFTSMEN,
            (int) $user->telegram_id,
            $user->source,
            ['category' => $category->slug, 'city' => $city],
        );

        $lines = [$this->messages->craftsmen($category->name, $city, $craftsmen->count())];
        $keyboard = [];

        foreach ($craftsmen as $craftsman) {
            $lines[] = '';
            $lines[] = $this->messages->craftsmanCard($craftsman, $craftsman->is_premium);

            $row = [
                ['text' => 'Pozovi', 'callback_data' => 'phone:'.$craftsman->id],
            ];

            if (filled($craftsman->viber_id)) {
                $row[] = [
                    'text' => 'Viber',
                    'url' => 'https://viber.com/chat?number='.urlencode(ltrim($craftsman->viber_id, '+')),
                ];
            }

            $keyboard[] = $row;
        }

        $keyboard[] = [
            ['text' => TelegramKeyboardBuilder::BTN_NEW_SEARCH, 'callback_data' => 'act:find'],
            ['text' => TelegramKeyboardBuilder::BTN_OTHER_CITY, 'callback_data' => 'act:cities:'.$category->slug],
        ];
        $keyboard[] = [
            ['text' => TelegramKeyboardBuilder::BTN_HOME, 'callback_data' => 'act:main'],
        ];

        $user->clearBotMessages($this->api, $chatId);
        $user->refresh();

        $messageId = $this->api->sendMessage($chatId, implode("\n", $lines), [
            'inline_keyboard' => $keyboard,
        ]);

        if ($messageId !== null) {
            $user->rememberBotMessages([$messageId]);
        }
    }

    private function shouldResetChat(TelegramUser $user): bool
    {
        return count($user->bot_message_ids ?? []) > 1;
    }

    private function showAbout(int $chatId, TelegramUser $user, ?int $messageId = null): void
    {
        $text = $this->messages->about(
            Setting::get('about_text', 'Platforma za pronalaženje proverenih majstora.'),
            Setting::get('contact_phone'),
            Setting::get('contact_email'),
        );

        $this->replaceScreen(
            $chatId,
            $user,
            $messageId,
            $text,
            $this->keyboard->backMenu(),
        );
    }

    private function parseStartPayload(string $text): ?string
    {
        $payload = trim(substr($text, 6));

        if ($payload === '') {
            return null;
        }

        return str($payload)->limit(64)->toString();
    }
}
