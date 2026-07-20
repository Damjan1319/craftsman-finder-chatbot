<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramHttp;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
                            {url? : HTTPS URL (npr. https://abc.ngrok.io/api/telegram/webhook)}
                            {--info : Prikaži trenutni webhook}
                            {--delete : Obriši webhook}';

    protected $description = 'Registruje Telegram webhook (zahteva TELEGRAM_BOT_TOKEN u .env)';

    public function handle(): int
    {
        $token = config('telegram.bot_token');

        if (blank($token)) {
            $this->error('TELEGRAM_BOT_TOKEN nije postavljen u .env fajlu.');

            return self::FAILURE;
        }

        if ($this->option('info')) {
            return $this->showWebhookInfo($token);
        }

        if ($this->option('delete')) {
            return $this->deleteWebhook($token);
        }

        $url = $this->argument('url');

        if (blank($url)) {
            $this->error('Unesi HTTPS webhook URL.');
            $this->line('Primer: php artisan telegram:set-webhook https://abc.ngrok-free.app/api/telegram/webhook');

            return self::FAILURE;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->error('Telegram prihvata samo HTTPS URL-ove.');

            return self::FAILURE;
        }

        $secret = config('telegram.webhook_secret') ?: Str::random(32);

        if (blank(config('telegram.webhook_secret'))) {
            $this->warn('TELEGRAM_WEBHOOK_SECRET nije u .env — generisan privremeni secret.');
            $this->line("Dodaj u .env: TELEGRAM_WEBHOOK_SECRET={$secret}");
        }

        $response = TelegramHttp::client()->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            $this->error('Greška: '.($data['description'] ?? $response->body()));

            return self::FAILURE;
        }

        $this->info('Telegram webhook uspešno registrovan!');
        $this->line('Direktan link: https://t.me/'.(config('telegram.bot_username') ?: 'BOT_USERNAME'));
        $this->line('Otvori bota u Telegramu i pošalji /start');

        app(TelegramApiClient::class)->setBotCommands();
        app(TelegramApiClient::class)->setBotDescription();
        app(TelegramApiClient::class)->setChatMenuButton();

        if (filled(config('telegram.web_app_url')) && str_starts_with(config('telegram.web_app_url'), 'https://')) {
            $this->info('Mini aplikacija aktivirana — dugme ispod chata otvara web aplikaciju.');
        } else {
            $this->warn('Mini aplikacija nije aktivna — postavi TELEGRAM_WEB_APP_URL na HTTPS domen u .env');
        }

        return self::SUCCESS;
    }

    private function showWebhookInfo(string $token): int
    {
        $response = TelegramHttp::client()->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
        $data = $response->json('result') ?? [];

        $this->info('Bot: @'.(config('telegram.bot_username') ?: '—'));
        $this->line('Webhook URL: '.($data['url'] ?? 'nije postavljen'));
        $this->line('Pending updates: '.($data['pending_update_count'] ?? 0));

        if (filled($data['last_error_message'] ?? null)) {
            $this->warn('Poslednja greška: '.$data['last_error_message']);
        }

        return self::SUCCESS;
    }

    private function deleteWebhook(string $token): int
    {
        $response = TelegramHttp::client()->post("https://api.telegram.org/bot{$token}/deleteWebhook");
        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            $this->error('Greška: '.($data['description'] ?? $response->body()));

            return self::FAILURE;
        }

        $this->info('Webhook obrisan.');

        return self::SUCCESS;
    }
}
