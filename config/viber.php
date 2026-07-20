<?php

return [
    'auth_token' => env('VIBER_AUTH_TOKEN'),
    'sender_name' => env('VIBER_SENDER_NAME', 'Majstori Bot'),
    'sender_avatar' => env('VIBER_SENDER_AVATAR'),
    'skip_signature' => env('VIBER_SKIP_SIGNATURE', env('APP_ENV') === 'local'),
    'welcome_message' => env(
        'VIBER_WELCOME_MESSAGE',
        'Dobrodošli! Pronađite proverene majstore u vašem gradu brzo i lako.'
    ),
];
