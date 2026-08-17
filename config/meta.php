<?php

return [
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    'verify_token' => env('META_VERIFY_TOKEN', 'majstori-verify'),
    'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    'skip_signature' => env('META_SKIP_SIGNATURE', env('APP_ENV') === 'local'),
    'verify_ssl' => env('META_VERIFY_SSL', env('APP_ENV') === 'production'),
];
