<?php

namespace App\Services\Viber;

class ViberMessageBuilder
{
    public function text(string $text, ?array $keyboard = null): array
    {
        $message = [
            'type' => 'text',
            'text' => $text,
        ];

        if ($keyboard !== null) {
            $message['keyboard'] = $keyboard;
        }

        return $this->withSender($message);
    }

    public function richMedia(array $richMedia, ?array $keyboard = null): array
    {
        $message = [
            'type' => 'rich_media',
            'rich_media' => $richMedia,
        ];

        if ($keyboard !== null) {
            $message['keyboard'] = $keyboard;
        }

        return $this->withSender($message);
    }

    public function mainKeyboard(): array
    {
        return [
            'Type' => 'keyboard',
            'DefaultHeight' => false,
            'Buttons' => [
                $this->replyButton('Tražim majstora', 'find_craftsman'),
                $this->replyButton('O nama / Kontakt', 'about'),
            ],
        ];
    }

    public function backKeyboard(): array
    {
        return [
            'Type' => 'keyboard',
            'DefaultHeight' => false,
            'Buttons' => [
                $this->replyButton('← Nazad', 'back_main'),
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, tracking: array<string, string>|string}>  $options
     */
    public function optionsKeyboard(array $options, bool $includeBack = true): array
    {
        $buttons = [];

        foreach ($options as $option) {
            $buttons[] = $this->replyButton($option['label'], $option['tracking']);
        }

        if ($includeBack) {
            $buttons[] = $this->replyButton('← Nazad', 'back_main');
        }

        return [
            'Type' => 'keyboard',
            'DefaultHeight' => false,
            'Buttons' => $buttons,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Craftsman>  $craftsmen
     */
    public function craftsmenCarousel($craftsmen): array
    {
        $buttons = [];

        foreach ($craftsmen as $craftsman) {
            $buttons[] = [
                'Columns' => 6,
                'Rows' => 3,
                'ActionType' => 'none',
                'Text' => "<font color=\"#323232\"><b>{$craftsman->name}</b></font>"
                    .($craftsman->is_premium ? '<br><font color="#B45309"><b>Preporučeno</b></font>' : '')
                    ."<br><br>{$craftsman->city}"
                    .'<br>'.str($craftsman->bio)->limit(80),
                'TextSize' => 'medium',
                'TextVAlign' => 'top',
                'TextHAlign' => 'left',
            ];

            $buttons[] = [
                'Columns' => 6,
                'Rows' => 1,
                'ActionType' => 'open-url',
                'ActionBody' => 'tel:'.$craftsman->phone,
                'Text' => '📞 Pozovi odmah',
                'TextSize' => 'regular',
                'BgColor' => '#7360F2',
                'TextVAlign' => 'middle',
                'TextHAlign' => 'center',
            ];

            if (filled($craftsman->viber_id)) {
                $buttons[] = [
                    'Columns' => 6,
                    'Rows' => 1,
                    'ActionType' => 'open-url',
                    'ActionBody' => 'viber://chat?number='.urlencode($craftsman->viber_id),
                    'Text' => '💬 Pošalji Viber poruku',
                    'TextSize' => 'regular',
                    'BgColor' => '#665CAC',
                    'TextVAlign' => 'middle',
                    'TextHAlign' => 'center',
                ];
            }
        }

        return [
            'Type' => 'rich_media',
            'ButtonsGroupColumns' => 6,
            'ButtonsGroupRows' => 7,
            'BgColor' => '#FFFFFF',
            'Buttons' => $buttons,
        ];
    }

    private function replyButton(string $label, array|string $tracking): array
    {
        $trackingData = is_array($tracking)
            ? json_encode($tracking)
            : json_encode(['action' => $tracking]);

        return [
            'ActionType' => 'reply',
            'ActionBody' => $label,
            'Text' => $label,
            'TextSize' => 'regular',
            'TrackingData' => $trackingData,
        ];
    }

    private function withSender(array $message): array
    {
        $message['sender'] = array_filter([
            'name' => config('viber.sender_name'),
            'avatar' => config('viber.sender_avatar'),
        ]);

        if (filled(config('viber.auth_token'))) {
            $message['auth_token'] = config('viber.auth_token');
        }

        return $message;
    }
}
