<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramApiClient
{
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): ?int
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $result = $this->request('sendMessage', $payload);

        return isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('editMessageText', $payload) !== null;
    }

    public function deleteMessage(int $chatId, int $messageId): bool
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]) !== null;
    }

    /**
     * @param  array<int, int>  $messageIds
     */
    public function deleteMessages(int $chatId, array $messageIds): void
    {
        $messageIds = array_values(array_unique(array_filter($messageIds)));

        if ($messageIds === []) {
            return;
        }

        if (count($messageIds) === 1) {
            $this->deleteMessage($chatId, (int) $messageIds[0]);

            return;
        }

        $token = config('telegram.bot_token');

        if (blank($token)) {
            return;
        }

        Http::pool(function ($pool) use ($messageIds, $chatId, $token) {
            foreach ($messageIds as $index => $messageId) {
                $request = $pool->as((string) $index)->timeout(15)->connectTimeout(5);

                if (! config('telegram.verify_ssl')) {
                    $request = $request->withoutVerifying();
                }

                $request->post("https://api.telegram.org/bot{$token}/deleteMessage", [
                    'chat_id' => $chatId,
                    'message_id' => (int) $messageId,
                ]);
            }
        });
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
            $payload['show_alert'] = $showAlert;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    public function setChatMenuButton(?string $webAppUrl = null): void
    {
        $webAppUrl ??= config('telegram.web_app_url');

        if (filled($webAppUrl) && str_starts_with($webAppUrl, 'https://')) {
            $this->request('setChatMenuButton', [
                'menu_button' => [
                    'type' => 'web_app',
                    'text' => config('telegram.menu_button_text', 'Pronađi majstora'),
                    'web_app' => [
                        'url' => rtrim($webAppUrl, '/'),
                    ],
                ],
            ]);

            return;
        }

        $this->request('setChatMenuButton', [
            'menu_button' => [
                'type' => 'commands',
            ],
        ]);
    }

    public function setBotCommands(): void
    {
        $this->request('setMyCommands', [
            'commands' => [
                ['command' => 'start', 'description' => 'Početni meni'],
                ['command' => 'menu', 'description' => 'Početak'],
            ],
        ]);
    }

    public function setBotDescription(): void
    {
        $this->request('setMyDescription', [
            'description' => 'Pronađite proverene majstore u vašem gradu.',
        ]);
    }

    private function request(string $method, array $payload): ?array
    {
        $token = config('telegram.bot_token');

        if (blank($token)) {
            Log::warning('Telegram bot token missing');

            return null;
        }

        $response = TelegramHttp::client()->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        $body = $response->json();

        if (! $response->successful() || ! ($body['ok'] ?? false)) {
            $description = $body['description'] ?? 'Unknown error';

            if ($method === 'editMessageText' && str_contains($description, 'message is not modified')) {
                return ['message_id' => $payload['message_id'] ?? null];
            }

            Log::error('Telegram API error', [
                'method' => $method,
                'response' => $body,
            ]);

            return null;
        }

        $result = $body['result'] ?? [];

        if (is_array($result)) {
            return $result;
        }

        // answerCallbackQuery, deleteMessage and editMessageText often return true.
        return $result === true ? [] : null;
    }
}
