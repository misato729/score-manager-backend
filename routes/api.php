<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
// ✅ ユーザー登録（スコア初期化も含む）
Route::post('/register', [UserController::class, 'store']);

use Illuminate\Http\Request;
use App\Http\Controllers\ScoreController;


// ✅ ログインユーザー情報（要ログイン）
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ✅ スコア取得（誰でも閲覧可能）
Route::get('/scores', [ScoreController::class, 'index']);

// ✅ スコア更新・アカウント削除（ログイン必須）
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/scores/{score}', [ScoreController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
