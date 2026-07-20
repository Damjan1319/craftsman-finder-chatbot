<?php

return [
    'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
    'welcome_message' => env(
        'INSTAGRAM_WELCOME_MESSAGE',
        'Dobrodošli! Pronađite proverene majstore u vašem gradu brzo i lako.'
    ),
];
