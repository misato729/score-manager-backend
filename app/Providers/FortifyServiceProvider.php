<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ✅ Fortifyのregister動作を無効化（カスタムルートを使うため）
        Fortify::createUsersUsing(function () {
            abort(404); // Breeze登録ルートを無効化
        });
    }
}
