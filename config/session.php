<?php

use Illuminate\Support\Str;

return [

    'driver' => env('SESSION_DRIVER', 'file'), // ✅ file推奨（開発時に安定）

    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),

    'path' => env('SESSION_PATH', '/'),


    'domain' => env('SESSION_DOMAIN', null),

    // ✅ ローカル開発では false にする（httpsでないため）
    'secure' => env('SESSION_SECURE_COOKIE', false),

    'http_only' => env('SESSION_HTTP_ONLY', true),

    // ✅ クッキーを他オリジンへ渡すには SameSite=None か null
    'same_site' => env('SESSION_SAME_SITE', 'lax'), // 'lax' または null（最終手段は 'none'）

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
];
