<?php

return [

    'paths' => [
        'api/*',                // ✅ これで /api/login, /api/user, ... を網羅
        'sanctum/csrf-cookie',
        'admin/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173', 'https://rbplus-rank-manager.site'], // 後で変える！！！！！！

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
