<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminShopController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')
    ->middleware(['auth', 'only10']) // ← ここ大事。auth → only10 の順で
    ->group(function () {
        Route::get('shops', [AdminShopController::class, 'index'])->name('shops.index');
        Route::get('shops/{shop}/edit', [AdminShopController::class, 'edit'])->name('shops.edit');
        Route::put('shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
        // ほかの管理画面ルートもここに入れる
    });

Route::get('songs', [\App\Http\Controllers\SongController::class, 'index']);


Route::middleware(['web', 'auth:sanctum'])->get('/user', function (Request $request) {
    return response()->json($request->user());
});


Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['message' => 'Logged out']);
});

// web.php の末尾などに追加
require __DIR__.'/auth.php';

