<?php

return [
    'enabled' => filter_var(env('RENDER_DEMO_SEED', false), FILTER_VALIDATE_BOOL),
    'admin' => [
        'name' => env('DEMO_ADMIN_NAME'),
        'username' => env('DEMO_ADMIN_USERNAME'),
        'email' => env('DEMO_ADMIN_EMAIL'),
        'password' => env('DEMO_ADMIN_PASSWORD'),
    ],
];
