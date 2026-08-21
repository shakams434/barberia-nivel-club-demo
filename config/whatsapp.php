<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'fake'),
    'send_enabled' => (bool) env('WHATSAPP_SEND_ENABLED', false),
    'graph_api_version' => env('META_GRAPH_API_VERSION', 'v25.0'),
    'app_id' => env('META_APP_ID'),
    'embedded_signup_configuration_id' => env('META_EMBEDDED_SIGNUP_CONFIGURATION_ID'),
    'waba_id' => env('META_WABA_ID'),
    'phone_number_id' => env('META_PHONE_NUMBER_ID'),
    'access_token' => env('META_ACCESS_TOKEN'),
    'app_secret' => env('META_APP_SECRET'),
    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    'baileys_base_url' => rtrim((string) env('BAILEYS_BASE_URL'), '/'),
    'baileys_api_token' => env('BAILEYS_API_TOKEN'),
    'baileys_webhook_secret' => env('BAILEYS_WEBHOOK_SECRET'),
    'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT_SECONDS', 5),
    'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 3),
];
