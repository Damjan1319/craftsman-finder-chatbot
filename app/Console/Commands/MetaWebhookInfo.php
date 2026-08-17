<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaApiClient;
use Illuminate\Console\Command;

class MetaWebhookInfo extends Command
{
    protected $signature = 'meta:webhook-info
                            {url? : HTTPS URL (npr. https://abc.ngrok.io/api/meta/webhook)}
                            {--setup : Podesi Get Started dugme na Messenger profilu}';

    protected $description = 'Prikazuje Meta webhook podešavanja za Messenger i Instagram';

    public function handle(MetaApiClient $api): int
    {
        $url = $this->argument('url') ?: rtrim((string) config('app.url'), '/').'/api/meta/webhook';
        $verifyToken = config('meta.verify_token');

        $this->info('Meta Webhook — Messenger + Instagram');
        $this->newLine();
        $this->line('Callback URL: '.$url);
        $this->line('Verify Token: '.$verifyToken);
        $this->newLine();

        $this->warn('U Meta Developer Console (developers.facebook.com):');
        $this->line('1. Otvori svoju aplikaciju');
        $this->line('2. Messenger → Settings → Webhooks → Add Callback URL');
        $this->line('   - Callback URL: '.$url);
        $this->line('   - Verify Token: '.$verifyToken);
        $this->line('   - Subscribe: messages, messaging_postbacks, messaging_referrals, messaging_optins, message_reactions');
        $this->warn('   VAŽNO: mora biti čekirano messaging_postbacks (dugmad), messaging_referrals (prvi ulazak), messaging_optins (Send Message) i message_reactions (like)!');
        $this->line('3. Instagram → Webhooks (ili API setup) → isti Callback URL');
        $this->line('   - Subscribe: messages, messaging_postbacks');
        $this->newLine();

        if (blank(config('meta.app_secret'))) {
            $this->error('META_APP_SECRET nije postavljen u .env');
        } else {
            $this->info('META_APP_SECRET: OK');
        }

        if (blank(config('messenger.page_access_token'))) {
            $this->warn('MESSENGER_PAGE_ACCESS_TOKEN nije postavljen — Messenger neće slati odgovore');
        } else {
            $this->info('MESSENGER_PAGE_ACCESS_TOKEN: OK');

            if ($this->option('setup')) {
                if ($api->configureMessengerProfile()) {
                    $this->info('Messenger profil je podešen (Počni + greeting + predložena pitanja).');
                } else {
                    $this->error('Messenger profil setup nije uspeo — proveri log.');
                }
            }
        }

        if (blank(config('instagram.access_token'))) {
            $this->warn('INSTAGRAM_ACCESS_TOKEN nije postavljen — Instagram neće slati odgovore');
        } else {
            $this->info('INSTAGRAM_ACCESS_TOKEN: OK');
        }

        if (config('meta.skip_signature')) {
            $this->warn('META_SKIP_SIGNATURE=true — potpis se ne proverava (samo za lokalni dev)');
        }

        $this->newLine();
        $this->warn('Ako drugi ljudi ne dobijaju odgovor:');
        $this->line('- Meta aplikacija mora biti u Live modu (App Mode → Live), ne Development');
        $this->line('- Stranica mora imati dozvolu za poruke (messages + messaging_postbacks u webhooku)');
        $this->line('- Pokreni: php artisan meta:webhook-info --setup');
        $this->newLine();
        $this->line('Lokalni test:');
        $this->line('  php artisan serve');
        $this->line('  ngrok http 8000');
        $this->line('  php artisan meta:webhook-info https://TVOJ-NGROK.ngrok-free.app/api/meta/webhook --setup');

        return self::SUCCESS;
    }
}
