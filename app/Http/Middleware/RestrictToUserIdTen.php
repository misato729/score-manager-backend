<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictToUserIdTen
{
    public function handle(Request $request, Closure $next): Response
    {
        // まずはログインさせる（未ログインならログイン画面へ）
        if (!Auth::check()) {
            // Breeze/Jetstream が入っていれば 'login' ルートがある想定
            return redirect()->route('login');
        }

        // ID=10（ローカルは15）を.envで管理
        $allowedId = (int) env('ALLOWED_USER_ID', 10); // デフォルト10
        if (Auth::id() !== $allowedId) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
