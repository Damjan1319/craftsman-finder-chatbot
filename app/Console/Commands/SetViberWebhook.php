<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetViberWebhook extends Command
{
    protected $signature = 'viber:set-webhook
                            {url? : HTTPS URL do webhook-a (npr. https://abc.ngrok.io/api/viber/webhook)}
                            {--info : Prikaži trenutni webhook umesto postavljanja}';

    protected $description = 'Registruje webhook URL kod Vibera (zahteva VIBER_AUTH_TOKEN u .env)';

    public function handle(): int
    {
        $token = config('viber.auth_token');

        if (blank($token)) {
            $this->error('VIBER_AUTH_TOKEN nije postavljen u .env fajlu.');
            $this->line('Kopiraj token iz partners.viber.com → tvoj bot → Authentication token');

            return self::FAILURE;
        }

        if ($this->option('info')) {
            return $this->showAccountInfo($token);
        }

        $url = $this->argument('url');

        if (blank($url)) {
            $this->error('Unesi HTTPS webhook URL.');
            $this->line('Primer: php artisan viber:set-webhook https://abc123.ngrok-free.app/api/viber/webhook');

            return self::FAILURE;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->error('Viber prihvata samo HTTPS URL-ove.');

            return self::FAILURE;
        }

        $this->info("Registrujem webhook: {$url}");

        $response = Http::post('https://chatapi.viber.com/pa/set_webhook', [
            'auth_token' => $token,
            'url' => $url,
            'event_types' => [
                'delivered',
                'seen',
                'failed',
                'subscribed',
                'unsubscribed',
                'conversation_started',
                'message',
            ],
        ]);

        $data = $response->json();

        if (($data['status'] ?? 1) !== 0) {
            $this->error('Greška: '.($data['status_message'] ?? $response->body()));

            return self::FAILURE;
        }

        $this->info('Webhook uspešno registrovan!');
        $this->line('Sada otvori bota u Viber aplikaciji i pošalji mu poruku.');

        return self::SUCCESS;
    }

    private function showAccountInfo(string $token): int
    {
        $response = Http::post('https://chatapi.viber.com/pa/get_account_info', [
            'auth_token' => $token,
        ]);

        $data = $response->json();

        if (($data['status'] ?? 1) !== 0) {
            $this->error('Greška: '.($data['status_message'] ?? $response->body()));

            return self::FAILURE;
        }

        $this->info('Bot: '.($data['name'] ?? '—'));
        $this->line('Webhook: '.($data['webhook'] ?? 'nije postavljen'));
        $this->line('Subscribers: '.($data['subscribers_count'] ?? 0));

        return self::SUCCESS;
    }
}
