<?php

namespace App\Services\Telegram;

use App\Models\Category;
use App\Models\Craftsman;
use App\Models\Setting;
use App\Models\TelegramUser;
use App\Models\UsageEvent;
use App\Services\Analytics\UsageTracker;
use Illuminate\Support\Collection;

class TelegramUpdateHandler
{
    public function __construct(
        private readonly TelegramApiClient $api,
        private readonly TelegramKeyboardBuilder $keyboard,
        private readonly TelegramMessageFormatter $messages,
        private readonly UsageTracker $tracker,
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

            $this->showMainMenu($chatId, $user, null, true);

            return;
        }

        $user = TelegramUser::touchFromTelegram($from);

        if (in_array($text, [TelegramKeyboardBuilder::BTN_SERVICE, 'Izaberi majstora', 'Tražim majstora'], true)) {
            $this->startFind($chatId, $user);

            return;
        }

        if (in_array($text, [TelegramKeyboardBuilder::BTN_HOME, '/menu', '/pocetak'], true)) {
            $this->showMainMenu($chatId, $user, null, true);

            return;
        }

        if (in_array($text, [TelegramKeyboardBuilder::BTN_ABOUT, '/help', 'Pomoć'], true)) {
            $this->showAbout($chatId, $user);

            return;
        }

        $this->showMainMenu($chatId, $user, null, true);
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

            match (substr($data, 4)) {
                'find' => $this->showCategories($chatId, $user, $messageId),
                'about' => $this->showAbout($chatId, $user, $messageId),
                'main' => $this->showMainMenu($chatId, $user, $messageId, true),
                default => $this->showMainMenu($chatId, $user, $messageId, true),
            };

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

        $this->showMainMenu($chatId, $user, $messageId, true);
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
        $text = $this->messages->home(config('telegram.welcome_message'));

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

    private function showCategories(int $chatId, TelegramUser $user, ?int $messageId = null): void
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
            $this->messages->categories($categories->pluck('name')->all()),
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

        $cities = Craftsman::query()
            ->where('category_id', $category->id)
            ->active()
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

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
            $this->messages->cities($category->name),
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

        $recommended = $craftsmen->where('is_premium', true)->values();
        $others = $craftsmen->where('is_premium', false)->values();

        $user->clearBotMessages($this->api, $chatId);
        $user->refresh();

        $sentMessageIds = [];

        foreach ($recommended as $craftsman) {
            $cardId = $this->sendCraftsmanCard($chatId, $craftsman, true);

            if ($cardId !== null) {
                $sentMessageIds[] = $cardId;
            }
        }

        foreach ($others as $craftsman) {
            $cardId = $this->sendCraftsmanCard($chatId, $craftsman, false);

            if ($cardId !== null) {
                $sentMessageIds[] = $cardId;
            }
        }

        $footerId = $this->api->sendMessage(
            $chatId,
            ' ',
            $this->keyboard->craftsmenFooterMenu(),
        );

        if ($footerId !== null) {
            $sentMessageIds[] = $footerId;
        }

        $user->rememberBotMessages($sentMessageIds);
    }

    private function sendCraftsmanCard(int $chatId, Craftsman $craftsman, bool $featured): ?int
    {
        $contactRow = [
            ['text' => 'Pozovi', 'callback_data' => 'phone:'.$craftsman->id],
        ];

        if (filled($craftsman->viber_id)) {
            $contactRow[] = [
                'text' => 'Viber',
                'url' => 'https://viber.com/chat?number='.urlencode(ltrim($craftsman->viber_id, '+')),
            ];
        }

        return $this->api->sendMessage(
            $chatId,
            $this->formatCraftsmanMessage($craftsman, $featured),
            [
                'inline_keyboard' => [
                    $contactRow,
                    [['text' => TelegramKeyboardBuilder::BTN_HOME, 'callback_data' => 'act:main']],
                ],
            ],
        );
    }

    private function formatCraftsmanMessage(Craftsman $craftsman, bool $featured = false): string
    {
        $lines = [];

        if ($featured) {
            $lines[] = '<b>'.e($craftsman->name).'</b>';
            $lines[] = '<i>Preporučeno</i>';
        } else {
            $lines[] = '<b>'.e($craftsman->name).'</b>';
        }

        $lines[] = '';
        $lines[] = 'Grad: '.e($craftsman->city);

        if (filled($craftsman->bio)) {
            $lines[] = '';
            $lines[] = '„'.e((string) str($craftsman->bio)->limit(180)).'”';
        }

        $lines[] = '<code>'.e($craftsman->phone).'</code>';

        return implode("\n", $lines);
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
