<?php

use App\Http\Controllers\AdminShopController;
use App\Http\Controllers\AdminSongController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('auth');

/*
|--------------------------------------------------------------------------
| ログイン / ログアウト
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| 管理画面ルート（ID=10のみアクセス可）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth', 'only10']) // auth でログイン必須 → only10 でID=10制限
    ->group(function () {
        Route::get('shops', [AdminShopController::class, 'index'])->name('shops.index');
        Route::get('shops/create', [AdminShopController::class, 'create'])->name('shops.create');
        Route::post('shops', [AdminShopController::class, 'store'])->name('shops.store');
        Route::get('shops/{shop}/edit', [AdminShopController::class, 'edit'])->name('shops.edit');
        Route::put('shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
        Route::get('songs', [AdminSongController::class, 'index'])->name('songs.index');
        Route::get('songs/{song}/edit', [AdminSongController::class, 'edit'])->name('songs.edit');
        Route::put('songs/{song}', [AdminSongController::class, 'update'])->name('songs.update');
        // 他の管理画面ルートもここにまとめて追加
    });
