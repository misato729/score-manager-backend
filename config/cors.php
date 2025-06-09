<?php

return [

    'paths' => [
        'api/*',
        'login',
        'logout',
        'register-user',
        'sanctum/csrf-cookie',
        '/user',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173', 'https://rbplus-rank-manager.site', 'https://score-manager-frontend-stg.vercel.app'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];