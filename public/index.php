<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// メンテナンスモードの確認
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composerのオートローダー登録
require __DIR__.'/../vendor/autoload.php';

// アプリケーションを読み込み
$app = require_once __DIR__.'/../bootstrap/app.php';

// ✅ Laravel 12では Kernel を使って handle する
$response = $app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->handle(Request::capture());

$response->send();

$app->terminate($request = Request::capture(), $response);
