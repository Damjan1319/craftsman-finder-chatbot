<?php

namespace App\Services\Telegram;

class TelegramKeyboardBuilder
{
    public const BTN_SERVICE = 'Izaberi uslugu';

    public const BTN_NEW_SEARCH = 'Nova pretraga';

    public const BTN_HOME = 'Početak';

    public const BTN_ABOUT = 'O nama';

    public function homeReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => self::BTN_SERVICE],
                    ['text' => self::BTN_ABOUT],
                ],
                [
                    ['text' => self::BTN_HOME],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    public function mainMenu(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => self::BTN_SERVICE, 'callback_data' => 'act:find'],
                    ['text' => self::BTN_ABOUT, 'callback_data' => 'act:about'],
                ],
                [
                    ['text' => self::BTN_HOME, 'callback_data' => 'act:main'],
                ],
            ],
        ];
    }

    public function backMenu(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => self::BTN_HOME, 'callback_data' => 'act:main']],
            ],
        ];
    }

    public function craftsmenFooterMenu(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => self::BTN_NEW_SEARCH, 'callback_data' => 'act:find'],
                    ['text' => self::BTN_HOME, 'callback_data' => 'act:main'],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, data: string}>  $options
     */
    public function optionsMenu(array $options, bool $includeBack = true): array
    {
        $rows = array_map(function (array $option): array {
            return [[
                'text' => $this->fitButtonText($option['label'], 64),
                'callback_data' => $option['data'],
            ]];
        }, $options);

        if ($includeBack) {
            $rows[] = [['text' => self::BTN_HOME, 'callback_data' => 'act:main']];
        }

        return ['inline_keyboard' => $rows];
    }

    private function fitButtonText(string $text, int $maxLength = 64): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_strimwidth($text, 0, max(1, $maxLength - 1), '…');
    }

    public function categoryCallback(string $slug): string
    {
        return 'cat:'.$slug;
    }

    public function cityCallback(string $slug, string $city): string
    {
        $data = 'city:'.$slug.':'.base64_encode($city);

        if (strlen($data) <= 64) {
            return $data;
        }

        return 'city:'.$slug.':'.rtrim(strtr(base64_encode($city), '+/', '-_'), '=');
    }

    public function parseCityCallback(string $data): ?array
    {
        if (! str_starts_with($data, 'city:')) {
            return null;
        }

        $parts = explode(':', $data, 3);

        if (count($parts) !== 3) {
            return null;
        }

        $encoded = $parts[2];
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false) {
            $decoded = base64_decode($encoded, true);
        }

        return [
            'slug' => $parts[1],
            'city' => $decoded ?: $encoded,
        ];
    }
}
