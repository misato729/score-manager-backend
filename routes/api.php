<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// 既存
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\VisitController;

Route::get('/health', fn () => response()->json(['ok' => true], 200));


// ✅ ログイン（セッションCookieを使うので web ミドルウェアを追加）
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('web'); // ← 重要

// ✅ ログアウト（同上）
Route::post('/logout', function (Request $request) {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['message' => 'Logged out']);
})->middleware('web');

// ✅ ユーザー情報（Sanctumで保護）
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// --- 以下、既存のAPIルートはそのままでOK ---
Route::post('/register-user', [UserController::class, 'store']);

Route::get('/scores', [ScoreController::class, 'index']);
Route::get('/user-scores', [ScoreController::class, 'userScores']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/scores/{score}', [ScoreController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::put('/users/target', [UserController::class, 'updateTarget']);
    Route::post('/scores', [ScoreController::class, 'store']);
    Route::post('/visit', [VisitController::class, 'store']);
    Route::get('/visited', [VisitController::class, 'index']);
});

Route::get('/shops', [ShopController::class, 'index']);
Route::get('/visited-shops', [VisitController::class, 'publicIndex']);
