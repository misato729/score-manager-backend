<?php

use Illuminate\Support\Facades\Route;
// ログイン
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::post('/login', [AuthenticatedSessionController::class, 'store']);


use App\Http\Controllers\UserController;
// ✅ ユーザー登録（スコア初期化も含む）
Route::post('/register-user', [UserController::class, 'store']);

use Illuminate\Http\Request;
use App\Http\Controllers\ScoreController;

// ✅ ログインユーザー情報（要ログイン）
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ✅ ログアウト（Sanctumトークンを使用）
use Illuminate\Support\Facades\Auth;
Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['message' => 'Logged out']);
});

// ✅ スコア取得（誰でも閲覧可能）
Route::get('/scores', [ScoreController::class, 'index']);
Route::get('/user-scores', [ScoreController::class, 'userScores']); // ←スコア未登録ユーザーのスコア取得API

// ✅ スコア更新・アカウント削除（ログイン必須）
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/scores/{score}', [ScoreController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});


Route::middleware('auth:sanctum')->post('/scores', [ScoreController::class, 'store']);



