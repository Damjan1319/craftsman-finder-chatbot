<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiClient
{
    public function sendText(string $recipientId, string $text, string $platform, ?array $quickReplies = null): bool
    {
        $message = ['text' => $text];

        if ($quickReplies !== null && $quickReplies !== []) {
            $message['quick_replies'] = $quickReplies;
        }

        return $this->send($recipientId, $message, $platform);
    }

    public function sendTypingOn(string $recipientId, string $platform): void
    {
        $this->sendSenderAction($recipientId, 'typing_on', $platform);
    }

    public function sendSenderAction(string $recipientId, string $action, string $platform): void
    {
        $token = $this->accessToken($platform);

        if (blank($token)) {
            return;
        }

        $version = config('meta.graph_version');

        MetaHttp::client()->post("https://graph.facebook.com/{$version}/me/messages", [
            'access_token' => $token,
            'recipient' => ['id' => $recipientId],
            'sender_action' => $action,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function sendMany(string $recipientId, array $messages, string $platform): void
    {
        if ($messages === []) {
            return;
        }

        if (count($messages) === 1) {
            $this->send($recipientId, $messages[0], $platform);

            return;
        }

        foreach ($messages as $index => $message) {
            if (! $this->send($recipientId, $message, $platform)) {
                Log::error('Meta Send API sequential error', [
                    'platform' => $platform,
                    'index' => $index,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $buttons
     */
    public function sendButtonTemplate(string $recipientId, string $text, array $buttons, string $platform): bool
    {
        return $this->send($recipientId, [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $text,
                    'buttons' => array_slice($buttons, 0, 3),
                ],
            ],
        ], $platform);
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     */
    public function sendGenericTemplate(string $recipientId, array $elements, string $platform): bool
    {
        return $this->send($recipientId, [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => array_slice($elements, 0, 10),
                ],
            ],
        ], $platform);
    }

    public function setMessengerGetStarted(string $platform = 'messenger'): bool
    {
        return $this->configureMessengerProfile($platform);
    }

    public function configureMessengerProfile(string $platform = 'messenger'): bool
    {
        $token = $this->accessToken($platform);

        if (blank($token)) {
            Log::warning("Meta access token missing for messenger profile setup: {$platform}");

            return false;
        }

        $greeting = (string) config('messenger.greeting_message');
        $version = config('meta.graph_version');

        $this->clearMessengerProfileExtras($token, $version);

        $payload = [
            'access_token' => $token,
            'get_started' => [
                'payload' => 'GET_STARTED',
            ],
            'greeting' => [
                [
                    'locale' => 'default',
                    'text' => mb_substr($greeting, 0, 160),
                ],
            ],
            'ice_breakers' => [
                [
                    'question' => 'Tražim majstora',
                    'payload' => 'GET_STARTED',
                ],
                [
                    'question' => 'O nama',
                    'payload' => 'act:about',
                ],
            ],
        ];

        $response = MetaHttp::client()->post("https://graph.facebook.com/{$version}/me/messenger_profile", $payload);

        if (! $response->successful()) {
            Log::error('Meta messenger_profile error', [
                'platform' => $platform,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    private function clearMessengerProfileExtras(string $token, string $version): void
    {
        $response = MetaHttp::client()
            ->withBody(json_encode(['fields' => ['persistent_menu']]), 'application/json')
            ->delete("https://graph.facebook.com/{$version}/me/messenger_profile?access_token={$token}");

        if (! $response->successful() && $response->status() !== 404) {
            Log::warning('Meta messenger_profile cleanup warning', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function send(string $recipientId, array $message, string $platform): bool
    {
        $token = $this->accessToken($platform);

        if (blank($token)) {
            Log::warning("Meta access token missing for platform: {$platform}");

            return false;
        }

        $version = config('meta.graph_version');
        $response = MetaHttp::client()->post("https://graph.facebook.com/{$version}/me/messages", [
            'access_token' => $token,
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message' => $message,
        ]);

        if (! $response->successful()) {
            $json = $response->json();

            Log::error('Meta Send API error', [
                'platform' => $platform,
                'recipient_id' => $recipientId,
                'status' => $response->status(),
                'response' => $json,
            ]);

            $message = data_get($json, 'error.message', '');

            if (str_contains($message, 'outside of allowed window')
                || str_contains($message, 'Cannot message users')
                || data_get($json, 'error.code') === 10) {
                Log::warning('Meta Send API: korisnik nema dozvolu — proveri da li je aplikacija u Live modu', [
                    'recipient_id' => $recipientId,
                ]);
            }

            return false;
        }

        return true;
    }

    private function accessToken(string $platform): ?string
    {
        return match ($platform) {
            'messenger' => config('messenger.page_access_token'),
            'instagram' => config('instagram.access_token'),
            default => null,
        };
    }
}
