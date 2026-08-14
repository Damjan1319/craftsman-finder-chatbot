<?php

return [
    'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    'welcome_message' => env(
        'MESSENGER_WELCOME_MESSAGE',
        'Pronađite proverene majstore u vašem gradu — brzo i jednostavno.'
    ),
];
