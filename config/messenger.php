<?php

return [
    'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    'welcome_message' => env(
        'MESSENGER_WELCOME_MESSAGE',
        'Pronađite proverene majstore u vašem gradu — brzo i jednostavno.'
    ),
    'greeting_message' => env(
        'MESSENGER_GREETING_MESSAGE',
        'Dobro došli na Nađi majstora! Kliknite Počni da započnete.'
    ),
];
