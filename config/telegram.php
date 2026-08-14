<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'verify_ssl' => env('TELEGRAM_VERIFY_SSL', env('APP_ENV') === 'production'),
    'welcome_message' => env(
        'TELEGRAM_WELCOME_MESSAGE',
        'Pronađite proverene majstore u vašem gradu — brzo i jednostavno.'
    ),
    'web_app_url' => env('TELEGRAM_WEB_APP_URL', env('APP_URL')),
    'menu_button_text' => env('TELEGRAM_MENU_BUTTON_TEXT', 'Pronađi majstora'),
];
