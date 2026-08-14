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

        $token = $this->accessToken($platform);

        if (blank($token)) {
            Log::warning("Meta access token missing for platform: {$platform}");

            return;
        }

        $version = config('meta.graph_version');
        $url = "https://graph.facebook.com/{$version}/me/messages";

        $responses = Http::pool(function ($pool) use ($messages, $url, $token, $recipientId) {
            foreach ($messages as $index => $message) {
                $request = $pool->as((string) $index)->timeout(15)->connectTimeout(5);

                if (! config('meta.verify_ssl', true)) {
                    $request = $request->withoutVerifying();
                }

                $request->post($url, [
                    'access_token' => $token,
                    'recipient' => ['id' => $recipientId],
                    'messaging_type' => 'RESPONSE',
                    'message' => $message,
                ]);
            }
        });

        foreach ($responses as $index => $response) {
            if (! $response->successful()) {
                Log::error('Meta Send API batch error', [
                    'platform' => $platform,
                    'index' => $index,
                    'status' => $response->status(),
                    'response' => $response->json(),
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
        $token = $this->accessToken($platform);

        if (blank($token)) {
            Log::warning("Meta access token missing for get_started setup: {$platform}");

            return false;
        }

        $version = config('meta.graph_version');
        $response = MetaHttp::client()->post("https://graph.facebook.com/{$version}/me/messenger_profile", [
            'access_token' => $token,
            'get_started' => [
                'payload' => 'GET_STARTED',
            ],
        ]);

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
            Log::error('Meta Send API error', [
                'platform' => $platform,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

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
