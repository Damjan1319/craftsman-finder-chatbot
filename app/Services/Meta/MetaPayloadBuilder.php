<?php

namespace App\Services\Meta;

class MetaPayloadBuilder
{
    public const BTN_SERVICE = 'Izaberi uslugu';

    public const BTN_NEW_SEARCH = 'Nova pretraga';

    public const BTN_OTHER_CITY = 'Drugi grad';

    public const BTN_HOME = 'Početak';

    public const BTN_ABOUT = 'O nama';

    public function mainMenuButtons(): array
    {
        return [
            $this->postbackButton(self::BTN_SERVICE, 'act:find'),
            $this->postbackButton(self::BTN_ABOUT, 'act:about'),
            $this->postbackButton(self::BTN_HOME, 'act:main'),
        ];
    }

    public function backButton(): array
    {
        return [
            $this->postbackButton(self::BTN_HOME, 'act:main'),
        ];
    }

    public function craftsmenFooterQuickReplies(): array
    {
        return $this->afterSearchQuickReplies('');
    }

    public function afterSearchQuickReplies(string $categorySlug): array
    {
        $options = [
            ['label' => self::BTN_NEW_SEARCH, 'data' => 'act:find'],
        ];

        if ($categorySlug !== '') {
            $options[] = ['label' => self::BTN_OTHER_CITY, 'data' => 'act:cities:'.$categorySlug];
        }

        return $this->quickReplies($options, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function afterSearchButtons(string $categorySlug): array
    {
        $buttons = [
            $this->postbackButton(self::BTN_NEW_SEARCH, 'act:find'),
        ];

        if ($categorySlug !== '') {
            $buttons[] = $this->postbackButton(self::BTN_OTHER_CITY, 'act:cities:'.$categorySlug);
        }

        $buttons[] = $this->postbackButton(self::BTN_HOME, 'act:main');

        return array_slice($buttons, 0, 3);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function homeButton(): array
    {
        return [
            $this->postbackButton(self::BTN_HOME, 'act:main'),
        ];
    }

    /**
     * @param  array<int, array{label: string, data: string}>  $options
     * @return array<int, array<string, mixed>>
     */
    public function quickReplies(array $options, bool $includeBack = true): array
    {
        $replies = array_map(fn (array $option): array => [
            'content_type' => 'text',
            'title' => $this->fitText($option['label'], 20),
            'payload' => $option['data'],
        ], array_slice($options, 0, 13));

        if ($includeBack) {
            $replies[] = [
                'content_type' => 'text',
                'title' => $this->fitText(self::BTN_HOME, 20),
                'payload' => 'act:main',
            ];
        }

        return array_slice($replies, 0, 13);
    }

    public function postbackButton(string $title, string $payload): array
    {
        return [
            'type' => 'postback',
            'title' => $this->fitText($title, 20),
            'payload' => $payload,
        ];
    }

    public function webUrlButton(string $title, string $url): array
    {
        return [
            'type' => 'web_url',
            'title' => $this->fitText($title, 20),
            'url' => $url,
        ];
    }

    public function phoneNumberButton(string $title, string $phone): array
    {
        return [
            'type' => 'phone_number',
            'title' => $this->fitText($title, 20),
            'payload' => $phone,
        ];
    }

    public function categoryCallback(string $slug): string
    {
        return 'cat:'.$slug;
    }

    public function categoryInCityCallback(string $slug, string $city): string
    {
        return 'catcity:'.$slug.':'.base64_encode($city);
    }

    public function parseCategoryInCityCallback(string $data): ?array
    {
        if (! str_starts_with($data, 'catcity:')) {
            return null;
        }

        $parts = explode(':', $data, 3);

        if (count($parts) !== 3) {
            return null;
        }

        $decoded = base64_decode($parts[2], true);

        return [
            'slug' => $parts[1],
            'city' => $decoded ?: $parts[2],
        ];
    }

    public function cityCallback(string $slug, string $city): string
    {
        return 'pick:'.rawurlencode($city);
    }

    public function parsePickCityCallback(string $data): ?string
    {
        if (! str_starts_with($data, 'pick:')) {
            return null;
        }

        $city = rawurldecode(substr($data, 5));

        return $city !== '' ? $city : null;
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

        $decoded = base64_decode($parts[2], true);

        return [
            'slug' => $parts[1],
            'city' => $decoded ?: $parts[2],
        ];
    }

    private function fitText(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_strimwidth($text, 0, max(1, $maxLength - 1), '…');
    }
}
