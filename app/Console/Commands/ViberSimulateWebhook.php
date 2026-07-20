<?php

namespace App\Console\Commands;

use App\Services\Viber\ViberWebhookHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ViberSimulateWebhook extends Command
{
    protected $signature = 'viber:simulate
                            {event=conversation_started : conversation_started | message | webhook}
                            {--text=Tražim majstora : Tekst poruke za message event}
                            {--tracking= : JSON tracking_data za message event}
                            {--url= : Webhook URL (default APP_URL/api/viber/webhook)}';

    protected $description = 'Simulira Viber webhook za lokalno testiranje';

    public function handle(ViberWebhookHandler $handler): int
    {
        $event = $this->argument('event');

        if ($this->option('url')) {
            return $this->sendHttpRequest($event);
        }

        $payload = $this->buildPayload($event);
        $response = $handler->handle($payload);

        $this->info('Event: '.$event);
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function sendHttpRequest(string $event): int
    {
        $url = $this->option('url') ?: rtrim(config('app.url'), '/').'/api/viber/webhook';
        $payload = $this->buildPayload($event);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        $this->info("POST {$url}");
        $this->line($response->body());

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function buildPayload(string $event): array
    {
        $user = [
            'id' => 'test-user-001',
            'name' => 'Test Korisnik',
            'language' => 'sr',
            'country' => 'RS',
            'api_version' => 8,
        ];

        return match ($event) {
            'webhook' => [
                'event' => 'webhook',
                'timestamp' => time(),
            ],
            'conversation_started' => [
                'event' => 'conversation_started',
                'timestamp' => time(),
                'user' => $user,
                'type' => 'open',
            ],
            'message' => [
                'event' => 'message',
                'timestamp' => time(),
                'message_token' => random_int(100000, 999999),
                'sender' => $user,
                'message' => array_filter([
                    'type' => 'text',
                    'text' => $this->option('text'),
                    'tracking_data' => $this->option('tracking'),
                ]),
            ],
            default => throw new \InvalidArgumentException("Nepoznat event: {$event}"),
        };
    }
}
