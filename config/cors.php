<?php

return [

    'paths' => [
        'api/*',
        'login',
        'logout',
        'register-user',
        'sanctum/csrf-cookie',
        '/user',
        'users/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173', 'https://rbplus-rank-manager.site'], // 後で変える！！！！！！

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];