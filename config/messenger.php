<?php

return [
    'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    'welcome_message' => env(
        'MESSENGER_WELCOME_MESSAGE',
        'Dobro došli na Nađi majstora! Izaberite uslugu ili ukucajte tačan naziv.'
    ),
    'greeting_message' => env(
        'MESSENGER_GREETING_MESSAGE',
        'Dobro došli na Nađi majstora! Kliknite Počni ili napišite poruku.'
    ),
];
