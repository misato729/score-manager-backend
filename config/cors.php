<?php

return [

    'paths' => [
        'api/*',                // ✅ これで /api/login, /api/user, ... を網羅
        'sanctum/csrf-cookie',
        'admin/*'
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => ['http://localhost:5173', 'https://rbplus-rank-manager.site','https://preview.rbplus-rank-manager.site'], //

    'allowed_origins_patterns' => [
        '^https:\/\/[a-z0-9-]+\.vercel\.app$',
        '^https:\/\/rbplus-rank-manager\.site$',
        '^https:\/\/preview\.rbplus-rank-manager\.site$', // ←これも必須
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
