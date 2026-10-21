<?php

return [
    'sentry_dsn' => env('SHOP_SYNC_SENTRY_DSN'),
    'admin_url' => env('SHOP_SYNC_ADMIN_URL', env('APP_URL', 'http://localhost')),
    'github_run_url' => env('SHOP_SYNC_GITHUB_RUN_URL'),
];
