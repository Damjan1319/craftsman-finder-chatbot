<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaHttp;
use Illuminate\Console\Command;

class MetaDiagnose extends Command
{
    protected $signature = 'meta:diagnose';

    protected $description = 'Proverava Messenger token, profil i česte razloge zašto bot ne odgovara drugima';

    public function handle(): int
    {
        $version = config('meta.graph_version');
        $pageToken = config('messenger.page_access_token');
        $appSecret = config('meta.app_secret');

        $this->info('Meta Messenger dijagnostika');
        $this->newLine();

        if (blank($pageToken)) {
            $this->error('MESSENGER_PAGE_ACCESS_TOKEN nije postavljen.');

            return self::FAILURE;
        }

        if (blank($appSecret)) {
            $this->warn('META_APP_SECRET nije postavljen — webhook potpis neće raditi u produkciji.');
        } else {
            $this->info('META_APP_SECRET: postavljen');
        }

        if (config('meta.skip_signature')) {
            $this->warn('META_SKIP_SIGNATURE=true — potpis se ne proverava.');
        }

        $profileResponse = MetaHttp::client()->get("https://graph.facebook.com/{$version}/me/messenger_profile", [
            'fields' => 'get_started,greeting',
            'access_token' => $pageToken,
        ]);

        if ($profileResponse->successful()) {
            $profile = $profileResponse->json('data.0', []);
            $getStarted = data_get($profile, 'get_started.payload', '—');
            $greeting = data_get($profile, 'greeting.0.text', '—');
            $this->info('Page token: validan (messenger_profile OK)');
            $this->info('Get Started payload: '.$getStarted);
            $this->line('Greeting: '.$greeting);
        } else {
            $error = data_get($profileResponse->json(), 'error.message', 'nepoznata greška');
            $this->error('Page token problem: '.$error);

            return self::FAILURE;
        }

        $pageResponse = MetaHttp::client()->get("https://graph.facebook.com/{$version}/me", [
            'fields' => 'id,name',
            'access_token' => $pageToken,
        ]);

        if ($pageResponse->successful()) {
            $page = $pageResponse->json();
            $this->info('Stranica: '.($page['name'] ?? '?').' (ID: '.($page['id'] ?? '?').')');
        }

        if (filled($appSecret)) {
            $appId = $this->resolveAppId($pageToken, $appSecret, $version);

            if ($appId !== null) {
                $appResponse = MetaHttp::client()->get("https://graph.facebook.com/{$version}/{$appId}", [
                    'fields' => 'name,app_type',
                    'access_token' => $appId.'|'.$appSecret,
                ]);

                if ($appResponse->successful()) {
                    $this->info('Aplikacija: '.($appResponse->json('name') ?? '?').' (ID: '.$appId.')');
                }

                $subsResponse = MetaHttp::client()->get("https://graph.facebook.com/{$version}/{$appId}/subscriptions", [
                    'access_token' => $appId.'|'.$appSecret,
                ]);

                if ($subsResponse->successful()) {
                    $this->newLine();
                    $this->line('Webhook pretplate:');

                    foreach ($subsResponse->json('data', []) as $subscription) {
                        $fields = implode(', ', $subscription['fields'] ?? []);
                        $callback = $subscription['callback_url'] ?? '—';
                        $active = ($subscription['active'] ?? false) ? 'aktivno' : 'neaktivno';
                        $this->line("  - {$subscription['object']} ({$active}): {$fields}");
                        $this->line("    URL: {$callback}");
                    }
                }
            }
        }

        $this->newLine();
        $this->warn('Ako drugi ljudi ne dobijaju odgovor (a ti dobijaš):');
        $this->line('1. Meta Developer Console → App Mode → prebaci na LIVE (ne Development)');
        $this->line('2. App Review → pages_messaging mora imati Advanced Access');
        $this->line('3. Webhook mora imati: messages, messaging_postbacks, messaging_referrals, messaging_optins');
        $this->line('4. Na Vercel-u postavi META_APP_SECRET (App Settings → Basic → App Secret)');
        $this->line('5. Pokreni: php artisan meta:webhook-info --setup');

        return self::SUCCESS;
    }

    private function resolveAppId(string $pageToken, string $appSecret, string $version): ?string
    {
        $response = MetaHttp::client()->get("https://graph.facebook.com/{$version}/debug_token", [
            'input_token' => $pageToken,
            'access_token' => config('meta.app_id').'|'.$appSecret,
        ]);

        if (! $response->successful()) {
            $response = MetaHttp::client()->get("https://graph.facebook.com/{$version}/debug_token", [
                'input_token' => $pageToken,
                'access_token' => $pageToken,
            ]);
        }

        if (! $response->successful()) {
            $this->warn('Nije moguće pročitati app ID — dodaj META_APP_ID u .env ako želiš detaljniju dijagnostiku.');

            return null;
        }

        return (string) data_get($response->json(), 'data.app_id', '');
    }
}
