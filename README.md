# Craftsman Finder Chatbot

Multi-channel chatbot platform for finding verified local craftsmen (handymen). Users pick a service category and city, then browse craftsman profiles with call and contact options.

Built with Laravel and a Filament admin panel for managing categories, craftsmen, subscriptions, and bot settings.

## Features

- **Multi-platform bots:** Viber, Telegram, Facebook Messenger, and Instagram DM
- **Guided flow:** Welcome → category → city → craftsman cards
- **Premium listings:** Featured craftsmen shown first
- **Admin dashboard:** Manage categories, craftsmen, users, and settings
- **PWA web app:** Mobile-friendly public site for browsing craftsmen
- **Usage analytics:** Track bot events per platform

## Tech Stack

| Layer | Technologies |
|-------|--------------|
| Backend | PHP 8.3, Laravel 13 |
| Admin panel | Filament 5, Livewire |
| Database | SQLite (dev) / MySQL / PostgreSQL |
| Bots | Viber Bot API, Telegram Bot API, Meta Graph API (Messenger + Instagram) |
| Frontend | Blade, CSS, PWA (manifest + service worker) |
| Dev tools | Composer, Artisan, ngrok, PHPUnit |

## Bot Flow

1. User opens the bot → welcome message and menu
2. **Find a craftsman** → list of active categories
3. Category selected → list of cities with available craftsmen
4. City selected → craftsman cards (name, city, phone, bio, call/Viber buttons)
5. **About us** → contact info from admin settings

## Quick Start (Local)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- **Admin panel:** http://127.0.0.1:8000/admin  
  - Email: `admin@admin.com`  
  - Password: `password`

- **Web app (PWA):** http://127.0.0.1:8000/

- **Webhooks:**
  - Viber: `POST /api/viber/webhook`
  - Telegram: `POST /api/telegram/webhook`
  - Meta (Messenger + Instagram): `GET+POST /api/meta/webhook`

## Environment Variables

Copy `.env.example` to `.env` and configure:

```env
# Viber
VIBER_AUTH_TOKEN=
VIBER_SKIP_SIGNATURE=true

# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=

# Meta (Messenger + Instagram)
META_APP_SECRET=
META_VERIFY_TOKEN=majstori-verify
MESSENGER_PAGE_ACCESS_TOKEN=
INSTAGRAM_ACCESS_TOKEN=
```

## Webhook Setup

### Telegram

```bash
php artisan serve
ngrok http 8000
php artisan telegram:set-webhook https://YOUR-NGROK.ngrok-free.app/api/telegram/webhook
```

### Meta (Messenger + Instagram)

```bash
php artisan meta:webhook-info https://YOUR-NGROK.ngrok-free.app/api/meta/webhook
```

Register the same callback URL in [Meta Developer Console](https://developers.facebook.com) for both Messenger and Instagram. Subscribe to `messages` and `messaging_postbacks`.

### Viber

Set webhook URL in [Viber Partners](https://partners.viber.com):

```
https://your-domain.com/api/viber/webhook
```

## Testing Viber Locally

```bash
php artisan viber:simulate conversation_started
php artisan viber:simulate message --text="Tražim majstora"
```

## Project Structure

| Component | Path |
|-----------|------|
| Viber webhook | `app/Http/Controllers/Api/ViberWebhookController.php` |
| Telegram webhook | `app/Http/Controllers/Api/TelegramWebhookController.php` |
| Meta webhook | `app/Http/Controllers/Api/MetaWebhookController.php` |
| Viber logic | `app/Services/Viber/ViberWebhookHandler.php` |
| Telegram logic | `app/Services/Telegram/TelegramUpdateHandler.php` |
| Meta logic | `app/Services/Meta/MetaMessagingHandler.php` |
| Admin | `app/Filament/` |
| Migrations | `database/migrations/` |

## License

MIT
