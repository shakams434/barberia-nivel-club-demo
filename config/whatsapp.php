<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'fake'),
    'send_enabled' => (bool) env('WHATSAPP_SEND_ENABLED', false),
    'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
    'waba_id' => env('META_WABA_ID'),
    'phone_number_id' => env('META_PHONE_NUMBER_ID'),
    'access_token' => env('META_ACCESS_TOKEN'),
    'app_secret' => env('META_APP_SECRET'),
    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT_SECONDS', 5),
    'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 3),
];
